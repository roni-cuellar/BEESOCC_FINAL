<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditoriaCambio;
use App\Models\AutorizacionCorreccion;
use App\Models\Partido;
use App\Services\CorreccionPartidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CorreccionPartidoController extends Controller
{
    public function index(Partido $partido): JsonResponse
    {
        Gate::authorize('view', $partido);

        return response()->json($partido->correcciones()->orderByDesc('id')->get());
    }

    public function auditoria(Partido $partido): JsonResponse
    {
        Gate::authorize('view', $partido);

        return response()->json(AuditoriaCambio::where('campeonato_id', $partido->campeonato_id)
            ->where('entidad_tipo', 'partido')->where('entidad_id', $partido->id)->orderBy('id')->get());
    }

    public function store(Request $request, Partido $partido, CorreccionPartidoService $servicio): JsonResponse
    {
        Gate::authorize('arbitrar', $partido);
        $datos = $request->validate([
            'motivo' => 'required|string|max:1000', 'alcance' => 'required|in:eventos',
            'partido_id' => 'prohibited', 'solicitado_por_id' => 'prohibited', 'autorizado_por_id' => 'prohibited',
            'estado' => 'prohibited', 'fecha_resolucion' => 'prohibited', 'fecha_consumo' => 'prohibited',
        ]);

        return response()->json(['correccion' => $servicio->solicitar($partido, $datos)], 201);
    }

    public function resolver(Request $request, AutorizacionCorreccion $correccion, CorreccionPartidoService $servicio): JsonResponse
    {
        Gate::authorize('resolver', $correccion);
        $datos = $request->validate(['estado' => 'required|in:aprobada,rechazada',
            'autorizado_por_id' => 'prohibited', 'solicitado_por_id' => 'prohibited', 'partido_id' => 'prohibited',
            'motivo' => 'prohibited', 'alcance' => 'prohibited', 'fecha_resolucion' => 'prohibited', 'fecha_consumo' => 'prohibited']);

        return response()->json(['correccion' => $servicio->resolver($correccion, $datos['estado'])]);
    }

    public function aplicar(Request $request, AutorizacionCorreccion $correccion, CorreccionPartidoService $servicio): JsonResponse
    {
        Gate::authorize('aplicar', $correccion);
        $datos = $request->validate([
            'operaciones' => 'required|array|min:1|max:100',
            'operaciones.*' => 'required|array:accion,datos,evento_id,minuto',
            'operaciones.*.accion' => 'required|in:registrar,corregir_minuto,eliminar',
            'operaciones.*.datos' => 'required_if:operaciones.*.accion,registrar|array',
            'operaciones.*.evento_id' => 'required_unless:operaciones.*.accion,registrar|integer',
            'operaciones.*.minuto' => 'required_if:operaciones.*.accion,corregir_minuto|integer|between:0,150',
            'estado' => 'prohibited', 'partido_id' => 'prohibited', 'fecha_consumo' => 'prohibited',
        ]);

        return response()->json(['correccion' => $servicio->aplicar($correccion, $datos['operaciones'])]);
    }
}
