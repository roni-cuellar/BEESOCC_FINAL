<?php

namespace App\Services;

use App\Models\AutorizacionCorreccion;
use App\Models\Campeonato;
use App\Models\EventoEstadistico;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CorreccionPartidoService
{
    public function solicitar(Partido $partido, array $datos): AutorizacionCorreccion
    {
        return DB::transaction(function () use ($partido, $datos) {
            $partido = app(ControlPartidoService::class)->bloquearActa($partido);
            $this->comprobarOficial($partido);
            $correccion = new AutorizacionCorreccion($datos);
            $correccion->partido_id = $partido->id;
            $correccion->solicitado_por_id = auth()->id();
            $correccion->estado = 'solicitada';
            $correccion->save();
            app(AuditoriaPartidoService::class)->registrar($partido, 'solicitar_correccion',
                $correccion->motivo, null, ['correccion' => $correccion->attributesToArray()]);

            return $correccion->unsetRelation('partido');
        });
    }

    public function resolver(AutorizacionCorreccion $correccion, string $estado): AutorizacionCorreccion
    {
        return DB::transaction(function () use ($correccion, $estado) {
            [$partido, $correccion] = $this->bloquear($correccion);
            Gate::authorize('resolver', $correccion);
            $this->comprobarOficial($partido);
            abort_unless($correccion->estado === 'solicitada', 409, 'La solicitud ya fue resuelta.');
            $anterior = $correccion->attributesToArray();
            $correccion->estado = $estado;
            $correccion->autorizado_por_id = auth()->id();
            $correccion->fecha_resolucion = now();
            $correccion->save();
            app(AuditoriaPartidoService::class)->registrar($partido, 'resolver_correccion',
                $correccion->motivo, ['correccion' => $anterior], ['correccion' => $correccion->attributesToArray()]);

            return $correccion->unsetRelation('partido');
        });
    }

    public function aplicar(AutorizacionCorreccion $correccion, array $operaciones): AutorizacionCorreccion
    {
        return DB::transaction(function () use ($correccion, $operaciones) {
            [$partido, $correccion] = $this->bloquear($correccion);
            Gate::authorize('aplicar', $correccion);
            $this->comprobarOficial($partido);
            abort_unless($correccion->estado === 'aprobada' && $correccion->alcance === 'eventos',
                409, 'La corrección no está aprobada para modificar eventos o ya fue consumida.');
            $auditoria = app(AuditoriaPartidoService::class);
            $anterior = $auditoria->capturar($partido) + ['correccion' => $correccion->attributesToArray()];
            foreach ($operaciones as $operacion) {
                $this->aplicarOperacion($partido, $operacion);
            }
            app(ResultadoOficialService::class)->comprobarEventos($partido);
            // Eventos, consumo y bitácora se confirman juntos; un fallo revierte todo.
            $correccion->estado = 'consumida';
            $correccion->fecha_consumo = now();
            $correccion->save();
            $auditoria->registrar($partido, 'aplicar_correccion', $correccion->motivo,
                $anterior, $auditoria->capturar($partido) + ['correccion' => $correccion->attributesToArray()]);

            return $correccion->unsetRelation('partido');
        });
    }

    private function aplicarOperacion(Partido $partido, array $operacion): void
    {
        $eventos = app(EventoEstadisticoService::class);
        if ($operacion['accion'] === 'registrar') {
            $eventos->registrar($partido, $eventos->validar($operacion['datos']));

            return;
        }
        $evento = EventoEstadistico::whereKey($operacion['evento_id'])->lockForUpdate()->firstOrFail();
        if ($operacion['accion'] === 'corregir_minuto') {
            $eventos->corregir($partido, $evento, $operacion['minuto']);
        } else {
            $eventos->eliminar($partido, $evento);
        }
    }

    private function bloquear(AutorizacionCorreccion $correccion): array
    {
        // Mismo orden que la revocación arbitral: campeonato, partido y autorización.
        $campeonato = Campeonato::whereKey($correccion->partido->campeonato_id)->lockForUpdate()->firstOrFail();
        $partido = Partido::whereKey($correccion->partido_id)->lockForUpdate()->firstOrFail();
        $partido->setRelation('campeonato', $campeonato);
        $correccion = AutorizacionCorreccion::whereKey($correccion->id)->lockForUpdate()->firstOrFail();
        $correccion->setRelation('partido', $partido);

        return [$partido, $correccion];
    }

    private function comprobarOficial(Partido $partido): void
    {
        abort_unless($partido->estado === 'finalizado' && $partido->oficializado_at,
            409, 'Se requiere un resultado oficial finalizado; no se oficializan históricos automáticamente.');
        abort_unless($partido->tipo_resultado === 'deportivo', 409, 'Solo se habilitan correcciones de eventos deportivos.');
    }
}
