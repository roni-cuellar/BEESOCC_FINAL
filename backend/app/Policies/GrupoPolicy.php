<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class GrupoPolicy
{
    public function view(User $user, Grupo $grupo): bool
    {
        return Gate::forUser($user)->allows('view', $grupo->fase);
    }
}
