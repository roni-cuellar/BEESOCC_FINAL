<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\User;

class EquipoPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function usar(User $user, Equipo $equipo): bool
    {
        return $equipo->administrador_id !== null && (int) $equipo->administrador_id === (int) $user->id;
    }

    public function update(User $user, Equipo $equipo): bool
    {
        // Editar el catálogo afecta todas sus inscripciones, incluidas las históricas.
        return $this->usar($user, $equipo) && ! $equipo->inscripcionesCampeonatos()
            ->whereHas('campeonato', fn ($consulta) => $consulta->whereNull('administrador_id')->orWhere('administrador_id', '!=', $user->id))
            ->exists();
    }

    public function delete(User $user, Equipo $equipo): bool
    {
        return $this->update($user, $equipo);
    }
}
