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
        // La sanción posterior al cuadro no reescribe la elegibilidad de su fase de origen.
        $generacion = AuditoriaCambio::where('entidad_tipo', 'fase')->where('entidad_id', $fase->id)->where('accion', 'generar_eliminacion_grupos')->latest('id')->first();
        $estadosHistoricos = $generacion ? AuditoriaCambio::where('campeonato_id', $fase->campeonato_id)->where('accion', 'inactivar_equipo')->where('id', '>', $generacion->id)->get()->mapWithKeys(fn ($a) => [$a->entidad_id => $a->anterior_json['estado']])->all() : [];
        $tabla = [];
        $elegibles = true;
        foreach ($miembros as $miembro) {
            $inscripcion = $miembro->inscripcion;
            abort_unless($miembro->fase_id === $fase->id && $inscripcion && $inscripcion->campeonato_id === $fase->campeonato_id && $inscripcion->equipo, 409, 'Pertenencia de grupo inconsistente.');
            $estadoGrupo = $estadosHistoricos[$inscripcion->id] ?? $inscripcion->estado;
            $elegibleGrupo = $miembro->estado === 'activo' && $estadoGrupo === 'activo';
            $elegibles = $elegibles && ($elegibleGrupo || ($inscripcion->inactivado_at && $inscripcion->motivo_inactividad));
            $tabla[$inscripcion->id] = ['fase_equipo_id' => $miembro->id, 'campeonato_equipo_id' => $inscripcion->id,
                'equipo_id' => $inscripcion->equipo_id, 'nombre' => $inscripcion->equipo->nombre,
                'elegible_clasificacion' => $elegibleGrupo, 'estado_actual' => $inscripcion->estado,
                'PJ' => 0, 'PG' => 0, 'PE' => 0, 'PP' => 0, 'GF' => 0, 'GC' => 0, 'DG' => 0, 'PTS' => 0];
        }
        $partidos = $grupo->partidos()->where('tipo_partido', 'regular')->with('participantes')->orderBy('id')->get();
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
            $oficial = $partido->esOficial();
            $oficiales += $oficial ? 1 : 0;
            $marcador = app(MarcadorService::class)->calcular($partido);
            $gl = $marcador['local']['goles'];
            $gv = $marcador['visitante']['goles'];
            $resultados[] = [$partido->id, $local, $visitante, $partido->estado, $partido->oficializado_at?->toISOString(), $partido->tipo_resultado, $gl, $gv];
            if ($partido->estado !== 'finalizado' || ! in_array($partido->tipo_resultado, ['deportivo', 'administrativo'], true) || ($soloOficiales && ! $oficial)) {
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
        $n = count($tabla);
        $esperados = intdiv($n * ($n - 1), 2);
        $generado = AuditoriaCambio::where('entidad_tipo', 'fase')->where('entidad_id', $fase->id)->where('accion', 'generar_calendario_grupos')->exists();
        $completa = $generado && $n > 0 && count($pares) === $esperados && $oficiales === $esperados && $elegibles;
        $huella = hash('sha256', json_encode([$grupo->id, $miembros->map(fn ($m) => [$m->id, $m->campeonato_equipo_id, $m->estado, $estadosHistoricos[$m->campeonato_equipo_id] ?? $m->inscripcion->estado])->all(), $resultados], JSON_THROW_ON_ERROR));
        $orden = app(OrdenGruposService::class);
        $bloques = [];
        foreach ($orden->bloques(array_values($tabla)) as $bloque) {
            array_push($bloques, ...$orden->directos($bloque, array_values($directos)));
        }
        $ordenadas = $pendientes = [];
        $usados = [];
        $posicion = 1;
        // Los expedientes independientes dependen solo del calendario regular.
        $huellaRegular = $huella;
        foreach ($bloques as $bloque) {
            $resolucion = app(DesempateGrupoService::class)->resolver($grupo, $bloque, $posicion, $huellaRegular, $completa);
            array_push($ordenadas, ...$resolucion['filas']);
            array_push($pendientes, ...$resolucion['pendientes']);
            array_push($usados, ...$resolucion['usados']);
            $huella = hash('sha256', $huella.$resolucion['huella']);
            $posicion += count($bloque);
        }

        return ['grupo_id' => $grupo->id, 'fase_id' => $fase->id, 'vista' => $soloOficiales ? 'oficial' : 'provisional',
            'definitiva' => $completa && ! $pendientes, 'partidos_esperados' => $esperados,
            'partidos_registrados' => $partidos->count(), 'partidos_oficiales' => $oficiales, 'partidos_computados' => $computados,
            'calendario_generado' => $generado, 'resultados_completos' => $completa, 'elegibles' => $elegibles,
            'tabla' => $ordenadas, 'pendientes_resolucion' => $pendientes,
            'desempates_usados' => $usados, 'huella' => $huella];
    }
}
