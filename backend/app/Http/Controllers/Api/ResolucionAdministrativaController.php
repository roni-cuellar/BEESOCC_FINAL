<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampeonatoEquipo;
use App\Models\Partido;
use App\Services\ResolucionAdministrativaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResolucionAdministrativaController extends Controller
{
    public function resultado(Request $request, Partido $partido, ResolucionAdministrativaService $servicio)
    {
        Gate::authorize('update', $partido);
        $datos = $request->validate(['motivo' => 'required|string|max:1000', 'beneficiado_id' => 'required|integer', 'sancionado_id' => 'required|integer|different:beneficiado_id', 'autorizacion_id' => 'sometimes|required|integer', 'estado' => 'prohibited', 'tipo_resultado' => 'prohibited', 'goles' => 'prohibited', 'administrativo_por_id' => 'prohibited', 'oficializado_at' => 'prohibited', 'campeonato_id' => 'prohibited']);

        return response()->json(['partido' => $servicio->resolver($partido, $datos)]);
    }

    public function inactivar(Request $request, CampeonatoEquipo $campeonatoEquipo, ResolucionAdministrativaService $servicio)
    {
        Gate::authorize('update', $campeonatoEquipo);
        $datos = $request->validate(['tipo' => 'required|in:retiro,descalificacion', 'motivo' => 'required|string|max:1000', 'estado' => 'prohibited']);

        return response()->json(['inscripcion' => $servicio->inactivar($campeonatoEquipo, $datos)]);
    }
}
