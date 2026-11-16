<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditoriaCambio;
use App\Models\Campeonato;
use App\Services\CicloCampeonatoService;
use App\Services\IntegridadCampeonatoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CicloCampeonatoController extends Controller
{
    public function requisitos(Request $request, Campeonato $campeonato)
    {
        Gate::authorize('update', $campeonato);
        $datos = $request->validate(['modalidad' => 'required|string|max:50', 'minimo_jugadores' => 'required|integer|min:1|max:99']);
        abort_if(trim($datos['modalidad']) === '', 422, 'Indica la modalidad del reglamento.');

        return DB::transaction(function () use ($campeonato, $datos) {
            $integridad = app(IntegridadCampeonatoService::class);
            $c = $integridad->bloquear($campeonato->id, true);
            $config = $c->configuracion()->firstOrNew([], ['numero_vueltas' => 1, 'orden_desempate' => 'puntos,diferencia_goles,goles_favor']);
            $antes = $config->exists ? $config->attributesToArray() : null;
            $config->fill($datos)->save();
            $integridad->auditar($c, 'configuracion', $config->id, 'configurar_requisitos_inicio', $antes, $config->attributesToArray());

            return response()->json(['configuracion' => $config]);
        });
    }

    public function iniciar(Request $request, Campeonato $campeonato, CicloCampeonatoService $ciclo)
    {
        Gate::authorize('update', $campeonato);
        $request->validate(['estado' => 'prohibited', 'campeon_campeonato_equipo_id' => 'prohibited']);

        return response()->json(['campeonato' => $ciclo->iniciar($campeonato)]);
    }

    public function finalizar(Request $request, Campeonato $campeonato, CicloCampeonatoService $ciclo)
    {
        Gate::authorize('update', $campeonato);
        $request->validate(['estado' => 'prohibited', 'campeon_campeonato_equipo_id' => 'prohibited']);

        return response()->json($ciclo->finalizar($campeonato));
    }

    public function auditoria(Campeonato $campeonato)
    {
        Gate::authorize('view', $campeonato);

        return response()->json(AuditoriaCambio::where('campeonato_id', $campeonato->id)->whereIn('accion', ['configurar_requisitos_inicio', 'iniciar_campeonato', 'finalizar_campeonato', 'crear_plantilla', 'actualizar_plantilla', 'eliminar_plantilla', 'estado_inscripcion'])->orderBy('id')->get());
    }
}
