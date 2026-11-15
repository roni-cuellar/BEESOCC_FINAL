<?php

namespace App\Services;

use App\Models\EquipoPartido;
use App\Models\Partido;

class MarcadorService
{
    public function goles(EquipoPartido $participante): int
    {
        // El autogol ya pertenece al participante beneficiado.
        return $participante->eventos->whereIn('tipo', ['gol', 'autogol'])->count();
    }

    public function calcular(Partido $partido): array
    {
        $participantes = $partido->participantes()->with(['campeonatoEquipo.equipo', 'eventos'])->get();
        $local = $participantes->firstWhere('rol', 'local');
        $visitante = $participantes->firstWhere('rol', 'visitante');
        abort_unless($participantes->count() === 2 && $local && $visitante,
            409, 'El partido debe tener exactamente un local y un visitante.');
        foreach ($participantes as $participante) {
            $inscripcion = $participante->campeonatoEquipo;
            abort_unless($inscripcion && $inscripcion->equipo
                && (int) $inscripcion->campeonato_id === (int) $partido->campeonato_id,
                409, 'Los participantes deben tener equipos válidos del campeonato.');
        }
        abort_if((int) $local->campeonatoEquipo->equipo_id === (int) $visitante->campeonatoEquipo->equipo_id,
            409, 'El local y el visitante deben ser equipos diferentes.');

        return ['partido_id' => $partido->id, 'jornada' => $partido->jornada, 'estado' => $partido->estado,
            'local' => $this->datos($local), 'visitante' => $this->datos($visitante)];
    }

    private function datos(EquipoPartido $participante): array
    {
        $equipo = $participante->campeonatoEquipo->equipo;

        return ['id' => $equipo->id, 'nombre' => $equipo->nombre, 'goles' => $this->goles($participante)];
    }
}
