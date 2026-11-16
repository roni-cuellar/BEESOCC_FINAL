<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\Fase;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClasificacionGruposService
{
    private function ultimoRegistro(Fase $fase): ?AuditoriaCambio
    {
        return AuditoriaCambio::where('entidad_tipo', 'fase')->where('entidad_id', $fase->id)
            ->whereIn('accion', ['clasificar_grupos', 'actualizar_clasificacion_grupos', 'invalidar_clasificacion_grupos'])->orderByDesc('id')->first();
    }

    public function preparar(Fase $fase): array
    {
        Gate::authorize('view', $fase);
        abort_unless($fase->tipo === 'grupos' && $fase->campeonato->formato === 'grupos_eliminacion', 409, 'Se requiere una fase de grupos.');
        $tablas = $candidatos = $bloqueos = [];
        $grupos = $fase->grupos()->get();
        $cupo = (int) $fase->clasificados_por_grupo;
        if ($grupos->isEmpty() || $cupo < 1 || $grupos->count() * $cupo < 2) {
            $bloqueos[] = 'configuracion_incompleta';
        }
        $miembros = $fase->equipos()->get();
        abort_if($miembros->contains(fn ($m) => ! $grupos->contains('id', $m->grupo_id)), 409, 'Hay participantes sin grupo válido en la fase.');
        foreach ($grupos as $grupo) {
            $tabla = app(TablaGruposService::class)->calcular($grupo, true);
            $tablas[] = $tabla;
            if (! $tabla['resultados_completos']) {
                $bloqueos[] = 'resultados_o_elegibilidad_pendientes_grupo_'.$grupo->id;
            }
            if ($tabla['pendientes_resolucion']) {
                $bloqueos[] = 'desempate_pendiente_grupo_'.$grupo->id;
            }
            if (count($tabla['tabla']) < $cupo) {
                $bloqueos[] = 'cupo_invalido_grupo_'.$grupo->id;
            }
            foreach ($tabla['tabla'] as $fila) {
                if ($fila['posicion'] !== null && $fila['posicion'] <= $cupo) {
                    if (! $fila['elegible_clasificacion']) {
                        $bloqueos[] = 'clasificado_inhabilitado_'.$fila['campeonato_equipo_id'];
                    }
                    $candidatos[] = ['campeonato_equipo_id' => $fila['campeonato_equipo_id'], 'fase_equipo_id' => $fila['fase_equipo_id'],
                        'origen_tipo' => 'grupo_puesto', 'origen_grupo_id' => $grupo->id, 'origen_puesto' => $fila['posicion']];
                }
            }
        }
        $huella = hash('sha256', json_encode([$fase->id, $cupo, array_column($tablas, 'huella')], JSON_THROW_ON_ERROR));
        $ultimo = $this->ultimoRegistro($fase);
        $vigente = ! $bloqueos && in_array($ultimo?->accion, ['clasificar_grupos', 'actualizar_clasificacion_grupos'], true) && ($ultimo->posterior_json['huella'] ?? null) === $huella;
        $eliminacion = Fase::where('fase_origen_id', $fase->id)->first();

        return ['fase_id' => $fase->id, 'clasificados_por_grupo' => $cupo, 'lista_para_clasificar' => ! $bloqueos,
            'clasificacion_vigente' => $vigente, 'huella' => $huella, 'bloqueos' => $bloqueos, 'tablas' => $tablas,
            'candidatos' => $bloqueos ? [] : $candidatos,
            'confirmacion' => $bloqueos ? null : hash_hmac('sha256', $huella, config('app.key')),
            'transicion_eliminacion' => ['habilitada' => $vigente && ! $eliminacion, 'fase_eliminacion_id' => $eliminacion?->id]];
    }

    public function clasificar(Fase $fase, string $confirmacion): array
    {
        return DB::transaction(function () use ($fase, $confirmacion) {
            $fase = app(DistribucionGruposService::class)->bloquear($fase);
            $vista = $this->preparar($fase);
            abort_unless($vista['lista_para_clasificar'], 409, 'Faltan resultados oficiales, elegibilidad o desempates por resolver.');
            abort_if($vista['clasificacion_vigente'], 409, 'La clasificación vigente ya fue registrada.');
            abort_unless(hash_equals($vista['confirmacion'], $confirmacion), 409, 'La clasificación cambió; vuelve a consultar la vista previa.');
            app(DistribucionGruposService::class)->auditar($fase, 'clasificar_grupos', 'Clasificación por cupo uniforme basada en resultados oficiales.', null,
                ['huella' => $vista['huella'], 'clasificados_por_grupo' => $vista['clasificados_por_grupo'], 'clasificados' => $vista['candidatos']]);
            $fase->update(['estado' => 'finalizada']);

            return $this->preparar($fase);
        });
    }

    public function revisarCorreccion(Partido $partido, string $motivo, ?array $anterior): void
    {
        if (! $partido->grupo_id || $anterior === null) {
            return;
        }
        $fase = $partido->fase()->firstOrFail();
        $vista = $this->preparar($fase);
        if ($vista['huella'] === $anterior['huella']) {
            return;
        }
        $usadosAntes = array_merge(...array_column($anterior['tablas'], 'desempates_usados'));
        $usadosAhora = array_merge(...array_column($vista['tablas'], 'desempates_usados'));
        $obsoletos = array_diff($usadosAntes, $usadosAhora);
        abort_if(Partido::whereIn('grupo_desempate_id', $obsoletos)->where(fn ($q) => $q->where('estado', '!=', 'programado')->orWhereNotNull('oficializado_at')->orWhereHas('participantes.eventos'))->exists(),
            409, 'La corrección invalida un desempate ya iniciado.');
        $eliminacion = Fase::where('fase_origen_id', $fase->id)->first();
        if ($eliminacion) {
            app(CrucesGruposService::class)->propagarCorreccion($eliminacion, $vista, $motivo);
            app(DistribucionGruposService::class)->auditar($fase, 'actualizar_clasificacion_grupos', $motivo, $anterior,
                ['huella' => $vista['huella'], 'clasificados_por_grupo' => $vista['clasificados_por_grupo'], 'clasificados' => $vista['candidatos']]);
            $fase->update(['estado' => 'finalizada']);

            return;
        }
        abort_if($fase->campeonato->fases()->where('orden', '>', $fase->orden)->where('tipo', 'eliminacion')->whereHas('rondas')->exists(), 409, 'La fase posterior no tiene procedencia de grupos verificable.');
        $ultimo = $this->ultimoRegistro($fase);
        if (in_array($ultimo?->accion, ['clasificar_grupos', 'actualizar_clasificacion_grupos'], true)) {
            app(DistribucionGruposService::class)->auditar($fase, 'invalidar_clasificacion_grupos', $motivo, $ultimo->posterior_json,
                ['huella' => $vista['huella'], 'clasificacion_vigente' => false]);
            $fase->update(['estado' => 'en_curso']);
        }
    }

    public function actualizarEstado(Fase $fase): void
    {
        $vista = $this->preparar($fase);
        $fase->update(['estado' => $vista['clasificacion_vigente'] ? 'finalizada'
            : ($fase->partidos()->where('estado', '!=', 'programado')->exists() ? 'en_curso' : 'planificacion')]);
    }
}
