<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditoriaCambio;
use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Llave;
use App\Services\AvanceEliminacionService;
use App\Services\CampeonCampeonatoService;
use App\Services\CrucesGruposService;
use App\Services\DistribucionGruposService;
use App\Services\GeneracionEliminacionService;
use App\Services\MarcadorService;
use App\Services\SiembraEliminacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FaseController extends Controller
{
    private function validarCruces(Request $request, bool $confirmar): array
    {
        return $request->validate([
            'metodo' => 'required|in:manual,automatico', 'rondas' => 'required|array|list|min:1|max:15',
            'rondas.*' => 'required|array:modalidad,desempate', 'rondas.*.modalidad' => 'required|in:unico,ida_vuelta',
            'rondas.*.desempate' => 'required|in:penales,prorroga_penales',
            'sorteo' => ($confirmar ? 'required' : 'sometimes').'|array|list|min:2|max:32768', 'sorteo.*' => 'required|string',
            'confirmacion_sorteo' => 'required_with:sorteo|string|size:64',
            'cruces' => ($confirmar ? 'required' : 'sometimes').'|array|list|min:1|max:16384',
            'cruces.*' => 'required|array|list|size:2', 'cruces.*.*' => 'nullable|string',
            'confirmacion' => $confirmar ? 'required|string|size:64' : 'prohibited',
            'fase_id' => 'prohibited', 'campeonato_id' => 'prohibited', 'estado' => 'prohibited',
        ]);
    }

    public function previsualizarDesdeGrupos(Request $request, Fase $fase, CrucesGruposService $servicio): JsonResponse
    {
        Gate::authorize('update', $fase);

        return response()->json($servicio->previsualizar($fase, $this->validarCruces($request, false)));
    }

    public function generarDesdeGrupos(Request $request, Fase $fase, CrucesGruposService $servicio): JsonResponse
    {
        Gate::authorize('update', $fase);

        return response()->json(['fase' => $servicio->generar($fase, $this->validarCruces($request, true))], 201);
    }

    public function index(Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('verFases', $campeonato);

        return response()->json($campeonato->fases()->get());
    }

    public function store(Request $request, Campeonato $campeonato, GeneracionEliminacionService $servicio): JsonResponse
    {
        Gate::authorize('update', $campeonato);
        $tipoPermitido = $campeonato->formato === 'grupos_eliminacion' ? 'grupos' : 'eliminacion';
        $datos = $request->validate(['nombre' => 'required|string|max:80', 'tipo' => 'required|in:'.$tipoPermitido,
            'campeonato_id' => 'prohibited', 'estado' => 'prohibited', 'orden' => 'prohibited']);

        $fase = $datos['tipo'] === 'grupos'
            ? app(DistribucionGruposService::class)->crearFase($campeonato, $datos['nombre'])
            : $servicio->crearFase($campeonato, $datos['nombre']);

        return response()->json(['fase' => $fase], 201);
    }

    public function show(Fase $fase): JsonResponse
    {
        Gate::authorize('view', $fase);

        if ($fase->tipo === 'grupos') {
            return response()->json($fase->load('grupos.equipos.inscripcion.equipo', 'grupos.partidos.participantes', 'equipos.inscripcion.equipo'));
        }

        $fase->load('equipos.inscripcion.equipo', 'rondas.llaves.plazas', 'rondas.llaves.partidos.participantes');
        if ($fase->tipo === 'eliminacion') {
            $fase->setAttribute('campeon', app(CampeonCampeonatoService::class)->consultar($fase->campeonato));
            $marcador = app(MarcadorService::class);
            foreach ($fase->rondas as $ronda) {
                foreach ($ronda->llaves as $llave) {
                    $llave->setAttribute('marcador_global', $marcador->globalOficial($llave, $ronda->modalidad));
                }
            }
        }

        return response()->json($fase);
    }

    public function previsualizar(Request $request, Fase $fase, SiembraEliminacionService $servicio): JsonResponse
    {
        Gate::authorize('update', $fase);

        return response()->json($servicio->previsualizar($fase, $this->validarCuadro($request)));
    }

    public function generar(Request $request, Fase $fase, GeneracionEliminacionService $servicio): JsonResponse
    {
        Gate::authorize('update', $fase);
        $datos = $this->validarCuadro($request);
        $datos += $request->validate(['confirmacion' => 'required|string|size:64']);

        return response()->json(['fase' => $servicio->generar($fase, $datos)], 201);
    }

    private function validarCuadro(Request $request): array
    {
        return $request->validate(['siembra' => 'required|array|list|min:2|max:32768', 'siembra.*' => 'required|integer|distinct',
            'rondas' => 'required|array|list|min:1|max:15', 'rondas.*' => 'required|array:modalidad,desempate',
            'rondas.*.modalidad' => 'required|in:unico,ida_vuelta', 'rondas.*.desempate' => 'required|in:penales,prorroga_penales',
            'fase_id' => 'prohibited', 'estado' => 'prohibited', 'ganador_fase_equipo_id' => 'prohibited']);
    }

    public function avanzar(Request $request, Llave $llave, AvanceEliminacionService $servicio): JsonResponse
    {
        Gate::authorize('avanzar', $llave);
        $request->validate(['ganador_fase_equipo_id' => 'prohibited', 'estado' => 'prohibited', 'metodo_resolucion' => 'prohibited']);

        return response()->json(['llave' => $servicio->avanzar($llave)]);
    }

    public function penales(Request $request, Llave $llave, AvanceEliminacionService $servicio): JsonResponse
    {
        Gate::authorize('registrarPenales', $llave);
        $datos = $request->validate(['penales_plaza_1' => 'required|integer|between:0,255',
            'penales_plaza_2' => 'required|integer|between:0,255|different:penales_plaza_1',
            'motivo' => 'required|string|max:1000', 'ganador_fase_equipo_id' => 'prohibited', 'estado' => 'prohibited']);

        return response()->json(['llave' => $servicio->registrarPenales($llave, $datos)]);
    }

    public function auditoria(Fase $fase): JsonResponse
    {
        Gate::authorize('view', $fase);
        $llaves = Llave::whereHas('ronda', fn ($q) => $q->where('fase_id', $fase->id))->pluck('id');

        return response()->json(AuditoriaCambio::where('campeonato_id', $fase->campeonato_id)->where(function ($q) use ($fase, $llaves) {
            $q->where(fn ($q) => $q->where('entidad_tipo', 'fase')->where('entidad_id', $fase->id))
                ->orWhere(fn ($q) => $q->where('entidad_tipo', 'llave')->whereIn('entidad_id', $llaves));
        })->orderBy('id')->get());
    }
}
