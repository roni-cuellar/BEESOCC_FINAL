<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\User;
use App\Services\AsignacionArbitroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AsignacionArbitroController extends Controller
{
    public function buscarUsuario(Request $request, Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('gestionarArbitros', $campeonato);
        $datos = $request->validate(['email' => 'required|email|max:255'], [
            'email.required' => 'Indica el correo del usuario registrado.',
            'email.email' => 'Indica un correo electrónico válido.',
            'email.max' => 'El correo no debe superar 255 caracteres.',
        ]);
        // Coincidencia exacta: no se ofrece un catálogo de cuentas.
        $usuario = User::where('email', $datos['email'])->whereKeyNot($request->user()->id)
            ->first(['id', 'name']);

        return response()->json(['usuario' => $usuario])->header('Cache-Control', 'no-store');
    }

    public function misArbitrajes(Request $request): JsonResponse
    {
        $asignaciones = $request->user()->asignacionesArbitro()
            ->whereNull('fecha_fin')->where('fecha_inicio', '<=', now())
            ->whereHas('campeonato', fn ($consulta) => $consulta->whereNotNull('administrador_id')
                ->where('administrador_id', '!=', $request->user()->id))
            ->with('campeonato:id,nombre,temporada,estado,formato')->orderByDesc('id')->get()
            ->map(fn ($asignacion) => [
                'id' => $asignacion->id,
                'fecha_inicio' => $asignacion->fecha_inicio,
                'campeonato' => $asignacion->campeonato,
            ]);

        return response()->json(['asignaciones' => $asignaciones])->header('Cache-Control', 'no-store');
    }

    public function vigente(Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('gestionarArbitros', $campeonato);

        return response()->json(['asignacion' => $campeonato->asignacionesArbitro()->whereNull('fecha_fin')
            ->with(['usuario:id,name', 'autorizador:id,name'])->first()]);
    }

    public function historial(Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('gestionarArbitros', $campeonato);

        return response()->json(['asignaciones' => $campeonato->asignacionesArbitro()
            ->with(['usuario:id,name', 'autorizador:id,name'])->orderByDesc('id')->get()]);
    }

    private function validar(Request $request, bool $reemplazo = false): array
    {
        return $request->validate([
            'usuario_id' => 'required|integer|exists:users,id',
            'motivo_revocacion' => $reemplazo ? 'required|string|max:1000' : 'prohibited',
            'campeonato_id' => 'prohibited',
            'autorizado_por' => 'prohibited',
            'fecha_inicio' => 'prohibited',
            'fecha_fin' => 'prohibited',
            'campeonato_activo_id' => 'prohibited',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El usuario debe identificarse con un número entero.',
            'exists' => 'El usuario seleccionado no está registrado.',
            'string' => 'El campo :attribute debe ser un texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'prohibited' => 'No se permite enviar el campo :attribute en esta operación.',
        ]);
    }

    public function store(Request $request, Campeonato $campeonato, AsignacionArbitroService $servicio): JsonResponse
    {
        Gate::authorize('gestionarArbitros', $campeonato);
        $datos = $this->validar($request);
        $asignacion = $servicio->asignar($campeonato, $request->user(), User::findOrFail($datos['usuario_id']));

        return response()->json(['message' => 'Árbitro asignado correctamente.', 'asignacion' => $asignacion], 201);
    }

    public function update(Request $request, Campeonato $campeonato, AsignacionArbitroService $servicio): JsonResponse
    {
        Gate::authorize('gestionarArbitros', $campeonato);
        $datos = $this->validar($request, true);
        $asignacion = $servicio->reemplazar($campeonato, $request->user(), User::findOrFail($datos['usuario_id']), $datos['motivo_revocacion']);

        return response()->json(['message' => 'Árbitro reemplazado correctamente.', 'asignacion' => $asignacion]);
    }

    public function revocar(Request $request, Campeonato $campeonato, AsignacionArbitroService $servicio): JsonResponse
    {
        Gate::authorize('gestionarArbitros', $campeonato);
        $datos = $request->validate(['motivo_revocacion' => 'required|string|max:1000'], [
            'required' => 'Debes indicar el motivo de revocación.',
            'string' => 'El motivo debe ser un texto.',
            'max' => 'El motivo no debe superar :max caracteres.',
        ]);
        $asignacion = $servicio->revocar($campeonato, $request->user(), $datos['motivo_revocacion']);

        return response()->json(['message' => 'Asignación revocada; su historial se conserva.', 'asignacion' => $asignacion]);
    }
}
