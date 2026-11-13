<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventoEstadistico;
use App\Models\Partido;
use App\Models\Plantilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventoEstadisticoController extends Controller
{
    public function index(Partido $partido): JsonResponse
    {
        $eventos = EventoEstadistico::whereHas('equipoPartido', function ($consulta) use ($partido) {
            $consulta->where('partido_id', $partido->id);
        })->with(['plantilla.jugador', 'plantilla.campeonatoEquipo.equipo', 'equipoPartido.campeonatoEquipo.equipo'])
            ->orderBy('minuto')->orderBy('id')->get();

        return response()->json($eventos);
    }

    public function store(Request $request, Partido $partido): JsonResponse
    {
        Gate::authorize('gestionarActa', $partido);
        $datos = $request->validate([
            'equipo_partido_id' => 'required|integer|exists:equipo_partido,id',
            'plantilla_id' => 'required|integer|exists:plantillas,id',
            'tipo' => 'required|in:gol,autogol,asistencia,amarilla,roja,falta',
            'minuto' => 'required|integer|between:0,150',
            'gol_relacionado_id' => 'required_if:tipo,asistencia|nullable|integer|exists:eventos_estadisticos,id',
            'partido_id' => 'prohibited',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'Una asistencia debe indicar su gol_relacionado_id.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El registro indicado en :attribute no existe.',
            'in' => 'El tipo debe ser gol, autogol, asistencia, amarilla, roja o falta.',
            'between' => 'El minuto debe estar entre 0 y 150.',
            'prohibited' => 'El partido se indica únicamente en la URL.',
        ]);

        if ($datos['tipo'] !== 'asistencia' && isset($datos['gol_relacionado_id'])) {
            return response()->json(['message' => 'Solo las asistencias pueden indicar un gol relacionado.'], 422);
        }

        return DB::transaction(function () use ($datos, $partido) {
            // Comparte el bloqueo usado por el controlador de partidos.
            $partido = Partido::whereKey($partido->id)->lockForUpdate()->firstOrFail();

            if ($partido->estado !== 'en_curso') {
                return response()->json(['message' => 'Solo se pueden registrar eventos en un partido en curso.'], 409);
            }

            $participante = $partido->participantes()->with('campeonatoEquipo')
                ->whereKey($datos['equipo_partido_id'])->first();
            $plantilla = Plantilla::with('campeonatoEquipo')->whereKey($datos['plantilla_id'])
                ->lockForUpdate()->first();

            if (! $participante || ! $plantilla) {
                return response()->json(['message' => 'El participante debe pertenecer al partido y la plantilla debe existir.'], 422);
            }

            if ((int) $plantilla->campeonatoEquipo->campeonato_id !== (int) $partido->campeonato_id
                || (int) $participante->campeonatoEquipo->campeonato_id !== (int) $partido->campeonato_id) {
                return response()->json(['message' => 'El jugador y el equipo participante deben pertenecer al campeonato del partido.'], 422);
            }

            if ($datos['tipo'] === 'autogol') {
                // En un autogol, el participante es el beneficiado y el jugador pertenece al rival.
                $esRival = $partido->participantes()
                    ->where('id', '!=', $participante->id)
                    ->where('campeonato_equipo_id', $plantilla->campeonato_equipo_id)->exists();

                if (! $esRival || (int) $plantilla->campeonato_equipo_id === (int) $participante->campeonato_equipo_id) {
                    return response()->json(['message' => 'En un autogol, el jugador debe pertenecer al equipo rival del beneficiado en este partido.'], 422);
                }
            } elseif ((int) $plantilla->campeonato_equipo_id !== (int) $participante->campeonato_equipo_id) {
                return response()->json(['message' => 'La plantilla del jugador no corresponde al equipo participante.'], 422);
            }

            Gate::authorize('participarEnActa', [$plantilla, $partido]);

            if ($datos['tipo'] === 'asistencia') {
                $gol = EventoEstadistico::with('plantilla')->whereKey($datos['gol_relacionado_id'])
                    ->where('tipo', 'gol')->where('equipo_partido_id', $participante->id)->first();

                if (! $gol) {
                    return response()->json(['message' => 'La asistencia debe relacionarse con un gol del mismo partido y equipo beneficiado.'], 422);
                }

                if ((int) $gol->plantilla->jugador_id === (int) $plantilla->jugador_id) {
                    return response()->json(['message' => 'Un jugador no puede asistirse a sí mismo.'], 422);
                }

                if (EventoEstadistico::where('tipo', 'asistencia')->where('gol_relacionado_id', $gol->id)->exists()) {
                    return response()->json(['message' => 'El gol ya tiene una asistencia registrada.'], 409);
                }
            }

            $evento = $participante->eventos()->create([
                'plantilla_id' => $plantilla->id,
                'tipo' => $datos['tipo'],
                'minuto' => $datos['minuto'],
                'gol_relacionado_id' => $datos['tipo'] === 'asistencia' ? $datos['gol_relacionado_id'] : null,
            ]);

            return response()->json([
                'message' => 'Evento registrado correctamente.',
                'evento' => $evento->load(['plantilla.jugador', 'equipoPartido.campeonatoEquipo.equipo', 'golRelacionado']),
            ], 201);
        });
    }

    public function show(EventoEstadistico $evento): JsonResponse
    {
        return response()->json($evento->load([
            'plantilla.jugador', 'plantilla.campeonatoEquipo.equipo',
            'equipoPartido.campeonatoEquipo.equipo', 'golRelacionado',
        ]));
    }

    public function update(Request $request, EventoEstadistico $evento): JsonResponse
    {
        Gate::authorize('update', $evento);
        $datos = $request->validate([
            'minuto' => 'required|integer|between:0,150',
            'partido_id' => 'prohibited',
            'equipo_partido_id' => 'prohibited',
            'plantilla_id' => 'prohibited',
            'jugador_id' => 'prohibited',
            'equipo_id' => 'prohibited',
            'tipo' => 'prohibited',
            'gol_relacionado_id' => 'prohibited',
        ], [
            'required' => 'El minuto es obligatorio.',
            'integer' => 'El minuto debe ser un número entero.',
            'between' => 'El minuto debe estar entre 0 y 150.',
            'prohibited' => 'Solo se permite corregir el minuto del evento.',
        ]);

        $partidoId = $evento->equipoPartido->partido_id;

        return DB::transaction(function () use ($datos, $evento, $partidoId) {
            $partido = Partido::whereKey($partidoId)->lockForUpdate()->firstOrFail();
            $evento = EventoEstadistico::whereKey($evento->id)->lockForUpdate()->firstOrFail();

            if ($partido->estado !== 'en_curso') {
                return response()->json(['message' => 'Solo se pueden corregir eventos mientras el partido esté en curso.'], 409);
            }

            $evento->update(['minuto' => $datos['minuto']]);

            return response()->json(['message' => 'Minuto del evento actualizado correctamente.', 'evento' => $evento]);
        });
    }

    public function destroy(EventoEstadistico $evento): JsonResponse
    {
        Gate::authorize('delete', $evento);
        $partidoId = $evento->equipoPartido->partido_id;

        return DB::transaction(function () use ($evento, $partidoId) {
            $partido = Partido::whereKey($partidoId)->lockForUpdate()->firstOrFail();
            $evento = EventoEstadistico::whereKey($evento->id)->lockForUpdate()->firstOrFail();

            if ($partido->estado !== 'en_curso') {
                return response()->json(['message' => 'Solo se pueden eliminar eventos mientras el partido esté en curso.'], 409);
            }

            if ($evento->tipo === 'gol' && EventoEstadistico::where('tipo', 'asistencia')
                ->where('gol_relacionado_id', $evento->id)->exists()) {
                return response()->json(['message' => 'Primero debes eliminar la asistencia relacionada con este gol.'], 409);
            }

            $evento->delete();

            return response()->json(['message' => 'Evento eliminado correctamente.']);
        });
    }
}
