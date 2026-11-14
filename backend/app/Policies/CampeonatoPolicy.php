<?php

namespace App\Policies;

use App\Models\Campeonato;
use App\Models\User;

class CampeonatoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campeonato $campeonato): bool
    {
        return $campeonato->administrador_id !== null && (int) $campeonato->administrador_id === (int) $user->id;
    }

    public function update(User $user, Campeonato $campeonato): bool
    {
        return $this->view($user, $campeonato);
    }

    public function delete(User $user, Campeonato $campeonato): bool
    {
        return $this->view($user, $campeonato);
    }

    public function publicar(User $user, Campeonato $campeonato): bool
    {
        return $this->view($user, $campeonato);
    }

    public function gestionarArbitros(User $user, Campeonato $campeonato): bool
    {
        return $this->view($user, $campeonato);
    }
}
