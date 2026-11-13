<?php

namespace App\Policies;

use App\Models\Partido;
use App\Models\Plantilla;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PlantillaPolicy
{
    public function update(User $user, Plantilla $plantilla): bool
    {
        return Gate::forUser($user)->allows('update', $plantilla->campeonatoEquipo)
            && Gate::forUser($user)->allows('usar', $plantilla->jugador);
    }

    public function delete(User $user, Plantilla $plantilla): bool
    {
        return $this->update($user, $plantilla);
    }

    public function participarEnActa(User $user, Plantilla $plantilla, Partido $partido): bool
    {
        $inscripcion = $plantilla->campeonatoEquipo;
        $propietario = $partido->campeonato->administrador_id;

        return Gate::forUser($user)->allows('gestionarActa', $partido)
            && $inscripcion && $propietario !== null
            && (int) $inscripcion->campeonato_id === (int) $partido->campeonato_id
            && (int) $inscripcion->equipo->administrador_id === (int) $propietario
            && (int) $plantilla->jugador->administrador_id === (int) $propietario
            && $partido->participantes()->where('campeonato_equipo_id', $inscripcion->id)->exists();
    }
}
