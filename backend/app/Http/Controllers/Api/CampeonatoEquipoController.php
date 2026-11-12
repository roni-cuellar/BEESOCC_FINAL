<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\CampeonatoEquipo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampeonatoEquipoController extends Controller
{
    public function index(Campeonato $campeonato): JsonResponse
    {
        $inscripciones = $campeonato->inscripcionesEquipos()
            ->with('equipo')->latest()->orderByDesc('id')->get();

        return response()->json($inscripciones);
    }

    public function store(Request $request, Campeonato $campeonato): JsonResponse
    {
        $datos = $request->validate([
            'equipo_id' => 'required|integer|exists:equipos,id',
            'campeonato_id' => 'prohibited',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El equipo seleccionado no existe.',
            'prohibited' => 'El campeonato se indica únicamente en la URL.',
        ]);

        if ($campeonato->inscripcionesEquipos()->where('equipo_id', $datos['equipo_id'])->exists()) {
            return response()->json(['message' => 'El equipo ya está inscrito en este campeonato.'], 409);
        }

        try {
            // La relación asigna el campeonato indicado en la URL.
            $inscripcion = $campeonato->inscripcionesEquipos()->create([
                'equipo_id' => $datos['equipo_id'],
                'estado' => 'activo',
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            // MySQL también protege contra inscripciones simultáneas duplicadas.
            return response()->json(['message' => 'El equipo ya está inscrito en este campeonato.'], 409);
        }

        return response()->json([
            'message' => 'Equipo inscrito correctamente.',
            'inscripcion' => $inscripcion->load('equipo'),
        ], 201);
    }

    public function show(CampeonatoEquipo $campeonatoEquipo): JsonResponse
    {
        return response()->json($campeonatoEquipo->load(['campeonato', 'equipo']));
    }

    public function update(Request $request, CampeonatoEquipo $campeonatoEquipo): JsonResponse
    {
        $datos = $request->validate([
            'estado' => 'required|in:activo,retirado',
            'campeonato_id' => 'prohibited',
            'equipo_id' => 'prohibited',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'in' => 'El estado debe ser activo o retirado.',
            'prohibited' => 'No se permite cambiar el campo :attribute de la inscripción.',
        ]);

        // Retirar al equipo conserva su inscripción y su historial.
        $campeonatoEquipo->update(['estado' => $datos['estado']]);

        return response()->json([
            'message' => 'Estado de la inscripción actualizado correctamente.',
            'inscripcion' => $campeonatoEquipo,
        ]);
    }

    public function destroy(CampeonatoEquipo $campeonatoEquipo): JsonResponse
    {
        if ($campeonatoEquipo->plantillas()->exists() || $campeonatoEquipo->participacionesPartidos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la inscripción porque tiene jugadores inscritos o participaciones en partidos. Cambia su estado a retirado para conservar el historial.',
            ], 409);
        }

        $campeonatoEquipo->delete();

        return response()->json(['message' => 'Inscripción eliminada correctamente.']);
    }
}
