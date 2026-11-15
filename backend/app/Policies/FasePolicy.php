<?php

namespace App\Policies;

use App\Models\Fase;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FasePolicy
{
    public function view(User $user, Fase $fase): bool
    {
        return Gate::forUser($user)->allows('verFases', $fase->campeonato);
    }

    public function update(User $user, Fase $fase): bool
    {
        return Gate::forUser($user)->allows('update', $fase->campeonato);
    }
}
