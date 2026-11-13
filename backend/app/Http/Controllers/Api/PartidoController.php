<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\CampeonatoEquipo;
use App\Models\Partido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PartidoController extends Controller
{
    public function index(Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('view', $campeonato);
        $partidos = $campeonato->partidos()->with('participantes.campeonatoEquipo.equipo')
            ->orderBy('jornada')->orderBy('fecha_hora')->orderBy('id')->get();

        return response()->json($partidos);
    }

    public function marcador(Partido $partido): JsonResponse
    {
        Gate::authorize('update', $partido);
        $participantes = $partido->participantes()
            ->with(['campeonatoEquipo.equipo', 'eventos'])->get();
        $local = $participantes->firstWhere('rol', 'local');
        $visitante = $participantes->firstWhere('rol', 'visitante');

        if ($participantes->count() !== 2 || ! $local || ! $visitante) {
            return response()->json(['message' => 'No se puede calcular el marcador: el partido debe tener exactamente un local y un visitante.'], 409);
        }

        foreach ($participantes as $participante) {
            if (! $participante->campeonatoEquipo || ! $participante->campeonatoEquipo->equipo
                || (int) $participante->campeonatoEquipo->campeonato_id !== (int) $partido->campeonato_id) {
                return response()->json(['message' => 'No se puede calcular el marcador: los participantes deben tener equipos válidos del campeonato.'], 409);
            }
        }

        if ((int) $local->campeonatoEquipo->equipo_id === (int) $visitante->campeonatoEquipo->equipo_id) {
            return response()->json(['message' => 'No se puede calcular el marcador: el local y el visitante deben ser equipos diferentes.'], 409);
        }

        // El autogol ya está asociado al participante beneficiado.
        $golesLocal = $local->eventos->whereIn('tipo', ['gol', 'autogol'])->count();
        $golesVisitante = $visitante->eventos->whereIn('tipo', ['gol', 'autogol'])->count();

        return response()->json([
            'partido_id' => $partido->id,
            'jornada' => $partido->jornada,
            'estado' => $partido->estado,
            'local' => [
                'id' => $local->campeonatoEquipo->equipo->id,
                'nombre' => $local->campeonatoEquipo->equipo->nombre,
                'goles' => $golesLocal,
            ],
            'visitante' => [
                'id' => $visitante->campeonatoEquipo->equipo->id,
                'nombre' => $visitante->campeonatoEquipo->equipo->nombre,
                'goles' => $golesVisitante,
            ],
        ]);
    }

    public function generarCalendario(Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('update', $campeonato);

        return DB::transaction(function () use ($campeonato) {
            // Dos generaciones del mismo campeonato deben esperar este bloqueo.
            $campeonato = Campeonato::whereKey($campeonato->id)->lockForUpdate()->firstOrFail();

            if ($campeonato->partidos()->exists()) {
                return response()->json(['message' => 'El campeonato ya tiene partidos registrados. No se puede generar otro calendario.'], 409);
            }

            if ($campeonato->formato !== 'todos_contra_todos') {
                return response()->json(['message' => 'Solo se puede generar el calendario para el formato todos_contra_todos.'], 422);
            }

            $configuracion = $campeonato->configuracion()->first();
            $vueltas = $configuracion ? $configuracion->numero_vueltas : 1;

            if (! in_array($vueltas, [1, 2], true)) {
                return response()->json(['message' => 'El número de vueltas debe ser 1 o 2.'], 422);
            }

            foreach ($campeonato->inscripcionesEquipos()->where('estado', 'activo')->with('equipo')->get() as $inscripcion) {
                Gate::authorize('update', $inscripcion);
            }

            $equipos = $campeonato->inscripcionesEquipos()->where('estado', 'activo')
                ->orderBy('id')->pluck('id')->all();
            $cantidadEquipos = count($equipos);

            if ($cantidadEquipos < 2) {
                return response()->json(['message' => 'Se necesitan por lo menos dos equipos activos para generar el calendario.'], 422);
            }

            // Construye los enfrentamientos sin guardar datos todavía.
            if ($cantidadEquipos % 2 !== 0) {
                $equipos[] = null; // Esta posición representa el descanso.
            }

            $cantidadPosiciones = count($equipos);
            $jornadasPorVuelta = $cantidadPosiciones - 1;
            $calendario = [];

            for ($vuelta = 1; $vuelta <= $vueltas; $vuelta++) {
                $rotacion = $equipos;

                for ($ronda = 0; $ronda < $jornadasPorVuelta; $ronda++) {
                    for ($indice = 0; $indice < $cantidadPosiciones / 2; $indice++) {
                        $local = $rotacion[$indice];
                        $visitante = $rotacion[$cantidadPosiciones - 1 - $indice];

                        if ($local === null || $visitante === null) {
                            continue;
                        }

                        // Alterna la localía y la invierte en la segunda vuelta.
                        if (($ronda % 2 !== 0) !== ($vuelta === 2)) {
                            $auxiliar = $local;
                            $local = $visitante;
                            $visitante = $auxiliar;
                        }

                        $calendario[] = [
                            'jornada' => ($vuelta - 1) * $jornadasPorVuelta + $ronda + 1,
                            'local_id' => $local,
                            'visitante_id' => $visitante,
                        ];
                    }

                    // Mantiene el primer equipo fijo y rota los demás.
                    $ultimo = array_pop($rotacion);
                    array_splice($rotacion, 1, 0, [$ultimo]);
                }
            }

            // Guarda cada partido y sus dos participantes en la misma transacción.
            foreach ($calendario as $encuentro) {
                $partido = $campeonato->partidos()->create([
                    'jornada' => $encuentro['jornada'],
                    'estado' => 'programado',
                    'fecha_hora' => null,
                    'lugar' => null,
                ]);

                $partido->participantes()->createMany([
                    ['campeonato_equipo_id' => $encuentro['local_id'], 'rol' => 'local'],
                    ['campeonato_equipo_id' => $encuentro['visitante_id'], 'rol' => 'visitante'],
                ]);
            }

            return response()->json([
                'message' => 'Calendario generado correctamente.',
                'cantidad_equipos' => $cantidadEquipos,
                'vueltas' => $vueltas,
                'jornadas' => $jornadasPorVuelta * $vueltas,
                'partidos_creados' => count($calendario),
                'partidos' => $campeonato->partidos()->with('participantes.campeonatoEquipo.equipo')
                    ->orderBy('jornada')->orderBy('id')->get(),
            ], 201);
        });
    }

    public function store(Request $request, Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('update', $campeonato);
        // Los IDs corresponden a inscripciones activas del campeonato de la URL.
        $equipoActivo = Rule::exists('campeonato_equipos', 'id')
            ->where('campeonato_id', $campeonato->id)->where('estado', 'activo');

        $datos = $request->validate([
            'jornada' => 'required|integer|min:1',
            'fecha_hora' => 'nullable|date',
            'lugar' => 'nullable|string|max:150',
            'local_id' => ['required', 'integer', $equipoActivo],
            'visitante_id' => ['required', 'integer', 'different:local_id', $equipoActivo],
            'campeonato_id' => 'prohibited',
            'estado' => 'prohibited',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min' => 'La jornada debe ser mayor o igual a 1.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'exists' => 'El equipo debe tener una inscripción activa en este campeonato.',
            'different' => 'El equipo visitante debe ser diferente del local.',
            'prohibited' => 'No se permite enviar el campo :attribute al crear el partido.',
        ]);

        foreach ([$datos['local_id'], $datos['visitante_id']] as $inscripcionId) {
            Gate::authorize('update', CampeonatoEquipo::findOrFail($inscripcionId));
        }

        return DB::transaction(function () use ($datos, $campeonato) {
            $partido = $campeonato->partidos()->create([
                'jornada' => $datos['jornada'],
                'fecha_hora' => $datos['fecha_hora'] ?? null,
                'lugar' => $datos['lugar'] ?? null,
                'estado' => 'programado',
            ]);

            $partido->participantes()->createMany([
                ['campeonato_equipo_id' => $datos['local_id'], 'rol' => 'local'],
                ['campeonato_equipo_id' => $datos['visitante_id'], 'rol' => 'visitante'],
            ]);

            return response()->json([
                'message' => 'Partido creado correctamente.',
                'partido' => $partido->load('participantes.campeonatoEquipo.equipo'),
            ], 201);
        });
    }

    public function show(Partido $partido): JsonResponse
    {
        Gate::authorize('update', $partido);

        return response()->json($partido->load(['campeonato', 'participantes.campeonatoEquipo.equipo']));
    }

    public function update(Request $request, Partido $partido): JsonResponse
    {
        Gate::authorize('update', $partido);
        if ($request->has('estado')) {
            Gate::authorize('gestionarActa', $partido);
        }
        $equipoActivo = Rule::exists('campeonato_equipos', 'id')
            ->where('campeonato_id', $partido->campeonato_id)->where('estado', 'activo');

        $datos = $request->validate([
            'jornada' => 'sometimes|required|integer|min:1',
            'fecha_hora' => 'sometimes|nullable|date',
            'lugar' => 'sometimes|nullable|string|max:150',
            'local_id' => ['required_with:visitante_id', 'integer', $equipoActivo],
            'visitante_id' => ['required_with:local_id', 'integer', 'different:local_id', $equipoActivo],
            'estado' => 'sometimes|required|in:programado,en_curso,finalizado,suspendido,cancelado',
            'campeonato_id' => 'prohibited',
        ], [
            'required' => 'El campo :attribute no puede estar vacío.',
            'required_with' => 'Para cambiar participantes debes enviar local_id y visitante_id.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min' => 'La jornada debe ser mayor o igual a 1.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'exists' => 'El equipo debe tener una inscripción activa en este campeonato.',
            'different' => 'El equipo visitante debe ser diferente del local.',
            'in' => 'El estado debe ser programado, en_curso, finalizado, suspendido o cancelado.',
            'prohibited' => 'No se permite cambiar el campeonato del partido.',
        ]);

        foreach (array_filter([$datos['local_id'] ?? null, $datos['visitante_id'] ?? null]) as $inscripcionId) {
            Gate::authorize('update', CampeonatoEquipo::findOrFail($inscripcionId));
        }

        return DB::transaction(function () use ($request, $datos, $partido) {
            // Serializa las modificaciones del mismo partido.
            $partido = Partido::whereKey($partido->id)->lockForUpdate()->firstOrFail();

            if (in_array($partido->estado, ['finalizado', 'cancelado'])) {
                return response()->json(['message' => 'Un partido finalizado o cancelado no se puede modificar.'], 409);
            }

            $transiciones = [
                'programado' => ['en_curso', 'cancelado'],
                'en_curso' => ['suspendido', 'finalizado', 'cancelado'],
                'suspendido' => ['en_curso', 'cancelado'],
            ];

            if (isset($datos['estado']) && $datos['estado'] !== $partido->estado
                && ! in_array($datos['estado'], $transiciones[$partido->estado] ?? [])) {
                return response()->json(['message' => 'El cambio de estado solicitado no está permitido.'], 409);
            }

            if (isset($datos['local_id'], $datos['visitante_id'])) {
                if ($partido->estado !== 'programado' || $partido->participantes()->whereHas('eventos')->exists()) {
                    return response()->json(['message' => 'Solo se pueden cambiar participantes de un partido programado y sin eventos.'], 409);
                }

                $participantes = $partido->participantes()->lockForUpdate()->get();
                $local = $participantes->firstWhere('rol', 'local');
                $visitante = $participantes->firstWhere('rol', 'visitante');

                if ($participantes->count() !== 2 || ! $local || ! $visitante) {
                    return response()->json(['message' => 'El partido debe tener exactamente un local y un visitante antes de editar sus participantes.'], 409);
                }

                if ((int) $datos['local_id'] === (int) $visitante->campeonato_equipo_id) {
                    // Intercambia roles sin repetir equipo ni rol durante la transacción.
                    $local->update(['rol' => 'temporal']);
                    $visitante->update(['rol' => 'local']);
                    $local->update(['rol' => 'visitante']);
                    $anteriorLocal = $local;
                    $local = $visitante;
                    $visitante = $anteriorLocal;
                }

                $local->update(['campeonato_equipo_id' => $datos['local_id']]);
                $visitante->update(['campeonato_equipo_id' => $datos['visitante_id']]);
            }

            $partido->update($request->only(['jornada', 'fecha_hora', 'lugar', 'estado']));

            return response()->json([
                'message' => 'Partido actualizado correctamente.',
                'partido' => $partido->load('participantes.campeonatoEquipo.equipo'),
            ]);
        });
    }

    public function destroy(Partido $partido): JsonResponse
    {
        Gate::authorize('delete', $partido);

        return DB::transaction(function () use ($partido) {
            $partido = Partido::whereKey($partido->id)->lockForUpdate()->firstOrFail();

            if ($partido->participantes()->whereHas('eventos')->exists()) {
                return response()->json(['message' => 'No se puede eliminar el partido porque tiene eventos estadísticos.'], 409);
            }

            // La cascada elimina únicamente el partido y sus participaciones.
            $partido->delete();

            return response()->json(['message' => 'Partido eliminado correctamente.']);
        });
    }
}
