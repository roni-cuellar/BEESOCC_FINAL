<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\Campeonato;

class IntegridadCampeonatoService
{
    // Invocar dentro de una transacción: campeonato primero, después sus registros.
    public function bloquear(int $id, bool $planificacion = false): Campeonato
    {
        $campeonato = Campeonato::whereKey($id)->lockForUpdate()->firstOrFail();
        abort_if($campeonato->estado === 'finalizado', 409, 'El campeonato está finalizado y no admite cambios deportivos.');
        abort_if($planificacion && $campeonato->estado !== 'planificacion', 409, 'Las plantillas y la organización ordinaria se cierran al iniciar el campeonato.');

        return $campeonato;
    }

    public function auditar(Campeonato $campeonato, string $tipo, int $id, string $accion, ?array $antes, array $despues): void
    {
        AuditoriaCambio::create(['campeonato_id' => $campeonato->id, 'usuario_id' => auth()->id(),
            'entidad_tipo' => $tipo, 'entidad_id' => $id, 'accion' => $accion,
            'motivo' => 'Operación autorizada de integridad y ciclo del campeonato.',
            'anterior_json' => $antes, 'posterior_json' => $despues, 'fecha' => now()]);
    }
}
