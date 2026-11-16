<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Models\EventoEstadistico;
use Illuminate\Http\JsonResponse;

class EstadisticaService
{
    public function goleadores(Campeonato $campeonato): JsonResponse
    {
        return $this->clasificacionJugadores($campeonato, 'gol', 'goleadores');
    }

    public function asistencias(Campeonato $campeonato): JsonResponse
    {
        return $this->clasificacionJugadores($campeonato, 'asistencia', 'asistencias');
    }

    public function amarillas(Campeonato $campeonato): JsonResponse
    {
        return $this->clasificacionJugadores($campeonato, 'amarilla', 'amarillas');
    }

    public function rojas(Campeonato $campeonato): JsonResponse
    {
        return $this->clasificacionJugadores($campeonato, 'roja', 'rojas');
    }

    public function faltas(Campeonato $campeonato): JsonResponse
    {
        return $this->clasificacionJugadores($campeonato, 'falta', 'faltas');
    }

    // Las cinco clasificaciones usan el mismo conteo, cambiando solo el tipo.
    private function clasificacionJugadores(Campeonato $campeonato, string $tipo, string $estadistica): JsonResponse
    {
        $eventos = EventoEstadistico::where('tipo', $tipo)
            ->whereHas('equipoPartido.partido', function ($consulta) use ($campeonato) {
                $consulta->where('campeonato_id', $campeonato->id)
                    ->whereIn('estado', ['en_curso', 'suspendido', 'finalizado']);
            })->with(['plantilla.jugador', 'plantilla.campeonatoEquipo'])->get();
        $resultados = [];

        foreach ($eventos as $evento) {
            $plantilla = $evento->plantilla;

            if (! $plantilla || ! $plantilla->jugador || ! $plantilla->campeonatoEquipo
                || (int) $plantilla->campeonatoEquipo->campeonato_id !== (int) $campeonato->id) {
                return response()->json(['message' => 'No se pueden calcular las estadísticas: un evento tiene una inscripción o jugador inconsistente con el campeonato.'], 409);
            }

            // Se agrupa por jugador, incluso si tuvo varias inscripciones.
            $jugador = $plantilla->jugador;

            if (! isset($resultados[$jugador->id])) {
                $resultados[$jugador->id] = [
                    'jugador_id' => $jugador->id,
                    'nombre' => $jugador->nombre,
                    'apellido' => $jugador->apellido,
                    'total' => 0,
                ];
            }

            $resultados[$jugador->id]['total']++;
        }

        $resultados = array_values($resultados);
        usort($resultados, function ($primero, $segundo) {
            $comparacion = $segundo['total'] <=> $primero['total'];

            if ($comparacion !== 0) {
                return $comparacion;
            }

            $comparacion = strcasecmp($primero['nombre'], $segundo['nombre']);

            return $comparacion !== 0 ? $comparacion : $primero['jugador_id'] <=> $segundo['jugador_id'];
        });

        return response()->json([
            'campeonato_id' => $campeonato->id,
            'estadistica' => $estadistica,
            'resultados' => $resultados,
        ]);
    }

    public function equipos(Campeonato $campeonato): JsonResponse
    {
        $inscripciones = $campeonato->inscripcionesEquipos()->with('equipo')->get();
        $resultados = [];

        foreach ($inscripciones as $inscripcion) {
            if (! $inscripcion->equipo) {
                return response()->json(['message' => 'No se pueden calcular las estadísticas: una inscripción no tiene equipo.'], 409);
            }

            $resultados[$inscripcion->id] = [
                'equipo_id' => $inscripcion->equipo->id,
                'nombre' => $inscripcion->equipo->nombre,
                'goles' => 0, 'amarillas' => 0, 'rojas' => 0, 'faltas' => 0,
            ];
        }

        $eventos = EventoEstadistico::whereIn('tipo', ['gol', 'autogol', 'amarilla', 'roja', 'falta'])
            ->whereHas('equipoPartido.partido', function ($consulta) use ($campeonato) {
                $consulta->where('campeonato_id', $campeonato->id)
                    ->whereIn('estado', ['en_curso', 'suspendido', 'finalizado']);
            })->with(['equipoPartido.partido', 'plantilla'])->get();

        $columnas = ['gol' => 'goles', 'autogol' => 'goles', 'amarilla' => 'amarillas', 'roja' => 'rojas', 'falta' => 'faltas'];

        foreach ($eventos as $evento) {
            if (! $evento->equipoPartido || ! $evento->plantilla
                || ! isset($resultados[$evento->equipoPartido->campeonato_equipo_id])
                || ! isset($resultados[$evento->plantilla->campeonato_equipo_id])) {
                return response()->json(['message' => 'No se pueden calcular las estadísticas: un evento tiene participantes o plantillas ajenos a las inscripciones del campeonato.'], 409);
            }

            if (in_array($evento->tipo, ['gol', 'autogol'])) {
                // El acta conserva esos goles reales, pero el total oficial de equipo usa el 3–0.
                if ($evento->equipoPartido->partido->tipo_resultado === 'administrativo') {
                    continue;
                }
                // El gol se atribuye al participante beneficiado.
                $inscripcionId = $evento->equipoPartido->campeonato_equipo_id;
            } else {
                // Las sanciones y faltas pertenecen a la inscripción histórica del jugador.
                $inscripcionId = $evento->plantilla->campeonato_equipo_id;
            }

            $resultados[$inscripcionId][$columnas[$evento->tipo]]++;
        }

        foreach ($campeonato->partidos()->where('tipo_resultado', 'administrativo')->whereNotNull('oficializado_at')->with('participantes')->get() as $p) {
            foreach ($p->participantes as $participante) {
                $resultados[$participante->campeonato_equipo_id]['goles'] += app(MarcadorService::class)->goles($participante);
            }
        }
        $resultados = array_values($resultados);
        usort($resultados, function ($primero, $segundo) {
            $comparacion = $segundo['goles'] <=> $primero['goles'];

            if ($comparacion !== 0) {
                return $comparacion;
            }

            $comparacion = strcasecmp($primero['nombre'], $segundo['nombre']);

            return $comparacion !== 0 ? $comparacion : $primero['equipo_id'] <=> $segundo['equipo_id'];
        });

        return response()->json([
            'campeonato_id' => $campeonato->id,
            'estadistica' => 'equipos',
            'resultados' => $resultados,
        ]);
    }
}
