<?php

namespace App\Policies;

use App\Models\AutorizacionCorreccion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AutorizacionCorreccionPolicy
{
    public function resolver(User $user, AutorizacionCorreccion $correccion): bool
    {
        return Gate::forUser($user)->allows('update', $correccion->partido->campeonato);
    }

    public function aplicar(User $user, AutorizacionCorreccion $correccion): bool
    {
        return (int) $correccion->solicitado_por_id === (int) $user->id
            && Gate::forUser($user)->allows('arbitrar', $correccion->partido);
    }
}
