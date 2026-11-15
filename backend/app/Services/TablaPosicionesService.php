<?php

namespace App\Services;

use App\Models\Campeonato;
use Illuminate\Http\JsonResponse;

class TablaPosicionesService
{
    public function index(Campeonato $campeonato): JsonResponse
    {
        $configuracion = $campeonato->configuracion()->first();
        $orden = $configuracion ? $configuracion->orden_desempate : 'puntos,diferencia_goles,goles_favor';
        $criterios = array_map('trim', explode(',', $orden));

        if (! in_array($criterios, [
            ['puntos', 'diferencia_goles', 'goles_favor'],
            ['puntos', 'goles_favor', 'diferencia_goles'],
        ], true)) {
            return response()->json(['message' => 'El orden de desempate debe ser puntos,diferencia_goles,goles_favor o puntos,goles_favor,diferencia_goles.'], 422);
        }

        // No se filtra por estado: los equipos retirados conservan su participación.
        $inscripciones = $campeonato->inscripcionesEquipos()->with('equipo')->get();
        $tabla = [];

        foreach ($inscripciones as $inscripcion) {
            if (! $inscripcion->equipo) {
                return response()->json(['message' => 'No se puede calcular la tabla: una inscripción no tiene un equipo válido.'], 409);
            }

            $tabla[$inscripcion->id] = [
                'equipo_id' => $inscripcion->equipo->id,
                'nombre' => $inscripcion->equipo->nombre,
                'PJ' => 0, 'PG' => 0, 'PE' => 0, 'PP' => 0,
                'GF' => 0, 'GC' => 0, 'DG' => 0, 'PTS' => 0,
            ];
        }

        $partidos = $campeonato->partidos()->where('estado', 'finalizado')
            ->with(['participantes.campeonatoEquipo.equipo', 'participantes.eventos'])->get();

        foreach ($partidos as $partido) {
            $participantes = $partido->participantes;
            $local = $participantes->firstWhere('rol', 'local');
            $visitante = $participantes->firstWhere('rol', 'visitante');

            if ($participantes->count() !== 2 || ! $local || ! $visitante) {
                return response()->json(['message' => 'El partido '.$partido->id.' debe tener exactamente un local y un visitante para calcular la tabla.'], 409);
            }

            foreach ($participantes as $participante) {
                $inscripcion = $participante->campeonatoEquipo;

                if (! $inscripcion || ! $inscripcion->equipo
                    || (int) $inscripcion->campeonato_id !== (int) $campeonato->id
                    || ! isset($tabla[$participante->campeonato_equipo_id])
                    || (int) $tabla[$participante->campeonato_equipo_id]['equipo_id'] !== (int) $inscripcion->equipo_id) {
                    return response()->json(['message' => 'El partido '.$partido->id.' tiene participantes inconsistentes con las inscripciones del campeonato.'], 409);
                }
            }

            $localId = $local->campeonato_equipo_id;
            $visitanteId = $visitante->campeonato_equipo_id;

            if ($tabla[$localId]['equipo_id'] === $tabla[$visitanteId]['equipo_id']) {
                return response()->json(['message' => 'El partido '.$partido->id.' tiene el mismo equipo como local y visitante.'], 409);
            }

            // Igual que en el marcador: el autogol pertenece al equipo beneficiado.
            $golesLocal = app(MarcadorService::class)->goles($local);
            $golesVisitante = app(MarcadorService::class)->goles($visitante);

            foreach ([[$localId, $golesLocal, $golesVisitante], [$visitanteId, $golesVisitante, $golesLocal]] as $resultado) {
                [$inscripcionId, $golesFavor, $golesContra] = $resultado;
                $tabla[$inscripcionId]['PJ']++;
                $tabla[$inscripcionId]['GF'] += $golesFavor;
                $tabla[$inscripcionId]['GC'] += $golesContra;

                if ($golesFavor > $golesContra) {
                    $tabla[$inscripcionId]['PG']++;
                    $tabla[$inscripcionId]['PTS'] += 3;
                } elseif ($golesFavor === $golesContra) {
                    $tabla[$inscripcionId]['PE']++;
                    $tabla[$inscripcionId]['PTS']++;
                } else {
                    $tabla[$inscripcionId]['PP']++;
                }

                $tabla[$inscripcionId]['DG'] = $tabla[$inscripcionId]['GF'] - $tabla[$inscripcionId]['GC'];
            }
        }

        $columnas = ['puntos' => 'PTS', 'diferencia_goles' => 'DG', 'goles_favor' => 'GF'];
        $tabla = array_values($tabla);

        usort($tabla, function ($primero, $segundo) use ($criterios, $columnas) {
            foreach ($criterios as $criterio) {
                $columna = $columnas[$criterio];
                $comparacion = $segundo[$columna] <=> $primero[$columna];

                if ($comparacion !== 0) {
                    return $comparacion;
                }
            }

            $comparacion = strcasecmp($primero['nombre'], $segundo['nombre']);

            return $comparacion !== 0 ? $comparacion : $primero['equipo_id'] <=> $segundo['equipo_id'];
        });

        foreach ($tabla as $indice => $equipo) {
            $tabla[$indice]['posicion'] = $indice + 1;
        }

        return response()->json([
            'campeonato_id' => $campeonato->id,
            'nombre' => $campeonato->nombre,
            'tabla' => $tabla,
        ]);
    }
}
