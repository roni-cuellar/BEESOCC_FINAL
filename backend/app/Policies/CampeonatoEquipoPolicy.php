<?php

namespace App\Policies;

use App\Models\CampeonatoEquipo;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CampeonatoEquipoPolicy
{
    public function update(User $user, CampeonatoEquipo $inscripcion): bool
    {
        return Gate::forUser($user)->allows('update', $inscripcion->campeonato)
            && Gate::forUser($user)->allows('usar', $inscripcion->equipo);
    }

    public function delete(User $user, CampeonatoEquipo $inscripcion): bool
    {
        return $this->update($user, $inscripcion);
    }
}
