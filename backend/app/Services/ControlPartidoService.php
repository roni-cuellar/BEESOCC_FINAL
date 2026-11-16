<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Models\Partido;
use Illuminate\Support\Facades\Gate;

class ControlPartidoService
{
    public function bloquearActa(Partido $partido): Partido
    {
        // El mismo bloqueo de asignaciones evita escribir con una autorización revocada.
        $campeonato = Campeonato::whereKey($partido->campeonato_id)->lockForUpdate()->firstOrFail();
        $partido = Partido::whereKey($partido->id)->lockForUpdate()->firstOrFail();
        $partido->setRelation('campeonato', $campeonato);
        Gate::authorize('arbitrar', $partido);
        app(DesempateGrupoService::class)->comprobarVigente($partido);

        return $partido;
    }
}
