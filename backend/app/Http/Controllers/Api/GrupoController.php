<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\GrupoDesempate;
use App\Services\ClasificacionGruposService;
use App\Services\DesempateGrupoService;
use App\Services\DistribucionGruposService;
use App\Services\TablaGruposService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GrupoController extends Controller
{
    public function desempates(Grupo $grupo): JsonResponse
    {
        Gate::authorize('view', $grupo);

        return response()->json(GrupoDesempate::where('grupo_id', $grupo->id)->with('partidos.participantes', 'llave')->get());
    }

    public function previsualizarDesempate(Request $request, Grupo $grupo, DesempateGrupoService $servicio): JsonResponse
    {
        Gate::authorize('update', $grupo->fase);
        $datos = $request->validate(['clave' => 'required|string|size:64']);

        return response()->json($servicio->previsualizar($grupo, $datos['clave']));
    }

    public function generarDesempate(Request $request, Grupo $grupo, DesempateGrupoService $servicio): JsonResponse
    {
        Gate::authorize('update', $grupo->fase);
        $datos = $request->validate(['clave' => 'required|string|size:64', 'confirmacion' => 'required|string|size:64']);

        return response()->json(['desempate' => $servicio->generar($grupo, $datos['clave'], $datos['confirmacion'])], 201);
    }

    private function validarDistribucion(Request $request, bool $confirmar = false): array
    {
        return $request->validate([
            'metodo' => 'required|in:manual,automatico', 'cantidad_grupos' => 'required|integer|between:1,65535',
            'clasificados_por_grupo' => 'required|integer|between:1,255',
            'grupos' => ($confirmar ? 'required' : 'sometimes').'|array|list|min:1|max:65535',
            'grupos.*' => 'required|array|list|min:1', 'grupos.*.*' => 'required|integer',
            'confirmacion' => $confirmar ? 'required|string|size:64' : 'prohibited',
            'fase_id' => 'prohibited', 'campeonato_id' => 'prohibited', 'estado' => 'prohibited',
        ]);
    }

    public function previsualizar(Request $request, Fase $fase, DistribucionGruposService $servicio): JsonResponse
    {
        Gate::authorize('update', $fase);

        return response()->json($servicio->previsualizar($fase, $this->validarDistribucion($request)));
    }

    public function confirmar(Request $request, Fase $fase, DistribucionGruposService $servicio): JsonResponse
    {
        Gate::authorize('update', $fase);

        return response()->json(['fase' => $servicio->confirmar($fase, $this->validarDistribucion($request, true))], 201);
    }

    public function calendario(Request $request, Fase $fase, DistribucionGruposService $servicio): JsonResponse
    {
        Gate::authorize('update', $fase);
        $request->validate(['vueltas' => 'prohibited', 'numero_vueltas' => 'prohibited', 'estado' => 'prohibited']);

        return response()->json($servicio->generarCalendario($fase), 201);
    }

    public function tabla(Request $request, Grupo $grupo, TablaGruposService $servicio): JsonResponse
    {
        Gate::authorize('view', $grupo);
        $datos = $request->validate(['vista' => 'sometimes|in:provisional,oficial']);

        return response()->json($servicio->calcular($grupo, ($datos['vista'] ?? 'provisional') === 'oficial'));
    }

    public function clasificacion(Fase $fase, ClasificacionGruposService $servicio): JsonResponse
    {
        Gate::authorize('view', $fase);

        return response()->json($servicio->preparar($fase));
    }

    public function clasificar(Request $request, Fase $fase, ClasificacionGruposService $servicio): JsonResponse
    {
        Gate::authorize('update', $fase);
        $datos = $request->validate(['confirmacion' => 'required|string|size:64', 'clasificados' => 'prohibited',
            'ganador_fase_equipo_id' => 'prohibited', 'estado' => 'prohibited']);

        return response()->json($servicio->clasificar($fase, $datos['confirmacion']));
    }
}
