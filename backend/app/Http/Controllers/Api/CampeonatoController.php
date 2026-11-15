<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CampeonatoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Campeonato::class);
        $campeonatos = $request->user()->campeonatos()->latest()->orderByDesc('id')->get();

        return response()->json($campeonatos);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Campeonato::class);
        $datos = $request->validate([
            'publicado' => 'prohibited',
            'administrador_id' => 'prohibited',
            'user_id' => 'prohibited',
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'temporada' => 'required|string|max:50',
            'formato' => 'required|in:todos_contra_todos,eliminacion_directa,grupos_eliminacion',
        ], [
            'administrador_id.prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'user_id.prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'in' => 'El formato debe ser todos_contra_todos, eliminacion_directa o grupos_eliminacion.',
        ]);

        // El estado inicial lo asigna la base de datos.
        $campeonato = $request->user()->campeonatos()->create($datos);
        $campeonato->refresh();

        return response()->json([
            'message' => 'Campeonato creado correctamente.',
            'campeonato' => $campeonato,
        ], 201);
    }

    public function show(Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('view', $campeonato);

        return response()->json($campeonato);
    }

    public function update(Request $request, Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('update', $campeonato);
        // Solo se actualizan los campos enviados y validados.
        $datos = $request->validate([
            'publicado' => 'prohibited',
            'administrador_id' => 'prohibited',
            'user_id' => 'prohibited',
            'nombre' => 'sometimes|required|string|max:150',
            'descripcion' => 'sometimes|nullable|string',
            'temporada' => 'sometimes|required|string|max:50',
            'formato' => 'sometimes|required|in:todos_contra_todos,eliminacion_directa,grupos_eliminacion',
        ], [
            'administrador_id.prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'user_id.prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'in' => 'El formato debe ser todos_contra_todos, eliminacion_directa o grupos_eliminacion.',
        ]);

        $campeonato = DB::transaction(function () use ($campeonato, $datos) {
            $campeonato = Campeonato::whereKey($campeonato->id)->lockForUpdate()->firstOrFail();
            if (isset($datos['formato']) && $datos['formato'] !== $campeonato->formato) {
                abort_if($campeonato->fases()->exists() || $campeonato->partidos()->exists() || $campeonato->estado !== 'planificacion', 409, 'No se puede cambiar el formato con estructura deportiva o competición iniciada.');
            }
            $campeonato->update($datos);

            return $campeonato;
        });

        return response()->json([
            'message' => 'Campeonato actualizado correctamente.',
            'campeonato' => $campeonato,
        ]);
    }

    public function destroy(Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('delete', $campeonato);
        if ($campeonato->fases()->exists() || $campeonato->inscripcionesEquipos()->exists() || $campeonato->partidos()->exists() || $campeonato->asignacionesArbitro()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el campeonato porque tiene inscripciones, partidos o historial arbitral.',
            ], 409);
        }

        $campeonato->delete();

        return response()->json(['message' => 'Campeonato eliminado correctamente.']);
    }

    public function publicacion(Request $request, Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('publicar', $campeonato);
        $datos = $request->validate(['publicado' => 'required|boolean'], [
            'required' => 'Indica si deseas publicar u ocultar el campeonato.',
            'boolean' => 'El estado de publicación debe ser verdadero o falso.',
        ]);
        $campeonato->publicado = $datos['publicado'];
        $campeonato->save();

        return response()->json([
            'message' => $campeonato->publicado ? 'Campeonato publicado.' : 'Campeonato oculto.',
            'campeonato' => $campeonato,
        ])->header('Cache-Control', 'no-store');
    }
}
