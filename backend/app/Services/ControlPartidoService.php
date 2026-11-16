<?php

namespace App\Services;

use App\Models\Partido;
use Illuminate\Support\Facades\Gate;

class ControlPartidoService
{
    public function bloquearActa(Partido $partido): Partido
    {
        // El mismo bloqueo de asignaciones evita escribir con una autorización revocada.
        $campeonato = app(IntegridadCampeonatoService::class)->bloquear($partido->campeonato_id);
        abort_unless($campeonato->estado === 'en_curso', 409, 'El administrador debe iniciar el campeonato antes del acta.');
        $partido = Partido::whereKey($partido->id)->lockForUpdate()->firstOrFail();
        $partido->setRelation('campeonato', $campeonato);
        Gate::authorize('arbitrar', $partido);
        app(DesempateGrupoService::class)->comprobarVigente($partido);

        return $partido;
    }
}
