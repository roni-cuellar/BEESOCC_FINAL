<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Partido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ArbitrajeController extends Controller
{
    public function partidos(Request $request, Campeonato $campeonato): JsonResponse
    {
        abort_unless($request->user()->esArbitroDe($campeonato), 403);

        return response()->json($campeonato->partidos()->with('participantes.campeonatoEquipo.equipo')
            ->orderBy('jornada')->orderBy('id')->get());
    }

    public function plantillas(Partido $partido): JsonResponse
    {
        Gate::authorize('arbitrar', $partido);
        $plantillas = [];
        foreach ($partido->participantes as $participante) {
            $plantillas[$participante->campeonato_equipo_id] = $participante->campeonatoEquipo->plantillas()
                ->with('jugador:id,nombre,apellido')->get();
        }

        return response()->json($plantillas);
    }
}
