<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Services\TablaPosicionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TablaPosicionesController extends Controller
{
    public function index(Campeonato $campeonato, TablaPosicionesService $tabla): JsonResponse
    {
        Gate::authorize('view', $campeonato);

        return $tabla->index($campeonato);
    }
}
