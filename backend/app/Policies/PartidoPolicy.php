<?php

namespace App\Policies;

use App\Models\Partido;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PartidoPolicy
{
    public function update(User $user, Partido $partido): bool
    {
        if (! Gate::forUser($user)->allows('update', $partido->campeonato)) {
            return false;
        }

        foreach ($partido->participantes as $participante) {
            $inscripcion = $participante->campeonatoEquipo;
            if (! $inscripcion || (int) $inscripcion->campeonato_id !== (int) $partido->campeonato_id
                || ! Gate::forUser($user)->allows('usar', $inscripcion->equipo)) {
                return false;
            }
        }

        return true;
    }

    public function gestionarActa(User $user, Partido $partido): bool
    {
        // Excepción temporal autorizada en Sprint 1.4; sustituir al implementar arbitraje.
        return $this->update($user, $partido);
    }

    public function delete(User $user, Partido $partido): bool
    {
        return $this->update($user, $partido);
    }
}
