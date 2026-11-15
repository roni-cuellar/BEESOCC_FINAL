<?php

namespace App\Policies;

use App\Models\Llave;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class LlavePolicy
{
    public function avanzar(User $user, Llave $llave): bool
    {
        return Gate::forUser($user)->allows('update', $llave->ronda->fase->campeonato);
    }

    public function registrarPenales(User $user, Llave $llave): bool
    {
        $partido = $llave->partidos()->reorder()->orderByDesc('numero_serie')->first();

        return $partido && Gate::forUser($user)->allows('arbitrar', $partido);
    }
}
