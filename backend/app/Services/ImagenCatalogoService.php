<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImagenCatalogoService
{
    public const REGLA = 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:5120';

    public function aplicar(Request $request, array $datos, string $campo, string $carpeta): array
    {
        // NULL u omisión conservan la referencia; solo la acción explícita la quita.
        unset($datos[$campo], $datos['eliminar_imagen']);
        abort_if($request->boolean('eliminar_imagen') && $request->hasFile($campo), 422, 'Elige reemplazar la imagen o quitarla, no ambas acciones.');
        if ($request->boolean('eliminar_imagen')) {
            $datos[$campo] = null;
        } elseif ($request->hasFile($campo)) {
            try {
                $url = $this->subir($request->file($campo), $carpeta);
                if (! str_starts_with($url, 'https://') || strlen($url) > 255) {
                    throw new \RuntimeException('Referencia de imagen inválida.');
                }
                $datos[$campo] = $url;
            } catch (Throwable $error) {
                // No registrar respuestas del proveedor que puedan contener credenciales.
                Log::warning('No se pudo guardar una imagen del catálogo.');
                throw new HttpResponseException(response()->json(['message' => 'No se pudo subir la imagen. Intenta de nuevo.'], 502));
            }
        }

        return $datos;
    }

    public function subir(UploadedFile $archivo, string $carpeta): string
    {
        $cloudinary = new Cloudinary([
            'cloud' => config('services.cloudinary'),
            'url' => ['secure' => true],
        ]);
        $resultado = $cloudinary->uploadApi()->upload($archivo->getRealPath(), [
            'folder' => $carpeta, 'resource_type' => 'image',
        ]);

        // Reemplazar o quitar una referencia nunca elimina el archivo remoto.
        return $resultado['secure_url'];
    }
}
