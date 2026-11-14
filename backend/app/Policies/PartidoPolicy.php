<?php

namespace App\Policies;

use App\Models\Partido;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PartidoPolicy
{
    public function update(User $user, Partido $partido): bool
    {
        if (! Gate::forUser($user)->allows('update', $partido->campeonato)) {
            return false;
        }

        foreach ($partido->participantes as $participante) {
            $inscripcion = $participante->campeonatoEquipo;
            if (! $inscripcion || (int) $inscripcion->campeonato_id !== (int) $partido->campeonato_id
                || ! Gate::forUser($user)->allows('usar', $inscripcion->equipo)) {
                return false;
            }
        }

        return true;
    }

    public function gestionarActa(User $user, Partido $partido): bool
    {
        // Alias temporal para los flujos actuales; no concede permisos al propietario.
        return $this->arbitrar($user, $partido);
    }

    public function arbitrar(User $user, Partido $partido): bool
    {
        if (! $user->esArbitroDe($partido->campeonato)) {
            return false;
        }

        $participantes = $partido->participantes;
        if ($participantes->count() !== 2 || $participantes->pluck('rol')->sort()->values()->all() !== ['local', 'visitante']
            || $participantes->pluck('campeonato_equipo_id')->unique()->count() !== 2) {
            return false;
        }
        foreach ($participantes as $participante) {
            $inscripcion = $participante->campeonatoEquipo;
            if (! $inscripcion || ! $inscripcion->equipo
                || (int) $inscripcion->campeonato_id !== (int) $partido->campeonato_id
                || (int) $inscripcion->equipo->administrador_id !== (int) $partido->campeonato->administrador_id) {
                return false;
            }
        }

        return true;
    }

    public function view(User $user, Partido $partido): bool
    {
        return $this->update($user, $partido) || $this->arbitrar($user, $partido);
    }

    public function delete(User $user, Partido $partido): bool
    {
        return $this->update($user, $partido);
    }
}
