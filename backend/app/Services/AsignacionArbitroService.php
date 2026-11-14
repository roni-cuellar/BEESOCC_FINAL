<?php

namespace App\Services;

use App\Models\AsignacionArbitro;
use App\Models\Campeonato;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AsignacionArbitroService
{
    private function bloquear(Campeonato $campeonato, User $actor): Campeonato
    {
        // Bloquear el padre también serializa la primera asignación, cuando aún no hay filas hijas.
        $campeonato = Campeonato::whereKey($campeonato->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('gestionarArbitros', $campeonato);

        return $campeonato;
    }

    private function crear(Campeonato $campeonato, User $actor, User $arbitro): AsignacionArbitro
    {
        if ((int) $arbitro->id === (int) $campeonato->administrador_id) {
            throw ValidationException::withMessages(['usuario_id' => 'El propietario no puede arbitrar su propio campeonato.']);
        }

        return $campeonato->asignacionesArbitro()->create([
            'usuario_id' => $arbitro->id,
            'autorizado_por' => $actor->id,
            'fecha_inicio' => now(),
            'fecha_fin' => null,
        ])->refresh();
    }

    public function asignar(Campeonato $campeonato, User $actor, User $arbitro): AsignacionArbitro
    {
        try {
            return DB::transaction(function () use ($campeonato, $actor, $arbitro) {
                $campeonato = $this->bloquear($campeonato, $actor);
                abort_if($campeonato->asignacionesArbitro()->whereNull('fecha_fin')->exists(), 409, 'El campeonato ya tiene un árbitro activo.');

                return $this->crear($campeonato, $actor, $arbitro);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'El campeonato ya tiene un árbitro activo.');
        }
    }

    public function reemplazar(Campeonato $campeonato, User $actor, User $arbitro, string $motivo): AsignacionArbitro
    {
        try {
            return DB::transaction(function () use ($campeonato, $actor, $arbitro, $motivo) {
                $campeonato = $this->bloquear($campeonato, $actor);
                $anterior = $campeonato->asignacionesArbitro()->whereNull('fecha_fin')->lockForUpdate()->first();
                abort_unless($anterior, 409, 'No hay una asignación activa para reemplazar.');
                abort_if((int) $anterior->usuario_id === (int) $arbitro->id, 409, 'El usuario indicado ya es el árbitro activo.');
                $anterior->update(['fecha_fin' => now(), 'motivo_revocacion' => $motivo]);

                // Si crear falla, la transacción conserva la asignación anterior activa.
                return $this->crear($campeonato, $actor, $arbitro);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'El campeonato ya tiene un árbitro activo.');
        }
    }

    public function revocar(Campeonato $campeonato, User $actor, string $motivo): AsignacionArbitro
    {
        return DB::transaction(function () use ($campeonato, $actor, $motivo) {
            $campeonato = $this->bloquear($campeonato, $actor);
            $asignacion = $campeonato->asignacionesArbitro()->whereNull('fecha_fin')->lockForUpdate()->first();
            abort_unless($asignacion, 409, 'No hay una asignación activa para revocar.');
            $asignacion->update(['fecha_fin' => now(), 'motivo_revocacion' => $motivo]);

            return $asignacion->refresh();
        }, 3);
    }
}
