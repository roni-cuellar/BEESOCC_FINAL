<?php

namespace App\Policies;

use App\Models\Jugador;
use App\Models\User;

class JugadorPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function usar(User $user, Jugador $jugador): bool
    {
        return $jugador->administrador_id !== null && (int) $jugador->administrador_id === (int) $user->id;
    }

    public function update(User $user, Jugador $jugador): bool
    {
        return $this->usar($user, $jugador) && ! $jugador->plantillas()
            ->whereHas('campeonatoEquipo.campeonato', fn ($consulta) => $consulta->whereNull('administrador_id')->orWhere('administrador_id', '!=', $user->id))
            ->exists();
    }

    public function delete(User $user, Jugador $jugador): bool
    {
        return $this->update($user, $jugador);
    }
}
