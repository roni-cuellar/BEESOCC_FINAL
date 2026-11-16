<?php

namespace App\Services;

use App\Models\Fase;
use App\Models\Grupo;
use App\Models\GrupoDesempate;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DesempateGrupoService
{
    public function resolver(Grupo $grupo, array $filas, int $posicion, string $contexto, bool $completa): array
    {
        if (count($filas) === 1) {
            return ['filas' => [$filas[0] + ['posicion' => $posicion]], 'pendientes' => [], 'usados' => [], 'huella' => ''];
        }
        $ids = array_column($filas, 'campeonato_equipo_id');
        sort($ids);
        $clave = hash('sha256', json_encode([$contexto, $ids, $posicion], JSON_THROW_ON_ERROR));
        $caso = $completa ? GrupoDesempate::where('grupo_id', $grupo->id)->where('clave', $clave)->first() : null;
        $pendiente = ['clave' => $clave, 'inscripciones' => $ids, 'rango_posiciones' => [$posicion, $posicion + count($ids) - 1],
            'motivo' => count($ids) === 2 ? 'partido_extra_pendiente' : 'liguilla_adicional_pendiente', 'generable' => $completa && ! $caso,
            'desempate_id' => $caso?->id, 'llave_id' => $caso?->llave_id];
        $huella = '';
        $usados = [];
        $partidos = [];
        if ($caso) {
            $usados[] = $caso->id;
            $partidos = $caso->partidos()->with('participantes')->orderBy('id')->get();
            $datos = [];
            $oficiales = true;
            $pares = [];
            $mini = [];
            foreach ($filas as $fila) {
                $mini[$fila['campeonato_equipo_id']] = array_replace($fila, array_fill_keys(['PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS'], 0));
            }
            foreach ($partidos as $partido) {
                $local = $partido->participantes->firstWhere('rol', 'local')?->campeonato_equipo_id;
                $visitante = $partido->participantes->firstWhere('rol', 'visitante')?->campeonato_equipo_id;
                $par = min($local, $visitante).':'.max($local, $visitante);
                abort_unless($partido->fase_id === $grupo->fase_id && $partido->grupo_id === $grupo->id && $partido->campeonato_id === $grupo->fase->campeonato_id
                    && $partido->tipo_partido === 'desempate' && $partido->llave_id === $caso->llave_id && $partido->participantes->count() === 2
                    && $local !== $visitante && isset($mini[$local], $mini[$visitante]) && ! isset($pares[$par]), 409, 'Partido de desempate inconsistente.');
                $pares[$par] = true;
                $marcador = app(MarcadorService::class)->calcular($partido);
                $gl = $marcador['local']['goles'];
                $gv = $marcador['visitante']['goles'];
                $datos[] = ['local' => $local, 'visitante' => $visitante, 'goles_local' => $gl, 'goles_visitante' => $gv];
                $oficiales = $oficiales && $partido->esOficial();
                app(OrdenGruposService::class)->acumular($mini, $local, $visitante, $gl, $gv);
            }
            $huella = hash('sha256', json_encode([$caso->id, $datos, $oficiales, $caso->llave?->attributesToArray()], JSON_THROW_ON_ERROR));
            if ($oficiales && count($pares) === intdiv(count($ids) * (count($ids) - 1), 2)) {
                if ($caso->llave_id) {
                    if ($caso->llave->estado === 'resuelta') {
                        // Consulta de una fase pasada; avanzar una llave siempre valida elegibilidad actual.
                        $fasePasada = Fase::where('fase_origen_id', $grupo->fase_id)->exists();
                        $ganador = app(AvanceEliminacionService::class)->ganador($caso->llave, true, ! $fasePasada);
                        abort_unless($ganador === $caso->llave->ganador_fase_equipo_id, 409, 'La resolución del desempate cambió.');
                        $inscripcion = $caso->llave->ganador->campeonato_equipo_id;
                        usort($filas, fn ($a, $b) => ($b['campeonato_equipo_id'] === $inscripcion) <=> ($a['campeonato_equipo_id'] === $inscripcion));

                        return ['filas' => array_map(fn ($f, $i) => $f + ['posicion' => $posicion + $i], $filas, array_keys($filas)), 'pendientes' => [], 'usados' => $usados, 'huella' => $huella];
                    }
                    $pendiente['motivo'] = 'avance_desempate_pendiente';
                } else {
                    $resultado = ['filas' => [], 'pendientes' => [], 'usados' => $usados, 'huella' => $huella];
                    $originales = array_column($filas, null, 'campeonato_equipo_id');
                    foreach (app(OrdenGruposService::class)->bloques(array_values($mini)) as $bloque) {
                        foreach (app(OrdenGruposService::class)->directos($bloque, $datos) as $subgrupo) {
                            $regulares = array_map(fn ($f) => $originales[$f['campeonato_equipo_id']], $subgrupo);
                            $sub = $this->resolver($grupo, $regulares, $posicion, $huella, true);
                            foreach (['filas', 'pendientes', 'usados'] as $campo) {
                                array_push($resultado[$campo], ...$sub[$campo]);
                            }
                            $resultado['huella'] .= $sub['huella'];
                            $posicion += count($subgrupo);
                        }
                    }

                    return $resultado;
                }
            } else {
                $pendiente['motivo'] = 'resultados_adicionales_pendientes';
            }
        }

        return ['filas' => array_map(fn ($f) => $f + ['posicion' => null], $filas), 'pendientes' => [$pendiente], 'usados' => $usados, 'huella' => $huella];
    }

    public function previsualizar(Grupo $grupo, string $clave): array
    {
        Gate::authorize('update', $grupo->fase);
        $tabla = app(TablaGruposService::class)->calcular($grupo, true);
        $pendiente = collect($tabla['pendientes_resolucion'])->firstWhere('clave', $clave);
        abort_unless($pendiente && $pendiente['generable'], 409, 'El desempate no está vigente o ya tiene partidos.');
        $vista = ['grupo_id' => $grupo->id, 'clave' => $clave, 'inscripciones' => $pendiente['inscripciones'],
            'posicion_inicio' => $pendiente['rango_posiciones'][0], 'tipo' => count($pendiente['inscripciones']) === 2 ? 'partido_extra' : 'liguilla', 'huella' => $tabla['huella']];

        return $vista + ['confirmacion' => hash_hmac('sha256', json_encode($vista, JSON_THROW_ON_ERROR), config('app.key'))];
    }

    public function generar(Grupo $grupo, string $clave, string $confirmacion): GrupoDesempate
    {
        return DB::transaction(function () use ($grupo, $clave, $confirmacion) {
            $fase = app(DistribucionGruposService::class)->bloquear($grupo->fase);
            abort_if($fase->campeonato->fases()->where('fase_origen_id', $fase->id)->exists(), 409, 'La eliminación ya fue generada.');
            $vista = $this->previsualizar($grupo, $clave);
            abort_unless(hash_equals($vista['confirmacion'], $confirmacion), 409, 'Cambió el desempate; consulta la vista previa.');
            $caso = GrupoDesempate::create(collect($vista)->only(['grupo_id', 'clave', 'inscripciones', 'posicion_inicio'])->all());
            if (count($vista['inscripciones']) === 2) {
                $ronda = $fase->rondas()->create(['numero' => ($fase->rondas()->max('numero') ?? 0) + 1, 'nombre' => 'Partido extra', 'modalidad' => 'unico', 'desempate' => 'penales']);
                $llave = $ronda->llaves()->create(['numero' => 1]);
                foreach ($vista['inscripciones'] as $i => $id) {
                    $llave->plazas()->create(['posicion' => $i + 1, 'fase_equipo_id' => $fase->equipos()->where('campeonato_equipo_id', $id)->sole()->id, 'origen_tipo' => 'directo']);
                }
                $caso->update(['llave_id' => $llave->id]);
                app(AvanceEliminacionService::class)->preparar($llave);
                $llave->partidos()->update(['grupo_id' => $grupo->id, 'tipo_partido' => 'desempate', 'grupo_desempate_id' => $caso->id]);
            } else {
                $rotacion = $vista['inscripciones'];
                if (count($rotacion) % 2) {
                    $rotacion[] = null;
                }
                $n = count($rotacion);
                for ($jornada = 1; $jornada < $n; $jornada++) {
                    for ($i = 0; $i < $n / 2; $i++) {
                        [$local, $visitante] = [$rotacion[$i], $rotacion[$n - 1 - $i]];
                        if ($local === null || $visitante === null) {
                            continue;
                        }
                        $partido = new Partido(['campeonato_id' => $fase->campeonato_id, 'jornada' => $jornada, 'estado' => 'programado']);
                        $partido->fase_id = $fase->id;
                        $partido->grupo_id = $grupo->id;
                        $partido->grupo_desempate_id = $caso->id;
                        $partido->tipo_partido = 'desempate';
                        $partido->save();
                        $partido->participantes()->createMany([['campeonato_equipo_id' => $local, 'rol' => 'local'], ['campeonato_equipo_id' => $visitante, 'rol' => 'visitante']]);
                    }
                    array_splice($rotacion, 1, 0, [array_pop($rotacion)]);
                }
            }
            app(DistribucionGruposService::class)->auditar($fase, 'generar_desempate_grupo', 'Desempate adicional confirmado expresamente.', null, $vista + ['desempate_id' => $caso->id]);

            return $caso->fresh()->load('partidos.participantes', 'llave');
        });
    }

    public function comprobarVigente(Partido $partido): void
    {
        if ($partido->grupo_desempate_id) {
            $tabla = app(TablaGruposService::class)->calcular($partido->grupo, true);
            abort_unless(in_array($partido->grupo_desempate_id, $tabla['desempates_usados'], true), 409, 'El desempate quedó obsoleto por una corrección oficial.');
        }
    }
}
