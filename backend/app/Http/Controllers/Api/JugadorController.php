<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jugador;
use App\Services\ImagenCatalogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JugadorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jugadores = $request->user()->jugadores()->latest()->orderByDesc('id')->get();

        return response()->json($jugadores);
    }

    public function store(Request $request, ImagenCatalogoService $imagenes): JsonResponse
    {
        Gate::authorize('create', Jugador::class);
        $datos = $request->validate([
            'administrador_id' => 'prohibited',
            'user_id' => 'prohibited',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'fecha_nacimiento' => 'required|date|before_or_equal:today',
            'foto' => ImagenCatalogoService::REGLA,
            'eliminar_imagen' => 'sometimes|boolean',
        ], [
            'prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'before_or_equal' => 'El campo :attribute no puede ser una fecha futura.',
            'image' => 'La foto debe ser una imagen.',
            'mimes' => 'La foto debe ser JPG, JPEG, PNG o WEBP.',
            'foto.max' => 'La foto no debe superar 5 MB.',
            'boolean' => 'Indica si deseas quitar la imagen.',
        ]);

        $datos = $imagenes->aplicar($request, $datos, 'foto', 'beesocc/jugadores');

        $jugador = $request->user()->jugadores()->create($datos);

        return response()->json([
            'message' => 'Jugador creado correctamente.',
            'jugador' => $jugador,
        ], 201);
    }

    public function show(Jugador $jugador): JsonResponse
    {
        Gate::authorize('usar', $jugador);

        return response()->json($jugador);
    }

    public function update(Request $request, Jugador $jugador, ImagenCatalogoService $imagenes): JsonResponse
    {
        Gate::authorize('update', $jugador);
        // Solo se actualizan los campos enviados y validados.
        $datos = $request->validate([
            'administrador_id' => 'prohibited',
            'user_id' => 'prohibited',
            'nombre' => 'sometimes|required|string|max:100',
            'apellido' => 'sometimes|required|string|max:100',
            'fecha_nacimiento' => 'sometimes|required|date|before_or_equal:today',
            'foto' => ImagenCatalogoService::REGLA,
            'eliminar_imagen' => 'sometimes|boolean',
        ], [
            'prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'before_or_equal' => 'El campo :attribute no puede ser una fecha futura.',
            'image' => 'La foto debe ser una imagen.',
            'mimes' => 'La foto debe ser JPG, JPEG, PNG o WEBP.',
            'foto.max' => 'La foto no debe superar 5 MB.',
            'boolean' => 'Indica si deseas quitar la imagen.',
        ]);

        $datos = $imagenes->aplicar($request, $datos, 'foto', 'beesocc/jugadores');

        $jugador->update($datos);

        return response()->json([
            'message' => 'Jugador actualizado correctamente.',
            'jugador' => $jugador,
        ]);
    }

    public function destroy(Jugador $jugador): JsonResponse
    {
        Gate::authorize('delete', $jugador);
        if ($jugador->plantillas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el jugador porque tiene inscripciones en plantillas.',
            ], 409);
        }

        $jugador->delete();

        return response()->json(['message' => 'Jugador eliminado correctamente.']);
    }
}
