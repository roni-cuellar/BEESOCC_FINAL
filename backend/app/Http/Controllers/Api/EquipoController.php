<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipo;
use Cloudinary\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EquipoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $equipos = $request->user()->equipos()->latest()->orderByDesc('id')->get();

        return response()->json($equipos);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Equipo::class);

        // Validar los datos
        $datos = $request->validate([
            'administrador_id' => 'prohibited',
            'user_id' => 'prohibited',
            'nombre' => 'required|string|max:150',
            'ciudad' => 'required|string|max:100',
            'escudo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'image' => 'El campo :attribute debe ser una imagen.',
            'mimes' => 'El escudo debe ser JPG, JPEG, PNG o WEBP.',
            'max' => 'El campo :attribute supera el tamaño permitido.',
        ]);

        // Si el usuario envía un escudo
        if ($request->hasFile('escudo')) {

            // Obtener archivo
            $file = $request->file('escudo');

            // Configurar Cloudinary
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => config('services.cloudinary.cloud_name'),
                    'api_key' => config('services.cloudinary.api_key'),
                    'api_secret' => config('services.cloudinary.api_secret'),
                ],
                'url' => ['secure' => true],
            ]);

            // Subir imagen a Cloudinary
            $resultado = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => 'beesocc/equipos',
                    'resource_type' => 'image',
                ]
            );

            // Guardar la URL recibida
            $datos['escudo'] = $resultado['secure_url'];

        } else {

            // Si no hay imagen, guardar null
            $datos['escudo'] = null;
        }

        // Registrar el equipo en MySQL
        $equipo = $request->user()->equipos()->create($datos);

        return response()->json([
            'message' => 'Equipo creado correctamente.',
            'equipo' => $equipo,
        ], 201);
    }

    public function show(Equipo $equipo): JsonResponse
    {
        Gate::authorize('usar', $equipo);

        return response()->json($equipo);
    }

    public function update(Request $request, Equipo $equipo): JsonResponse
    {
        Gate::authorize('update', $equipo);
        // Solo se actualizan los campos enviados y validados.
        $datos = $request->validate([
            'administrador_id' => 'prohibited',
            'user_id' => 'prohibited',
            'nombre' => 'sometimes|required|string|max:150',
            'ciudad' => 'sometimes|required|string|max:100',
            'escudo' => 'sometimes|nullable|string|max:255',
        ], [
            'prohibited' => 'El propietario lo asigna el servidor y no se puede modificar.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
        ]);

        $equipo->update($datos);

        return response()->json([
            'message' => 'Equipo actualizado correctamente.',
            'equipo' => $equipo,
        ]);
    }

    public function destroy(Equipo $equipo): JsonResponse
    {
        Gate::authorize('delete', $equipo);
        if ($equipo->inscripcionesCampeonatos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el equipo porque tiene inscripciones en campeonatos.',
            ], 409);
        }

        $equipo->delete();

        return response()->json(['message' => 'Equipo eliminado correctamente.']);
    }
}
