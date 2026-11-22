<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Partido;
use App\Services\AuditoriaPartidoService;
use App\Services\ControlPartidoService;
use App\Services\PanelArbitralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function panel(Partido $partido, PanelArbitralService $panel): JsonResponse
    {
        Gate::authorize('arbitrar', $partido);

        return response()->json($panel->consultar($partido))->header('Cache-Control', 'no-store');
    }

    public function preparar(Request $request, Partido $partido, PanelArbitralService $panel): JsonResponse
    {
        Gate::authorize('arbitrar', $partido);
        $datos = $request->validate([
            'version' => 'required|integer|min:0',
            'duracion_minutos' => 'required|integer|between:1,150',
            'titulares' => 'required|array:local,visitante',
            'titulares.local' => 'present|array|list',
            'titulares.visitante' => 'present|array|list',
            'titulares.*.*' => 'required|integer|min:1|distinct',
        ]);

        return DB::transaction(function () use ($partido, $panel, $datos) {
            $partido = app(ControlPartidoService::class)->bloquearActa($partido, false);
            $auditoria = app(AuditoriaPartidoService::class);
            $antes = $auditoria->capturar($partido);
            $panel->preparar($partido, $datos);
            $auditoria->registrar($partido, 'preparar_panel', 'Selección arbitral de titulares y duración.', $antes, $auditoria->capturar($partido));

            return $this->panel($partido, $panel);
        });
    }

    public function reloj(Request $request, Partido $partido, PanelArbitralService $panel): JsonResponse
    {
        Gate::authorize('arbitrar', $partido);
        $datos = $request->validate(['accion' => 'required|in:pausar,reanudar', 'version' => 'required|integer|min:0']);

        return DB::transaction(function () use ($partido, $panel, $datos) {
            $partido = app(ControlPartidoService::class)->bloquearActa($partido);
            $auditoria = app(AuditoriaPartidoService::class);
            $antes = $auditoria->capturar($partido);
            $panel->moverReloj($partido, $datos['accion'], $datos['version']);
            $auditoria->registrar($partido, $datos['accion'].'_reloj', 'Control arbitral del cronómetro.', $antes, $auditoria->capturar($partido));

            return $this->panel($partido, $panel);
        });
    }
}
