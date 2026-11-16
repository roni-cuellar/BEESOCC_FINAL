<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\Campeonato;
use App\Models\CampeonatoEquipo;
use App\Models\Fase;

class DeportePublicoService
{
    private function equipo(CampeonatoEquipo $inscripcion, Campeonato $liga): array
    {
        abort_unless($inscripcion->campeonato_id === $liga->id && $inscripcion->equipo
            && $inscripcion->equipo->administrador_id === $liga->administrador_id, 404);

        return $inscripcion->equipo->only(['id', 'nombre', 'escudo']);
    }

    private function tabla(array $tabla): array
    {
        return collect($tabla)->only(['grupo_id', 'vista', 'definitiva', 'partidos_oficiales', 'partidos_esperados'])->all()
            + ['tabla' => collect($tabla['tabla'])->map(fn ($fila) => collect($fila)->only([
                'campeonato_equipo_id', 'nombre', 'posicion', 'PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS',
            ])->all())->all(), 'pendientes_resolucion' => collect($tabla['pendientes_resolucion'])
                ->map(fn ($p) => ['rango_posiciones' => $p['rango_posiciones']])->all()];
    }

    public function consultar(Campeonato $liga): array
    {
        abort_unless($liga->publicado && $liga->administrador_id, 404);
        $fases = [];
        foreach ($liga->fases()->orderBy('orden')->get() as $fase) {
            $datos = $fase->only(['id', 'nombre', 'tipo', 'estado']);
            $datos['equipos'] = $fase->equipos()->with('inscripcion.equipo')->get()->map(fn ($m) => [
                'id' => $m->id, 'campeonato_equipo_id' => $m->campeonato_equipo_id,
                'inscripcion' => ['equipo' => $this->equipo($m->inscripcion, $liga)],
            ])->all();
            if ($fase->tipo === 'grupos') {
                $vista = app(ClasificacionGruposService::class)->consultarPublica($fase);
                $datos['clasificacion'] = ['clasificacion_vigente' => $vista['clasificacion_vigente'],
                    'candidatos' => $vista['clasificacion_vigente'] ? collect($vista['candidatos'])->map(fn ($c) => collect($c)->only([
                        'campeonato_equipo_id', 'origen_grupo_id', 'origen_puesto',
                    ])->all())->all() : []];
                $datos['grupos'] = $fase->grupos->map(fn ($g) => $g->only(['id', 'codigo', 'nombre']))->all();
                $datos['tablas_oficiales'] = array_map(fn ($t) => $this->tabla($t), $vista['tablas']);
                $datos['tablas_provisionales'] = $fase->grupos->map(fn ($g) => $this->tabla(app(TablaGruposService::class)->consultarPublica($g)))->all();
                $datos['rondas_desempate'] = $this->rondas($fase, $liga);
            } else {
                $datos['rondas'] = $this->rondas($fase, $liga);
            }
            $fases[] = $datos;
        }
        // El campeón procede de una resolución de Laravel, nunca del orden visual de la tabla.
        $campeon = null;
        if ($liga->estado === 'finalizado') {
            $registro = AuditoriaCambio::where('campeonato_id', $liga->id)->where('entidad_tipo', 'campeonato')
                ->where('entidad_id', $liga->id)->where('accion', 'finalizar_campeonato')->latest('id')->first();
            $id = $registro?->posterior_json['campeon_campeonato_equipo_id'] ?? null;
            $inscripcion = $liga->inscripcionesEquipos()->whereKey($id)->where('estado', 'activo')->first();
            if ($inscripcion) {
                $campeon = $this->equipo($inscripcion, $liga);
            }
        }

        return ['fases' => $fases, 'campeon' => $campeon];
    }

    private function rondas(Fase $fase, Campeonato $liga): array
    {
        return $fase->rondas()->with('llaves.plazas', 'llaves.partidos.participantes.campeonatoEquipo.equipo')->get()->map(function ($ronda) use ($liga) {
            return $ronda->only(['id', 'numero', 'nombre', 'modalidad', 'desempate', 'estado']) + [
                'llaves' => $ronda->llaves->map(function ($llave) use ($liga) {
                    return $llave->only(['id', 'numero', 'estado', 'ganador_fase_equipo_id', 'metodo_resolucion', 'penales_plaza_1', 'penales_plaza_2']) + [
                        'plazas' => $llave->plazas->map(fn ($p) => $p->only(['id', 'posicion', 'fase_equipo_id', 'origen_tipo', 'origen_llave_id', 'origen_grupo_id', 'origen_puesto'])),
                        'partidos' => $llave->partidos->map(function ($p) use ($liga) {
                            foreach ($p->participantes as $participante) {
                                $this->equipo($participante->campeonatoEquipo, $liga);
                            }
                            $m = app(MarcadorService::class)->calcular($p);

                            return $p->only(['id', 'numero_serie', 'estado', 'tipo_resultado', 'oficializado_at']) + [
                                'marcador' => ['local' => collect($m['local'])->only(['nombre', 'goles']), 'visitante' => collect($m['visitante'])->only(['nombre', 'goles'])],
                                'marcador_deportivo' => $m['marcador_deportivo'],
                            ];
                        }),
                    ];
                }),
            ];
        })->all();
    }
}
