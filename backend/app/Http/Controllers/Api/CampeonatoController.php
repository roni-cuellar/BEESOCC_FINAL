<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampeonatoController extends Controller
{
    public function index(): JsonResponse
    {
        $campeonatos = Campeonato::latest()->orderByDesc('id')->get();

        return response()->json($campeonatos);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'temporada' => 'required|string|max:50',
            'formato' => 'required|in:todos_contra_todos',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'in' => 'El campo :attribute solo permite todos_contra_todos.',
        ]);

        // El estado inicial lo asigna la base de datos.
        $campeonato = Campeonato::create($datos);
        $campeonato->refresh();

        return response()->json([
            'message' => 'Campeonato creado correctamente.',
            'campeonato' => $campeonato,
        ], 201);
    }

    public function show(Campeonato $campeonato): JsonResponse
    {
        return response()->json($campeonato);
    }

    public function update(Request $request, Campeonato $campeonato): JsonResponse
    {
        // Solo se actualizan los campos enviados y validados.
        $datos = $request->validate([
            'nombre' => 'sometimes|required|string|max:150',
            'descripcion' => 'sometimes|nullable|string',
            'temporada' => 'sometimes|required|string|max:50',
            'formato' => 'sometimes|required|in:todos_contra_todos',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'in' => 'El campo :attribute solo permite todos_contra_todos.',
        ]);

        $campeonato->update($datos);

        return response()->json([
            'message' => 'Campeonato actualizado correctamente.',
            'campeonato' => $campeonato,
        ]);
    }

    public function destroy(Campeonato $campeonato): JsonResponse
    {
        if ($campeonato->inscripcionesEquipos()->exists() || $campeonato->partidos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el campeonato porque tiene equipos inscritos o partidos asociados.',
            ], 409);
        }

        $campeonato->delete();

        return response()->json(['message' => 'Campeonato eliminado correctamente.']);
    }
}
