<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Llave;
use App\Models\LlavePlaza;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AvanceEliminacionService
{
    private function bloquear(Llave $llave): Llave
    {
        $campeonato = Campeonato::whereKey($llave->ronda->fase->campeonato_id)->lockForUpdate()->firstOrFail();
        $campeonato->inscripcionesEquipos()->lockForUpdate()->get();

        return Llave::whereKey($llave->id)->lockForUpdate()->firstOrFail();
    }

    public function avanzar(Llave $llave): Llave
    {
        return DB::transaction(function () use ($llave) {
            $llave = $this->bloquear($llave);
            Gate::authorize('avanzar', $llave);
            abort_unless($llave->estado === 'pendiente', 409, 'La llave ya fue clasificada.');
            $ganador = $this->ganador($llave);
            $this->resolver($llave, $ganador, $llave->penales_plaza_1 !== null ? $llave->ronda->desempate : 'goles', 'Avance administrativo basado en resultados oficiales.');

            return $llave->refresh();
        });
    }

    public function registrarPenales(Llave $llave, array $datos): Llave
    {
        return DB::transaction(function () use ($llave, $datos) {
            $llave = $this->bloquear($llave);
            Gate::authorize('registrarPenales', $llave);
            abort_unless($llave->estado === 'pendiente' && $llave->penales_plaza_1 === null && $llave->penales_plaza_2 === null, 409, 'La tanda ya se registró o la llave fue resuelta.');
            abort_unless($this->ganador($llave, false) === null, 409, 'Solo se registra una tanda si el resultado oficial está empatado.');
            $auditoria = app(AuditoriaEliminacionService::class);
            $antes = $auditoria->capturar($llave);
            $llave->update(collect($datos)->only(['penales_plaza_1', 'penales_plaza_2'])->all());
            $auditoria->registrar($llave->ronda->fase, 'llave', $llave->id, 'registrar_penales', $datos['motivo'], $antes, $auditoria->capturar($llave));

            return $llave;
        });
    }

    private function plazas(Llave $llave)
    {
        $plazas = $llave->plazas()->with('participante.inscripcion.equipo')->get();
        abort_unless($plazas->count() === 2 && $plazas->pluck('posicion')->map(fn ($p) => (int) $p)->all() === [1, 2], 409, 'La llave debe tener dos plazas válidas.');
        foreach ($plazas as $plaza) {
            if (! $plaza->fase_equipo_id) {
                abort_unless($plaza->origen_tipo === 'bye', 409, 'Todavía hay plazas sin clasificado.');

                continue;
            }
            $equipo = $plaza->participante;
            $inscripcion = $equipo?->inscripcion;
            abort_unless($equipo && (int) $equipo->fase_id === (int) $llave->ronda->fase_id && $equipo->estado === 'activo'
                && $inscripcion && $inscripcion->estado === 'activo' && $inscripcion->equipo
                && (int) $inscripcion->campeonato_id === (int) $llave->ronda->fase->campeonato_id
                && (int) $inscripcion->equipo->administrador_id === (int) $llave->ronda->fase->campeonato->administrador_id,
                409, 'Participante ajeno, retirado o descalificado: se requiere revisión administrativa.');
        }
        abort_unless($plazas->whereNotNull('fase_equipo_id')->pluck('fase_equipo_id')->unique()->count() === $plazas->whereNotNull('fase_equipo_id')->count(), 409, 'Un equipo no puede ocupar ambas plazas.');

        return $plazas;
    }

    public function ganador(Llave $llave, bool $usarPenales = true): ?int
    {
        $plazas = $this->plazas($llave);
        abort_if($plazas->contains('origen_tipo', 'bye'), 409, 'El descanso se resuelve al preparar el cuadro.');
        $cantidad = $llave->ronda->modalidad === 'ida_vuelta' ? 2 : 1;
        $partidos = $llave->partidos()->with('participantes')->get();
        abort_unless($partidos->count() === $cantidad, 409, 'Faltan partidos de la serie.');
        $goles = [0, 0];
        foreach ($partidos as $indice => $partido) {
            abort_unless((int) $partido->numero_serie === $indice + 1
                && (int) $partido->fase_id === (int) $llave->ronda->fase_id
                && (int) $partido->campeonato_id === (int) $llave->ronda->fase->campeonato_id
                && $partido->estado === 'finalizado' && $partido->oficializado_at && $partido->tipo_resultado === 'deportivo',
                409, 'Se requieren todos los partidos deportivos oficiales de esta llave.');
            $marcador = app(MarcadorService::class)->calcular($partido);
            foreach (['local', 'visitante'] as $rol) {
                $posicion = $rol === 'local' ? $indice % 2 : 1 - ($indice % 2);
                $participante = $partido->participantes->firstWhere('rol', $rol);
                abort_unless((int) $participante->campeonato_equipo_id === (int) $plazas[$posicion]->participante->campeonato_equipo_id,
                    409, 'Los participantes del partido no coinciden con las plazas.');
                $goles[$posicion] += $marcador[$rol]['goles'];
            }
        }
        if ($goles[0] !== $goles[1]) {
            abort_if($llave->penales_plaza_1 !== null || $llave->penales_plaza_2 !== null, 409, 'La corrección afecta una tanda previa; requiere revisión específica.');

            return (int) $plazas[$goles[0] > $goles[1] ? 0 : 1]->fase_equipo_id;
        }
        if (! $usarPenales) {
            return null;
        }
        abort_unless($llave->penales_plaza_1 !== null && $llave->penales_plaza_2 !== null
            && (int) $llave->penales_plaza_1 !== (int) $llave->penales_plaza_2, 409, 'Empate oficial: falta una tanda válida certificada por el árbitro.');

        return (int) $plazas[$llave->penales_plaza_1 > $llave->penales_plaza_2 ? 0 : 1]->fase_equipo_id;
    }

    public function preparar(Llave $llave): void
    {
        $plazas = $llave->plazas()->with('participante')->get();
        if ($plazas->contains(fn ($p) => ! $p->fase_equipo_id && $p->origen_tipo !== 'bye')) {
            return;
        }
        $plazas = $this->plazas($llave);
        if ($plazas->contains('origen_tipo', 'bye')) {
            abort_unless($plazas->whereNotNull('fase_equipo_id')->count() === 1 && ! $llave->partidos()->exists(), 409, 'Descanso inconsistente.');
            $ganador = (int) $plazas->firstWhere('fase_equipo_id', '!=', null)->fase_equipo_id;
            if ($llave->estado !== 'resuelta' || (int) $llave->ganador_fase_equipo_id !== $ganador) {
                $this->resolver($llave, $ganador, 'bye', 'Pase automático según siembra confirmada.');
            }

            return;
        }
        if ($llave->partidos()->exists()) {
            return;
        }
        $cantidad = $llave->ronda->modalidad === 'ida_vuelta' ? 2 : 1;
        for ($serie = 1; $serie <= $cantidad; $serie++) {
            $partido = new Partido(['campeonato_id' => $llave->ronda->fase->campeonato_id, 'jornada' => $llave->ronda->numero, 'estado' => 'programado']);
            $partido->fase_id = $llave->ronda->fase_id;
            $partido->llave_id = $llave->id;
            $partido->numero_serie = $serie;
            $partido->save();
            foreach (['local', 'visitante'] as $indice => $rol) {
                $posicion = $serie === 1 ? $indice : 1 - $indice;
                $partido->participantes()->create(['rol' => $rol, 'campeonato_equipo_id' => $plazas[$posicion]->participante->campeonato_equipo_id]);
            }
        }
    }

    private function resolver(Llave $llave, int $ganador, string $metodo, string $motivo): void
    {
        $auditoria = app(AuditoriaEliminacionService::class);
        $antes = $auditoria->capturar($llave);
        $llave->update(['estado' => 'resuelta', 'ganador_fase_equipo_id' => $ganador, 'metodo_resolucion' => $metodo]);
        $auditoria->registrar($llave->ronda->fase, 'llave', $llave->id, 'resolver_llave', $motivo, $antes, $auditoria->capturar($llave));
        $this->propagar($llave, $motivo);
        $this->actualizarEstados($llave->ronda->fase);
    }

    private function propagar(Llave $origen, string $motivo): void
    {
        foreach (LlavePlaza::where('origen_llave_id', $origen->id)->get() as $plaza) {
            $destino = $plaza->llave;
            abort_unless((int) $destino->ronda->fase_id === (int) $origen->ronda->fase_id
                && (int) $destino->ronda->numero === (int) $origen->ronda->numero + 1, 409, 'Procedencia de plaza inconsistente.');
            if ((int) $plaza->fase_equipo_id === (int) $origen->ganador_fase_equipo_id) {
                continue;
            }
            $auditoria = app(AuditoriaEliminacionService::class);
            $antes = $auditoria->capturar($destino);
            $partidos = $destino->partidos()->lockForUpdate()->get();
            foreach ($partidos as $partido) {
                abort_unless($partido->estado === 'programado' && ! $partido->oficializado_at
                    && ! $partido->participantes()->whereHas('eventos')->exists(), 409, 'La corrección afecta una ronda iniciada o cerrada; se requiere revisión administrativa.');
            }
            $plaza->update(['fase_equipo_id' => $origen->ganador_fase_equipo_id]);
            foreach ($partidos as $partido) {
                $captura = app(AuditoriaPartidoService::class)->capturar($partido);
                $rol = ((int) $plaza->posicion === 1) === ((int) $partido->numero_serie === 1) ? 'local' : 'visitante';
                $participante = $partido->participantes()->where('rol', $rol)->firstOrFail();
                $participante->update(['campeonato_equipo_id' => $origen->ganador->campeonato_equipo_id]);
                app(AuditoriaPartidoService::class)->registrar($partido, 'actualizar_clasificado', $motivo, $captura, app(AuditoriaPartidoService::class)->capturar($partido));
            }
            $this->preparar($destino);
            $auditoria->registrar($destino->ronda->fase, 'llave', $destino->id, 'actualizar_plaza', $motivo, $antes, $auditoria->capturar($destino));
        }
    }

    public function revisarCorreccion(Partido $partido, string $motivo): void
    {
        if (! $partido->llave_id) {
            return;
        }
        $llave = Llave::whereKey($partido->llave_id)->lockForUpdate()->firstOrFail();
        $llave->ronda->fase->campeonato->inscripcionesEquipos()->lockForUpdate()->get();
        if ($llave->estado !== 'resuelta' && $llave->penales_plaza_1 === null) {
            return;
        }
        $ganador = $this->ganador($llave);
        if ($llave->estado === 'resuelta' && (int) $llave->ganador_fase_equipo_id !== $ganador) {
            $this->resolver($llave, $ganador, 'goles', $motivo);
        }
    }

    public function actualizarEstados(Fase $fase): void
    {
        foreach ($fase->rondas as $ronda) {
            $resuelta = ! $ronda->llaves()->where('estado', '!=', 'resuelta')->exists();
            $iniciada = Partido::whereIn('llave_id', $ronda->llaves()->select('id'))->where('estado', '!=', 'programado')->exists();
            $ronda->update(['estado' => $resuelta ? 'finalizada' : ($iniciada ? 'en_curso' : 'planificacion')]);
        }
        $estados = $fase->rondas()->pluck('estado');
        $fase->update(['estado' => $estados->isNotEmpty() && $estados->every(fn ($e) => $e === 'finalizada') ? 'finalizada'
            : ($estados->contains(fn ($e) => $e !== 'planificacion') ? 'en_curso' : 'planificacion')]);
    }
}
