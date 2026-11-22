<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Models\Plantilla;
use Illuminate\Database\Eloquent\Builder;

class FichaDeportivaService
{
    private function plantillas(Campeonato $liga): Builder
    {
        return Plantilla::whereHas('campeonatoEquipo', fn (Builder $consulta) => $consulta->where('campeonato_id', $liga->id))
            ->with(['jugador', 'campeonatoEquipo.equipo'])->latest('fecha_ingreso')->latest('id');
    }

    private function vigente(Plantilla $plantilla): bool
    {
        return $plantilla->fecha_salida === null && $plantilla->campeonatoEquipo->estado === 'activo';
    }

    private function pertenece(Plantilla $plantilla, Campeonato $liga): bool
    {
        return $plantilla->jugador && $plantilla->campeonatoEquipo->equipo
            && (int) $plantilla->jugador->administrador_id === (int) $liga->administrador_id
            && (int) $plantilla->campeonatoEquipo->equipo->administrador_id === (int) $liga->administrador_id;
    }

    public function jugador(Campeonato $liga, int $jugador, EstadisticaService $estadistica): array
    {
        $plantillas = $this->plantillas($liga)->where('jugador_id', $jugador)->get();
        abort_if($plantillas->isEmpty(), 404);

        $equipos = $plantillas->map(function (Plantilla $plantilla) use ($liga) {
            abort_unless($this->pertenece($plantilla, $liga), 404);

            return [
                'equipo' => $plantilla->campeonatoEquipo->equipo->only(['id', 'nombre', 'escudo']),
                'dorsal' => $plantilla->dorsal,
                'posicion' => $plantilla->posicion,
                'vigente' => $this->vigente($plantilla),
            ];
        })->sortByDesc('vigente')->values();

        return [
            'jugador' => $plantillas->first()->jugador->only(['id', 'nombre', 'apellido', 'foto']),
            'equipos' => $equipos,
            'estadisticas' => $estadistica->totalesJugador($liga, $jugador),
        ];
    }

    public function identidades(Campeonato $liga): array
    {
        // El ranking suma toda la liga. La identidad muestra la inscripción vigente
        // o la última conocida si el jugador ya no tiene una inscripción activa.
        $jugadores = $this->plantillas($liga)->get()
            ->filter(fn (Plantilla $fila) => $this->pertenece($fila, $liga))
            ->sortByDesc($this->vigente(...))->unique('jugador_id')->values()
            ->map(fn (Plantilla $fila) => [
                'jugador_id' => $fila->jugador_id,
                'foto' => $fila->jugador->foto,
                'equipo' => $fila->campeonatoEquipo->equipo->only(['id', 'nombre', 'escudo']),
            ]);

        $equipos = $liga->inscripcionesEquipos()->whereHas('equipo',
            fn (Builder $consulta) => $consulta->where('administrador_id', $liga->administrador_id))
            ->with('equipo')->get()->map(fn ($fila) => $fila->equipo->only(['id', 'nombre', 'escudo']));

        return ['jugadores' => $jugadores, 'equipos' => $equipos];
    }
}
