<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\AutorizacionCorreccion;
use App\Models\CampeonatoEquipo;
use App\Models\Fase;
use App\Models\FaseEquipo;
use App\Models\Llave;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ResolucionAdministrativaService
{
    public function resolver(Partido $original, array $datos): Partido
    {
        Gate::authorize('update', $original);

        return DB::transaction(function () use ($original, $datos) {
            $c = app(IntegridadCampeonatoService::class)->bloquear($original->campeonato_id);
            abort_unless($c->estado === 'en_curso', 409, 'La decisión requiere un campeonato en curso.');
            $p = Partido::whereKey($original->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $p);
            app(DesempateGrupoService::class)->comprobarVigente($p);
            $correccion = null;
            if ($p->oficializado_at) {
                abort_unless(isset($datos['autorizacion_id']), 409, 'Un oficial requiere autorización de rectificación.');
                $correccion = AutorizacionCorreccion::whereKey($datos['autorizacion_id'])->lockForUpdate()->first();
                abort_unless($correccion && $correccion->partido_id === $p->id && $correccion->alcance === 'resultado' && $correccion->estado === 'aprobada', 409, 'Autorización no vigente para este resultado.');
                Gate::authorize('aplicar', $correccion);
            } else {
                abort_if(isset($datos['autorizacion_id']), 422, 'No se consume autorización sobre un partido no oficial.');
                abort_unless(in_array($p->estado, ['programado', 'en_curso', 'suspendido'], true), 409, 'No se resuelven históricos o partidos cancelados mediante esta operación.');
            }
            $ids = $p->participantes()->pluck('campeonato_equipo_id')->sort()->values()->all();
            $propuestos = [(int) $datos['beneficiado_id'], (int) $datos['sancionado_id']];
            sort($propuestos);
            abort_unless(count($ids) === 2 && $ids === $propuestos && $propuestos[0] !== $propuestos[1], 422, 'Beneficiado y sancionado deben ser los dos participantes distintos.');
            abort_unless(CampeonatoEquipo::whereKey($datos['beneficiado_id'])->where('estado', 'activo')->exists(), 409, 'No se beneficia a un equipo retirado o descalificado. DP-03 exige revisión extraordinaria.');
            abort_if($p->tipo_resultado === 'administrativo' && (int) $p->administrativo_beneficiado_id === (int) $datos['beneficiado_id'], 409, 'La misma decisión ya está registrada.');
            abort_if($p->correcciones()->where('alcance', 'eventos')->where('estado', 'aprobada')->exists(), 409, 'Aplica primero la corrección de eventos aprobada; no se deja una autorización inutilizable.');
            $auditoria = app(AuditoriaPartidoService::class);
            $antes = $auditoria->capturar($p) + ['correccion' => $correccion?->attributesToArray()];
            $clasificacion = $p->grupo_id ? app(ClasificacionGruposService::class)->preparar($p->fase) : null;
            $p->tipo_resultado = 'administrativo';
            $p->administrativo_beneficiado_id = $datos['beneficiado_id'];
            $p->administrativo_sancionado_id = $datos['sancionado_id'];
            $p->administrativo_por_id = auth()->id();
            $p->motivo_administrativo = $datos['motivo'];
            app(PanelArbitralService::class)->alCambiarEstado($p, 'finalizado');
            $p->estado = 'finalizado';
            $p->oficializado_at = now();
            $p->save();
            app(AvanceEliminacionService::class)->revisarCorreccion($p, $datos['motivo']);
            app(ClasificacionGruposService::class)->revisarCorreccion($p, $datos['motivo'], $clasificacion);
            if ($correccion) {
                $correccion->estado = 'consumida';
                $correccion->fecha_consumo = now();
                $correccion->save();
            }
            $auditoria->registrar($p, 'resultado_administrativo', $datos['motivo'], $antes,
                $auditoria->capturar($p) + ['autorizacion_id' => $correccion?->id, 'correccion' => $correccion?->attributesToArray()]);

            return $p->refresh();
        });
    }

    public function inactivar(CampeonatoEquipo $original, array $datos): CampeonatoEquipo
    {
        Gate::authorize('update', $original);

        return DB::transaction(function () use ($original, $datos) {
            $c = app(IntegridadCampeonatoService::class)->bloquear($original->campeonato_id);
            abort_unless($c->estado === 'en_curso', 409, 'Se requiere un campeonato en curso.');
            $e = CampeonatoEquipo::whereKey($original->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $e);
            abort_unless($e->estado === 'activo', 409, 'La inscripción ya está inactiva; no se reabre ordinariamente.');
            $anteriores = [];
            foreach (Fase::where('campeonato_id', $c->id)->where('tipo', 'grupos')->get() as $fase) {
                $vista = app(ClasificacionGruposService::class)->preparar($fase);
                // No sustituir un clasificado oficial sin una regla de vacantes aprobada.
                $cuadroConfirmado = Fase::where('fase_origen_id', $fase->id)->exists();
                abort_if(! $cuadroConfirmado && $vista['clasificacion_vigente'] && collect($vista['candidatos'])->contains('campeonato_equipo_id', $e->id), 409, 'El equipo ya tiene un cupo oficial: la vacante requiere procedimiento extraordinario aprobado.');
                if (! $cuadroConfirmado) {
                    $anteriores[$fase->id] = $vista;
                }
            }
            $antes = $e->attributesToArray();
            $e->estado = $datos['tipo'] === 'retiro' ? 'retirado' : 'descalificado';
            $e->motivo_inactividad = $datos['motivo'];
            $e->inactivado_at = now();
            $e->save();
            $miembros = FaseEquipo::where('campeonato_equipo_id', $e->id)->orderBy('id')->get();
            $miembrosAntes = $miembros->map->only(['id', 'fase_id', 'campeonato_equipo_id', 'estado'])->all();
            foreach ($miembros as $miembro) {
                if ($miembro->fase->tipo !== 'grupos' || ! Fase::where('fase_origen_id', $miembro->fase_id)->exists()) {
                    $miembro->update(['estado' => $e->estado]);
                }
            }
            foreach ($anteriores as $faseId => $vista) {
                $partido = Partido::where('fase_id', $faseId)->whereNotNull('grupo_id')->first();
                if ($partido) {
                    app(ClasificacionGruposService::class)->revisarCorreccion($partido, $datos['motivo'], $vista);
                }
            }
            AuditoriaCambio::create(['campeonato_id' => $c->id, 'usuario_id' => auth()->id(),
                'entidad_tipo' => 'inscripcion', 'entidad_id' => $e->id, 'accion' => 'inactivar_equipo', 'motivo' => $datos['motivo'],
                'anterior_json' => $antes + ['participaciones_fase' => $miembrosAntes],
                'posterior_json' => $e->attributesToArray() + ['participaciones_fase' => $miembros->map->only(['id', 'fase_id', 'campeonato_equipo_id', 'estado'])->all()], 'fecha' => now()]);
            foreach (Llave::where('estado', 'pendiente')->whereHas('plazas.participante', fn ($q) => $q->where('campeonato_equipo_id', $e->id))->get() as $llave) {
                $plazas = $llave->plazas()->with('participante.inscripcion')->get();
                if ($plazas->count() === 2 && $plazas->every(fn ($p) => $p->participante && $p->participante->inscripcion->estado !== 'activo')) {
                    app(AuditoriaEliminacionService::class)->registrar($llave->ronda->fase, 'llave', $llave->id, 'bloquear_doble_inactividad', $datos['motivo'], null,
                        ['ganador_fase_equipo_id' => null, 'bloqueo' => 'DP-03', 'inscripciones' => $plazas->pluck('participante.campeonato_equipo_id')->all()]);
                }
            }

            return $e;
        });
    }
}
