<?php

namespace App\Services;

use App\Models\EquipoPartido;
use App\Models\Partido;

class MarcadorService
{
    public function goles(EquipoPartido $participante): int
    {
        $p = $participante->partido;
        if ($p->tipo_resultado === 'administrativo') {
            abort_unless($p->esOficial() && in_array((int) $participante->campeonato_equipo_id, [(int) $p->administrativo_beneficiado_id, (int) $p->administrativo_sancionado_id], true), 409, 'Resolución administrativa inconsistente.');

            return (int) $participante->campeonato_equipo_id === (int) $p->administrativo_beneficiado_id ? 3 : 0;
        }

        return $this->golesDeportivos($participante);
    }

    public function golesDeportivos(EquipoPartido $participante): int
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
            'tipo_resultado' => $partido->tipo_resultado, 'oficializado_at' => $partido->oficializado_at,
            'marcador_deportivo' => ['local' => $this->golesDeportivos($local), 'visitante' => $this->golesDeportivos($visitante)],
            'local' => $this->datos($local), 'visitante' => $this->datos($visitante)];
    }

    private function datos(EquipoPartido $participante): array
    {
        $equipo = $participante->campeonatoEquipo->equipo;

        return ['id' => $equipo->id, 'nombre' => $equipo->nombre, 'goles' => $this->goles($participante)];
    }
}
