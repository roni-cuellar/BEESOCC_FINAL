<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventoEstadistico;
use App\Models\Partido;
use App\Services\AuditoriaPartidoService;
use App\Services\ControlPartidoService;
use App\Services\EventoEstadisticoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventoEstadisticoController extends Controller
{
    public function index(Partido $partido): JsonResponse
    {
        Gate::authorize('view', $partido);
        $eventos = EventoEstadistico::whereHas('equipoPartido', function ($consulta) use ($partido) {
            $consulta->where('partido_id', $partido->id);
        })->with(['plantilla.jugador', 'plantilla.campeonatoEquipo.equipo', 'equipoPartido.campeonatoEquipo.equipo'])
            ->orderBy('minuto')->orderBy('id')->get();

        return response()->json($eventos);
    }

    public function store(Request $request, Partido $partido): JsonResponse
    {
        Gate::authorize('arbitrar', $partido);
        $datos = app(EventoEstadisticoService::class)->validar($request->all());

        return DB::transaction(function () use ($datos, $partido) {
            // Comparte el bloqueo usado por el controlador de partidos.
            $partido = app(ControlPartidoService::class)->bloquearActa($partido);

            if ($partido->estado !== 'en_curso') {
                return response()->json(['message' => 'Solo se pueden registrar eventos en un partido en curso.'], 409);
            }

            $auditoria = app(AuditoriaPartidoService::class);
            $anterior = $auditoria->capturar($partido);
            $evento = app(EventoEstadisticoService::class)->registrar($partido, $datos);
            $auditoria->registrar($partido, 'registrar_evento', 'Registro arbitral de evento.', $anterior, $auditoria->capturar($partido));

            return response()->json([
                'message' => 'Evento registrado correctamente.',
                'evento' => $evento->load(['plantilla.jugador', 'equipoPartido.campeonatoEquipo.equipo', 'golRelacionado']),
            ], 201);
        });
    }

    public function show(EventoEstadistico $evento): JsonResponse
    {
        Gate::authorize('view', $evento);

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
            'partido_id' => 'prohibited', 'equipo_partido_id' => 'prohibited',
            'plantilla_id' => 'prohibited', 'jugador_id' => 'prohibited',
            'equipo_id' => 'prohibited', 'tipo' => 'prohibited', 'gol_relacionado_id' => 'prohibited',
        ], [
            'required' => 'El minuto es obligatorio.', 'integer' => 'El minuto debe ser un número entero.',
            'between' => 'El minuto debe estar entre 0 y 150.', 'prohibited' => 'Solo se permite corregir el minuto del evento.',
        ]);

        $partidoId = $evento->equipoPartido->partido_id;

        return DB::transaction(function () use ($datos, $evento, $partidoId) {
            $partido = app(ControlPartidoService::class)->bloquearActa(Partido::findOrFail($partidoId));
            $evento = EventoEstadistico::whereKey($evento->id)->lockForUpdate()->firstOrFail();

            Gate::authorize('update', $evento);

            if ($partido->estado !== 'en_curso') {
                return response()->json(['message' => 'Solo se pueden corregir eventos mientras el partido esté en curso.'], 409);
            }

            $auditoria = app(AuditoriaPartidoService::class);
            $anterior = $auditoria->capturar($partido);
            app(EventoEstadisticoService::class)->corregir($partido, $evento, $datos['minuto']);
            $auditoria->registrar($partido, 'corregir_minuto', 'Corrección de minuto durante el partido.', $anterior, $auditoria->capturar($partido));

            return response()->json(['message' => 'Minuto del evento actualizado correctamente.', 'evento' => $evento]);
        });
    }

    public function destroy(EventoEstadistico $evento): JsonResponse
    {
        Gate::authorize('delete', $evento);
        $partidoId = $evento->equipoPartido->partido_id;

        return DB::transaction(function () use ($evento, $partidoId) {
            $partido = app(ControlPartidoService::class)->bloquearActa(Partido::findOrFail($partidoId));
            $evento = EventoEstadistico::whereKey($evento->id)->lockForUpdate()->firstOrFail();

            Gate::authorize('update', $evento);

            if ($partido->estado !== 'en_curso') {
                return response()->json(['message' => 'Solo se pueden eliminar eventos mientras el partido esté en curso.'], 409);
            }

            $auditoria = app(AuditoriaPartidoService::class);
            $anterior = $auditoria->capturar($partido);
            app(EventoEstadisticoService::class)->eliminar($partido, $evento);
            $auditoria->registrar($partido, 'eliminar_evento', 'Retiro arbitral de evento durante el partido.', $anterior, $auditoria->capturar($partido));

            return response()->json(['message' => 'Evento eliminado correctamente.']);
        });
    }
}
