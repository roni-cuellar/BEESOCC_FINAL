<?php

namespace App\Services;

use App\Models\EventoEstadistico;
use App\Models\Partido;

class ResultadoOficialService
{
    public function comprobarEventos(Partido $partido): void
    {
        app(MarcadorService::class)->calcular($partido);
        $servicio = app(EventoEstadisticoService::class);
        $eventos = EventoEstadistico::whereHas('equipoPartido', fn ($q) => $q->where('partido_id', $partido->id))->get();
        foreach ($eventos as $evento) {
            $datos = $servicio->validar($evento->only(['equipo_partido_id', 'plantilla_id', 'tipo', 'minuto', 'gol_relacionado_id']));
            $servicio->comprobar($partido, $datos, $evento->id);
        }
    }

    public function oficializar(Partido $partido): void
    {
        abort_unless($partido->estado === 'en_curso' && ! $partido->oficializado_at,
            409, 'Solo se puede oficializar una vez un partido en curso.');
        abort_unless($partido->tipo_resultado === 'deportivo', 409, 'La resolución administrativa no está habilitada en este sprint.');
        $this->comprobarEventos($partido);
        $auditoria = app(AuditoriaPartidoService::class);
        $anterior = $auditoria->capturar($partido);
        $partido->estado = 'finalizado';
        $partido->oficializado_at = now();
        $partido->save();
        $auditoria->registrar($partido, 'oficializar', 'Finalización arbitral del resultado deportivo.', $anterior, $auditoria->capturar($partido));
    }
}
