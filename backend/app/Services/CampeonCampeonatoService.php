<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\Campeonato;

class CampeonCampeonatoService
{
    public function consultar(Campeonato $liga): ?array
    {
        if ($liga->estado !== 'finalizado') {
            return null;
        }

        $registro = AuditoriaCambio::where('campeonato_id', $liga->id)->where('entidad_tipo', 'campeonato')
            ->where('entidad_id', $liga->id)->where('accion', 'finalizar_campeonato')->latest('id')->first();
        $id = $registro?->posterior_json['campeon_campeonato_equipo_id'] ?? null;
        $inscripcion = $liga->inscripcionesEquipos()->whereKey($id)->where('estado', 'activo')->with('equipo')->first();
        if (! $inscripcion || ! $inscripcion->equipo || $inscripcion->equipo->administrador_id !== $liga->administrador_id) {
            return null;
        }

        return $inscripcion->equipo->only(['id', 'nombre', 'escudo']);
    }
}
