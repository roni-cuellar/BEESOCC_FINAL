<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jugador;
use Cloudinary\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JugadorController extends Controller
{
    public function index(): JsonResponse
    {
        $jugadores = Jugador::latest()->orderByDesc('id')->get();

        return response()->json($jugadores);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Jugador::class);
        $datos = $request->validate([
            'administrador_id' => 'prohibited',
            'user_id' => 'prohibited',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'fecha_nacimiento' => 'required|date|before_or_equal:today',
            'foto' => 'nullable|string|max:255',
        ], [
            'prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'before_or_equal' => 'El campo :attribute no puede ser una fecha futura.',
        ]);

        // Si enviaron una fotografía
        if ($request->hasFile('foto')) {

            $file = $request->file('foto');

            // Configurar Cloudinary
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => config('services.cloudinary.cloud_name'),
                    'api_key' => config('services.cloudinary.api_key'),
                    'api_secret' => config('services.cloudinary.api_secret'),
                ],
                'url' => ['secure' => true],
            ]);

            // Subir fotografía
            $resultado = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => 'beesocc/jugadores',
                    'resource_type' => 'image',
                ]
            );

            // Guardar URL en los datos del jugador
            $datos['foto'] = $resultado['secure_url'];
        }

        $jugador = $request->user()->jugadores()->create($datos);

        return response()->json([
            'message' => 'Jugador creado correctamente.',
            'jugador' => $jugador,
        ], 201);
    }

    public function show(Jugador $jugador): JsonResponse
    {
        return response()->json($jugador);
    }

    public function update(Request $request, Jugador $jugador): JsonResponse
    {
        Gate::authorize('update', $jugador);
        // Solo se actualizan los campos enviados y validados.
        $datos = $request->validate([
            'administrador_id' => 'prohibited',
            'user_id' => 'prohibited',
            'nombre' => 'sometimes|required|string|max:100',
            'apellido' => 'sometimes|required|string|max:100',
            'fecha_nacimiento' => 'sometimes|required|date|before_or_equal:today',
            'foto' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:255',
        ], [
            'prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'before_or_equal' => 'El campo :attribute no puede ser una fecha futura.',
        ]);

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
