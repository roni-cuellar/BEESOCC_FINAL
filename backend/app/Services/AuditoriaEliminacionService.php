<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\Fase;
use App\Models\Llave;

class AuditoriaEliminacionService
{
    public function capturar(Llave $llave): array
    {
        return $llave->attributesToArray() + ['plazas' => $llave->plazas()->get()->toArray(),
            'partidos' => $llave->partidos()->with('participantes')->get()->map(fn ($p) => app(AuditoriaPartidoService::class)->capturar($p))->all()];
    }

    public function registrar(Fase $fase, string $tipo, int $id, string $accion, string $motivo, ?array $antes, array $despues): void
    {
        AuditoriaCambio::create(['campeonato_id' => $fase->campeonato_id, 'usuario_id' => auth()->id(),
            'entidad_tipo' => $tipo, 'entidad_id' => $id, 'accion' => $accion, 'motivo' => $motivo,
            'anterior_json' => $antes, 'posterior_json' => $despues, 'fecha' => now()]);
    }
}
