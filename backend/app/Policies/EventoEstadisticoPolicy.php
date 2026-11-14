<?php

namespace App\Policies;

use App\Models\EventoEstadistico;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class EventoEstadisticoPolicy
{
    public function view(User $user, EventoEstadistico $evento): bool
    {
        return $this->update($user, $evento)
            || ($evento->equipoPartido && $evento->plantilla
                && Gate::forUser($user)->allows('update', $evento->plantilla)
                && Gate::forUser($user)->allows('view', $evento->equipoPartido->partido));
    }

    public function update(User $user, EventoEstadistico $evento): bool
    {
        $participante = $evento->equipoPartido;
        $plantilla = $evento->plantilla;

        return $participante && $plantilla
            && ($evento->tipo === 'autogol'
                ? (int) $plantilla->campeonato_equipo_id !== (int) $participante->campeonato_equipo_id
                : (int) $plantilla->campeonato_equipo_id === (int) $participante->campeonato_equipo_id)
            && Gate::forUser($user)->allows('participarEnActa', [$plantilla, $participante->partido]);
    }

    public function delete(User $user, EventoEstadistico $evento): bool
    {
        return $this->update($user, $evento);
    }
}
