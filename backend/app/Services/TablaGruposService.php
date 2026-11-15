<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\Grupo;
use Illuminate\Support\Facades\Gate;

class TablaGruposService
{
    public function calcular(Grupo $grupo, bool $soloOficiales = false): array
    {
        Gate::authorize('view', $grupo);
        $fase = $grupo->fase;
        abort_unless($fase->tipo === 'grupos' && $fase->campeonato->formato === 'grupos_eliminacion', 409, 'La tabla requiere una fase de grupos.');
        $miembros = $grupo->equipos()->with('inscripcion.equipo')->get();
        $tabla = [];
        $elegibles = true;
        foreach ($miembros as $miembro) {
            $inscripcion = $miembro->inscripcion;
            abort_unless($miembro->fase_id === $fase->id && $inscripcion && $inscripcion->campeonato_id === $fase->campeonato_id && $inscripcion->equipo, 409, 'Pertenencia de grupo inconsistente.');
            $elegibles = $elegibles && $miembro->estado === 'activo' && $inscripcion->estado === 'activo';
            $tabla[$inscripcion->id] = ['fase_equipo_id' => $miembro->id, 'campeonato_equipo_id' => $inscripcion->id,
                'equipo_id' => $inscripcion->equipo_id, 'nombre' => $inscripcion->equipo->nombre,
                'PJ' => 0, 'PG' => 0, 'PE' => 0, 'PP' => 0, 'GF' => 0, 'GC' => 0, 'DG' => 0, 'PTS' => 0];
        }
        $partidos = $grupo->partidos()->with('participantes')->get();
        $pares = [];
        $directos = [];
        $resultados = [];
        $oficiales = $computados = 0;
        foreach ($partidos as $partido) {
            abort_unless($partido->fase_id === $fase->id && $partido->campeonato_id === $fase->campeonato_id
                && ! $partido->llave_id && $partido->tipo_partido === 'regular', 409, 'Partido ajeno al calendario regular del grupo.');
            $local = $partido->participantes->firstWhere('rol', 'local')?->campeonato_equipo_id;
            $visitante = $partido->participantes->firstWhere('rol', 'visitante')?->campeonato_equipo_id;
            abort_unless($partido->participantes->count() === 2 && $local !== $visitante && isset($tabla[$local], $tabla[$visitante]), 409, 'Los participantes deben pertenecer al mismo grupo.');
            $par = [min($local, $visitante), max($local, $visitante)];
            $clave = implode(':', $par);
            abort_if(isset($pares[$clave]), 409, 'Hay un enfrentamiento duplicado en el grupo.');
            $pares[$clave] = true;
            $oficial = $partido->estado === 'finalizado' && $partido->oficializado_at && $partido->tipo_resultado === 'deportivo';
            $oficiales += $oficial ? 1 : 0;
            $marcador = app(MarcadorService::class)->calcular($partido);
            $gl = $marcador['local']['goles'];
            $gv = $marcador['visitante']['goles'];
            $resultados[] = [$partido->id, $local, $visitante, $partido->estado, $partido->oficializado_at?->toISOString(), $partido->tipo_resultado, $gl, $gv];
            if ($partido->estado !== 'finalizado' || $partido->tipo_resultado !== 'deportivo' || ($soloOficiales && ! $oficial)) {
                continue;
            }
            $computados++;
            $directos[$clave] = ['local' => $local, 'visitante' => $visitante, 'goles_local' => $gl, 'goles_visitante' => $gv];
            foreach ([[$local, $gl, $gv], [$visitante, $gv, $gl]] as [$id, $gf, $gc]) {
                $tabla[$id]['PJ']++;
                $tabla[$id]['GF'] += $gf;
                $tabla[$id]['GC'] += $gc;
                $tabla[$id]['DG'] = $tabla[$id]['GF'] - $tabla[$id]['GC'];
                $tabla[$id][$gf > $gc ? 'PG' : ($gf === $gc ? 'PE' : 'PP')]++;
                $tabla[$id]['PTS'] += $gf > $gc ? 3 : ($gf === $gc ? 1 : 0);
            }
        }
        $filas = array_values($tabla);
        usort($filas, fn ($a, $b) => [$b['PTS'], $b['DG'], $b['GF']] <=> [$a['PTS'], $a['DG'], $a['GF']]);
        $bloques = [];
        foreach ($filas as $fila) {
            $bloques[$fila['PTS'].':'.$fila['DG'].':'.$fila['GF']][] = $fila;
        }
        $ordenadas = $pendientes = [];
        $posicion = 1;
        foreach ($bloques as $bloque) {
            $resuelto = count($bloque) === 1;
            $motivo = 'empate_multiple_pendiente_DP01_DP05';
            if (count($bloque) === 2) {
                [$a, $b] = array_column($bloque, 'campeonato_equipo_id');
                $directo = $directos[min($a, $b).':'.max($a, $b)] ?? null;
                $motivo = $directo ? 'partido_extra_pendiente' : 'enfrentamiento_directo_pendiente';
                if ($directo && $directo['goles_local'] !== $directo['goles_visitante']) {
                    $ganador = $directo[$directo['goles_local'] > $directo['goles_visitante'] ? 'local' : 'visitante'];
                    usort($bloque, fn ($a, $b) => ($b['campeonato_equipo_id'] === $ganador) <=> ($a['campeonato_equipo_id'] === $ganador));
                    $resuelto = true;
                }
            }
            if (! $resuelto) {
                $pendientes[] = ['motivo' => $motivo, 'inscripciones' => array_column($bloque, 'campeonato_equipo_id'), 'rango_posiciones' => [$posicion, $posicion + count($bloque) - 1]];
            }
            foreach ($bloque as $i => $fila) {
                // Ni nombre ni ID deciden una posición deportiva empatada.
                $ordenadas[] = $fila + ['posicion' => $resuelto ? $posicion + $i : null];
            }
            $posicion += count($bloque);
        }
        $n = count($tabla);
        $esperados = intdiv($n * ($n - 1), 2);
        $generado = AuditoriaCambio::where('entidad_tipo', 'fase')->where('entidad_id', $fase->id)->where('accion', 'generar_calendario_grupos')->exists();
        $completa = $generado && $n > 0 && count($pares) === $esperados && $oficiales === $esperados && $elegibles;

        return ['grupo_id' => $grupo->id, 'fase_id' => $fase->id, 'vista' => $soloOficiales ? 'oficial' : 'provisional',
            'definitiva' => $completa && ! $pendientes, 'partidos_esperados' => $esperados,
            'partidos_registrados' => $partidos->count(), 'partidos_oficiales' => $oficiales, 'partidos_computados' => $computados,
            'calendario_generado' => $generado, 'resultados_completos' => $completa, 'elegibles' => $elegibles,
            'tabla' => $ordenadas, 'pendientes_resolucion' => $pendientes,
            'huella' => hash('sha256', json_encode([$grupo->id, $miembros->map(fn ($m) => [$m->id, $m->campeonato_equipo_id, $m->estado, $m->inscripcion->estado])->all(), $resultados], JSON_THROW_ON_ERROR))];
    }
}
