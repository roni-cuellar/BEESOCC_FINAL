<?php

namespace App\Services;

use App\Models\EquipoPartido;
use App\Models\EventoEstadistico;
use App\Models\Partido;
use App\Models\Plantilla;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class EventoEstadisticoService
{
    public function validar(array $datos): array
    {
        $datos = Validator::make($datos, [
            'equipo_partido_id' => 'required|integer|exists:equipo_partido,id',
            'plantilla_id' => 'required|integer|exists:plantillas,id',
            'tipo' => 'required|in:gol,autogol,asistencia,amarilla,roja,falta',
            'minuto' => 'required|integer|between:0,150',
            'gol_relacionado_id' => 'required_if:tipo,asistencia|nullable|integer|exists:eventos_estadisticos,id',
            'partido_id' => 'prohibited',
            'jugador_id' => 'prohibited',
            'equipo_id' => 'prohibited',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'Una asistencia debe indicar su gol_relacionado_id.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El registro indicado en :attribute no existe.',
            'in' => 'El tipo debe ser gol, autogol, asistencia, amarilla, roja o falta.',
            'between' => 'El minuto debe estar entre 0 y 150.',
            'prohibited' => 'El partido se indica únicamente en la URL.',
        ])->validate();
        abort_if($datos['tipo'] !== 'asistencia' && isset($datos['gol_relacionado_id']),
            422, 'Solo las asistencias pueden indicar un gol relacionado.');

        return $datos;
    }

    public function comprobar(Partido $partido, array $datos, ?int $ignorarId = null): array
    {
        $participante = $partido->participantes()->with('campeonatoEquipo')->whereKey($datos['equipo_partido_id'])->first();
        $plantilla = Plantilla::with('campeonatoEquipo')->whereKey($datos['plantilla_id'])->lockForUpdate()->first();
        abort_unless($participante && $plantilla, 422, 'El participante debe pertenecer al partido y la plantilla debe existir.');
        $this->comprobarEquipo($partido, $participante, $plantilla, $datos['tipo']);
        Gate::authorize('participarEnActa', [$plantilla, $partido]);
        $this->comprobarVigencia($partido, $plantilla);
        if ($datos['tipo'] === 'asistencia') {
            $this->comprobarAsistencia($participante, $plantilla, $datos['gol_relacionado_id'], $ignorarId);
        }

        return [$participante, $plantilla];
    }

    private function comprobarEquipo(Partido $partido, EquipoPartido $participante, Plantilla $plantilla, string $tipo): void
    {
        abort_unless((int) $plantilla->campeonatoEquipo->campeonato_id === (int) $partido->campeonato_id
            && (int) $participante->campeonatoEquipo->campeonato_id === (int) $partido->campeonato_id,
            422, 'El jugador y el equipo participante deben pertenecer al campeonato del partido.');
        if ($tipo === 'autogol') {
            // El participante es el beneficiado; el jugador pertenece al rival.
            $esRival = $partido->participantes()->where('id', '!=', $participante->id)
                ->where('campeonato_equipo_id', $plantilla->campeonato_equipo_id)->exists();
            abort_unless($esRival && (int) $plantilla->campeonato_equipo_id !== (int) $participante->campeonato_equipo_id,
                422, 'En un autogol, el jugador debe pertenecer al equipo rival del beneficiado en este partido.');
        } else {
            abort_unless((int) $plantilla->campeonato_equipo_id === (int) $participante->campeonato_equipo_id,
                422, 'La plantilla del jugador no corresponde al equipo participante.');
        }
    }

    private function comprobarVigencia(Partido $partido, Plantilla $plantilla): void
    {
        // Se conserva el criterio temporal del Sprint 2.3A; DP-07 sigue pendiente.
        $fecha = ($partido->fecha_hora ?? now())->toDateString();
        abort_if($plantilla->fecha_ingreso->toDateString() > $fecha
            || ($plantilla->fecha_salida && $plantilla->fecha_salida->toDateString() < $fecha),
            422, 'El jugador no está habilitado en la fecha del partido.');
    }

    private function comprobarAsistencia(EquipoPartido $participante, Plantilla $plantilla, int $golId, ?int $ignorarId): void
    {
        $gol = EventoEstadistico::with('plantilla')->whereKey($golId)->where('tipo', 'gol')
            ->where('equipo_partido_id', $participante->id)->first();
        abort_unless($gol && $gol->plantilla, 422, 'La asistencia debe relacionarse con un gol del mismo partido y equipo beneficiado.');
        abort_if((int) $gol->plantilla->jugador_id === (int) $plantilla->jugador_id, 422, 'Un jugador no puede asistirse a sí mismo.');
        $duplicada = EventoEstadistico::where('tipo', 'asistencia')->where('gol_relacionado_id', $golId)
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))->exists();
        abort_if($duplicada, 409, 'El gol ya tiene una asistencia registrada.');
    }

    public function registrar(Partido $partido, array $datos): EventoEstadistico
    {
        [$participante, $plantilla] = $this->comprobar($partido, $datos);
        $evento = $participante->eventos()->create([
            'plantilla_id' => $plantilla->id,
            'tipo' => $datos['tipo'],
            'minuto' => $datos['minuto'],
            'gol_relacionado_id' => $datos['tipo'] === 'asistencia' ? $datos['gol_relacionado_id'] : null,
        ]);

        return $evento;
    }

    public function corregir(Partido $partido, EventoEstadistico $evento, int $minuto): void
    {
        $this->comprobarPartido($partido, $evento);
        $evento->update(['minuto' => $minuto]);
    }

    public function eliminar(Partido $partido, EventoEstadistico $evento): void
    {
        $this->comprobarPartido($partido, $evento);
        abort_if($evento->tipo === 'gol' && EventoEstadistico::where('tipo', 'asistencia')
            ->where('gol_relacionado_id', $evento->id)->exists(), 409,
            'Primero debes eliminar la asistencia relacionada con este gol.');
        $evento->delete();
    }

    private function comprobarPartido(Partido $partido, EventoEstadistico $evento): void
    {
        abort_unless((int) $evento->equipoPartido->partido_id === (int) $partido->id,
            422, 'El evento no pertenece al partido autorizado.');
        Gate::authorize('update', $evento);
    }
}
