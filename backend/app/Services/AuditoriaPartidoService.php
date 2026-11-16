<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\EventoEstadistico;
use App\Models\Partido;

class AuditoriaPartidoService
{
    public function capturar(Partido $partido): array
    {
        // Los eventos conservan sus IDs y valores, incluso si luego se retiran del acta.
        return $partido->only([
            'id',
            'campeonato_id',
            'jornada',
            'fecha_hora',
            'lugar',
            'estado',
            'tipo_resultado',
            'oficializado_at',
            'administrativo_beneficiado_id',
            'administrativo_sancionado_id',
            'administrativo_por_id',
            'motivo_administrativo',
        ]) + [
            'participantes' => $partido->participantes()->orderBy('id')->get(['id', 'rol', 'campeonato_equipo_id'])->toArray(),
            'eventos' => EventoEstadistico::whereHas('equipoPartido', fn ($q) => $q->where('partido_id', $partido->id))
                ->orderBy('id')->get()->map->only(['id', 'equipo_partido_id', 'plantilla_id', 'tipo', 'minuto', 'gol_relacionado_id'])->all(),
        ];
    }

    public function registrar(Partido $partido, string $accion, string $motivo, ?array $anterior, ?array $posterior): AuditoriaCambio
    {
        return AuditoriaCambio::create([
            'campeonato_id' => $partido->campeonato_id, 'usuario_id' => auth()->id(),
            'entidad_tipo' => 'partido', 'entidad_id' => $partido->id,
            'accion' => $accion, 'motivo' => $motivo,
            'anterior_json' => $anterior, 'posterior_json' => $posterior, 'fecha' => now(),
        ]);
    }
}
