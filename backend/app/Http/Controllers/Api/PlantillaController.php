<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampeonatoEquipo;
use App\Models\Jugador;
use App\Models\Plantilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlantillaController extends Controller
{
    public function index(CampeonatoEquipo $campeonatoEquipo): JsonResponse
    {
        $plantillas = $campeonatoEquipo->plantillas()
            ->with('jugador')->latest()->orderByDesc('id')->get();

        return response()->json($plantillas);
    }

    public function store(Request $request, CampeonatoEquipo $campeonatoEquipo): JsonResponse
    {
        Gate::authorize('update', $campeonatoEquipo);
        $datos = $request->validate([
            'jugador_id' => 'required|integer|exists:jugadores,id',
            'dorsal' => 'required|integer|between:1,99',
            'posicion' => 'required|string|max:50',
            'fecha_ingreso' => 'required|date',
            'campeonato_equipo_id' => 'prohibited',
            'fecha_salida' => 'prohibited',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El jugador seleccionado no existe.',
            'between' => 'El dorsal debe estar entre 1 y 99.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'prohibited' => 'No se permite enviar el campo :attribute al crear la inscripción.',
        ]);

        Gate::authorize('usar', Jugador::findOrFail($datos['jugador_id']));

        if ($campeonatoEquipo->estado !== 'activo') {
            return response()->json(['message' => 'Solo se pueden inscribir jugadores en un equipo activo.'], 409);
        }

        // Busca al jugador en todos los equipos del mismo campeonato.
        $jugadorInscrito = Plantilla::where('jugador_id', $datos['jugador_id'])
            ->whereNull('fecha_salida')
            ->whereHas('campeonatoEquipo', function ($consulta) use ($campeonatoEquipo) {
                $consulta->where('campeonato_id', $campeonatoEquipo->campeonato_id);
            })->exists();

        if ($jugadorInscrito) {
            return response()->json(['message' => 'El jugador ya tiene una inscripción activa en este campeonato.'], 409);
        }

        $dorsalOcupado = $campeonatoEquipo->plantillas()
            ->whereNull('fecha_salida')->where('dorsal', $datos['dorsal'])->exists();

        if ($dorsalOcupado) {
            return response()->json(['message' => 'El dorsal ya está ocupado por otro jugador activo de este equipo.'], 409);
        }

        $plantilla = $campeonatoEquipo->plantillas()->create([
            'jugador_id' => $datos['jugador_id'],
            'dorsal' => $datos['dorsal'],
            'posicion' => $datos['posicion'],
            'fecha_ingreso' => $datos['fecha_ingreso'],
            'fecha_salida' => null,
        ]);

        return response()->json([
            'message' => 'Jugador inscrito correctamente.',
            'plantilla' => $plantilla->load('jugador'),
        ], 201);
    }

    public function show(Plantilla $plantilla): JsonResponse
    {
        return response()->json($plantilla->load(['jugador', 'campeonatoEquipo.equipo']));
    }

    public function update(Request $request, Plantilla $plantilla): JsonResponse
    {
        Gate::authorize('update', $plantilla);
        if ($plantilla->fecha_salida !== null) {
            return response()->json(['message' => 'La inscripción está cerrada y se conserva como historial. No se puede modificar ni reactivar.'], 409);
        }

        $datos = $request->validate([
            'dorsal' => 'sometimes|required|integer|between:1,99',
            'posicion' => 'sometimes|required|string|max:50',
            'fecha_salida' => 'sometimes|required|date|after_or_equal:'.$plantilla->fecha_ingreso->toDateString(),
            'jugador_id' => 'prohibited',
            'campeonato_equipo_id' => 'prohibited',
            'fecha_ingreso' => 'prohibited',
        ], [
            'required' => 'El campo :attribute no puede estar vacío.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'between' => 'El dorsal debe estar entre 1 y 99.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'after_or_equal' => 'La fecha de salida no puede ser anterior a la fecha de ingreso.',
            'prohibited' => 'No se permite modificar el campo :attribute de la inscripción.',
        ]);

        if (isset($datos['dorsal'])) {
            $dorsalOcupado = Plantilla::where('campeonato_equipo_id', $plantilla->campeonato_equipo_id)
                ->whereNull('fecha_salida')->where('dorsal', $datos['dorsal'])
                ->where('id', '!=', $plantilla->id)->exists();

            if ($dorsalOcupado) {
                return response()->json(['message' => 'El dorsal ya está ocupado por otro jugador activo de este equipo.'], 409);
            }
        }

        // Solo estos campos validados pueden cambiar; la inscripción anterior permanece.
        $plantilla->update($request->only(['dorsal', 'posicion', 'fecha_salida']));

        return response()->json([
            'message' => 'Inscripción del jugador actualizada correctamente.',
            'plantilla' => $plantilla,
        ]);
    }

    public function destroy(Plantilla $plantilla): JsonResponse
    {
        Gate::authorize('delete', $plantilla);
        if ($plantilla->eventos()->exists()) {
            return response()->json(['message' => 'No se puede eliminar la inscripción porque tiene eventos estadísticos. Registra la fecha de salida para retirarla si sigue activa.'], 409);
        }

        if ($plantilla->fecha_salida !== null) {
            return response()->json(['message' => 'No se puede eliminar una inscripción cerrada porque forma parte del historial del jugador.'], 409);
        }

        // Solo permite corregir una inscripción activa hecha por error y sin eventos.
        $plantilla->delete();

        return response()->json(['message' => 'Inscripción eliminada correctamente como corrección.']);
    }
}
