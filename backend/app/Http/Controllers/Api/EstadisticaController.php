<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Services\EstadisticaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EstadisticaController extends Controller
{
    public function goleadores(Campeonato $campeonato, EstadisticaService $estadistica): JsonResponse
    {
        Gate::authorize('view', $campeonato);

        return $estadistica->goleadores($campeonato);
    }

    public function asistencias(Campeonato $campeonato, EstadisticaService $estadistica): JsonResponse
    {
        Gate::authorize('view', $campeonato);

        return $estadistica->asistencias($campeonato);
    }

    public function amarillas(Campeonato $campeonato, EstadisticaService $estadistica): JsonResponse
    {
        Gate::authorize('view', $campeonato);

        return $estadistica->amarillas($campeonato);
    }

    public function rojas(Campeonato $campeonato, EstadisticaService $estadistica): JsonResponse
    {
        Gate::authorize('view', $campeonato);

        return $estadistica->rojas($campeonato);
    }

    public function faltas(Campeonato $campeonato, EstadisticaService $estadistica): JsonResponse
    {
        Gate::authorize('view', $campeonato);

        return $estadistica->faltas($campeonato);
    }

    public function equipos(Campeonato $campeonato, EstadisticaService $estadistica): JsonResponse
    {
        Gate::authorize('view', $campeonato);

        return $estadistica->equipos($campeonato);
    }
}
