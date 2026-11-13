<?php

namespace App\Policies;

use App\Models\EventoEstadistico;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class EventoEstadisticoPolicy
{
    public function update(User $user, EventoEstadistico $evento): bool
    {
        $participante = $evento->equipoPartido;
        $plantilla = $evento->plantilla;

        return $participante && $plantilla
            && Gate::forUser($user)->allows('participarEnActa', [$plantilla, $participante->partido]);
    }

    public function delete(User $user, EventoEstadistico $evento): bool
    {
        return $this->update($user, $evento);
    }
}
