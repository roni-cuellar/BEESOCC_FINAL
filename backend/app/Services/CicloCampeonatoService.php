<?php

namespace App\Services;

use App\Models\AutorizacionCorreccion;
use App\Models\Campeonato;
use App\Models\FaseEquipo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CicloCampeonatoService
{
    public function iniciar(Campeonato $original): Campeonato
    {
        return DB::transaction(function () use ($original) {
            $integridad = app(IntegridadCampeonatoService::class);
            $c = $integridad->bloquear($original->id, true);
            Gate::authorize('update', $c);
            $config = $c->configuracion;
            abort_unless($config && trim($config->modalidad ?? '') !== '' && $config->minimo_jugadores >= 1, 409, 'Configura modalidad y mínimo de jugadores antes de iniciar.');
            $equipos = $c->inscripcionesEquipos()->orderBy('id')->lockForUpdate()->get();
            abort_unless($equipos->count() >= 2 && $equipos->every(fn ($e) => $e->estado === 'activo'), 409, 'Se requieren al menos dos equipos activos, sin retiros pendientes.');
            abort_unless($c->asignacionesArbitro()->whereNull('fecha_fin')->where('fecha_inicio', '<=', now())->where('usuario_id', '!=', $c->administrador_id)->exists(), 409, 'Asigna un árbitro vigente distinto del administrador.');
            $jugadores = [];
            foreach ($equipos as $equipo) {
                Gate::authorize('usar', $equipo->equipo);
                // Conserva la vigencia DATE inclusiva existente; DP-07 no se redefine.
                $plantilla = $equipo->plantillas()->whereDate('fecha_ingreso', '<=', today())->where(fn ($q) => $q->whereNull('fecha_salida')->orWhereDate('fecha_salida', '>=', today()))->orderBy('id')->lockForUpdate()->get();
                abort_unless($plantilla->count() >= $config->minimo_jugadores, 409, 'Un equipo no alcanza el mínimo de jugadores elegibles.');
                abort_unless($plantilla->pluck('dorsal')->unique()->count() === $plantilla->count(), 409, 'Hay dorsales elegibles repetidos en la fecha de inicio.');
                foreach ($plantilla as $p) {
                    Gate::authorize('usar', $p->jugador);
                    abort_if(isset($jugadores[$p->jugador_id]), 409, 'Un jugador tiene más de una inscripción elegible. Revisa las vigencias.');
                    $jugadores[$p->jugador_id] = true;
                }
            }
            abort_if($c->partidos()->where('estado', '!=', 'programado')->exists(), 409, 'Existen partidos iniciados: se requiere revisión del campeonato anterior.');
            if ($c->formato === 'todos_contra_todos') {
                $this->comprobarCalendarioLiga($c, $equipos->pluck('id')->all(), $config->numero_vueltas);
            } elseif ($c->formato === 'grupos_eliminacion') {
                abort_unless($c->fases()->where('tipo', 'grupos')->count() === 1, 409, 'Crea una fase de grupos antes de iniciar.');
                $fase = $c->fases()->where('tipo', 'grupos')->sole();
                $miembros = $fase->equipos()->pluck('campeonato_equipo_id')->sort()->values()->all();
                abort_unless($miembros === $equipos->pluck('id')->sort()->values()->all() && $fase->grupos()->exists(), 409, 'La distribución de grupos no coincide con los inscritos.');
                foreach ($fase->grupos as $grupo) {
                    $tabla = app(TablaGruposService::class)->calcular($grupo, true);
                    abort_unless($tabla['calendario_generado'] && $tabla['partidos_registrados'] === $tabla['partidos_esperados'], 409, 'Falta el calendario completo de grupos.');
                }
            } else {
                abort_unless($c->fases()->where('tipo', 'eliminacion')->count() === 1, 409, 'Falta una fase eliminatoria única.');
                $fase = $c->fases()->where('tipo', 'eliminacion')->sole();
                abort_unless($fase->rondas()->exists() && $fase->equipos()->pluck('campeonato_equipo_id')->sort()->values()->all() === $equipos->pluck('id')->sort()->values()->all() && $fase->partidos()->exists(), 409, 'Confirma el cuadro completo antes de iniciar.');
            }
            $antes = $c->attributesToArray();
            $c->estado = 'en_curso';
            $c->iniciado_at = now();
            $c->save();
            $integridad->auditar($c, 'campeonato', $c->id, 'iniciar_campeonato', $antes, $c->attributesToArray() + ['jugadores_elegibles' => count($jugadores)]);

            return $c;
        });
    }

    private function comprobarCalendarioLiga(Campeonato $c, array $ids, int $vueltas): void
    {
        $partidos = $c->partidos()->with('participantes')->get();
        $esperados = count($ids) * (count($ids) - 1) / 2 * $vueltas;
        abort_unless(in_array($vueltas, [1, 2], true) && $partidos->count() === (int) $esperados, 409, 'El calendario de liga está incompleto.');
        $pares = [];
        foreach ($partidos as $p) {
            $par = $p->participantes->pluck('campeonato_equipo_id')->sort()->values()->all();
            abort_unless(count($par) === 2 && $par[0] !== $par[1] && ! array_diff($par, $ids) && ! $p->fase_id, 409, 'Hay participantes inconsistentes en el calendario.');
            $clave = implode(':', $par);
            $pares[$clave] = ($pares[$clave] ?? 0) + 1;
        }
        abort_unless(count($pares) === count($ids) * (count($ids) - 1) / 2 && collect($pares)->every(fn ($cantidad) => $cantidad === $vueltas), 409, 'El calendario contiene cruces repetidos o ausentes.');
    }

    public function finalizar(Campeonato $original): array
    {
        return DB::transaction(function () use ($original) {
            $integridad = app(IntegridadCampeonatoService::class);
            $c = $integridad->bloquear($original->id);
            Gate::authorize('update', $c);
            abort_unless($c->estado === 'en_curso', 409, 'Solo se finaliza un campeonato en curso.');
            abort_if(! $c->partidos()->exists() || $c->partidos()->where(fn ($q) => $q->where('estado', '!=', 'finalizado')->orWhereNull('oficializado_at')->orWhere('tipo_resultado', '!=', 'deportivo'))->exists(), 409, 'Faltan resultados deportivos oficiales completos.');
            abort_if(AutorizacionCorreccion::whereHas('partido', fn ($q) => $q->where('campeonato_id', $c->id))->whereIn('estado', ['solicitada', 'aprobada'])->exists(), 409, 'Resuelve las correcciones pendientes antes de finalizar.');
            if ($c->formato === 'todos_contra_todos') {
                $this->comprobarCalendarioLiga($c, $c->inscripcionesEquipos()->pluck('id')->all(), $c->configuracion->numero_vueltas);
                $tabla = app(TablaPosicionesService::class)->index($c)->getData(true)['tabla'];
                $criterios = explode(',', $c->configuracion->orden_desempate);
                $columnas = ['puntos' => 'PTS', 'diferencia_goles' => 'DG', 'goles_favor' => 'GF'];
                abort_if(collect($criterios)->every(fn ($k) => $tabla[0][$columnas[$k]] === $tabla[1][$columnas[$k]]), 409, 'El primer puesto sigue empatado: no se elige campeón por nombre ni ID.');
                $ganador = $c->inscripcionesEquipos()->where('equipo_id', $tabla[0]['equipo_id'])->sole()->id;
            } else {
                if ($c->formato === 'grupos_eliminacion') {
                    $grupos = $c->fases()->where('tipo', 'grupos')->sole();
                    abort_unless(app(ClasificacionGruposService::class)->preparar($grupos)['clasificacion_vigente'], 409, 'Confirma la clasificación oficial de grupos.');
                    $fase = $c->fases()->where('fase_origen_id', $grupos->id)->sole();
                } else {
                    abort_unless($c->fases()->where('tipo', 'eliminacion')->count() === 1, 409, 'Falta una fase eliminatoria única.');
                    $fase = $c->fases()->where('tipo', 'eliminacion')->sole();
                }
                abort_unless($fase->estado === 'finalizada', 409, 'Falta resolver el cuadro eliminatorio.');
                $final = $fase->rondas()->orderByDesc('numero')->firstOrFail()->llaves()->sole();
                abort_unless($final->estado === 'resuelta' && $final->ganador_fase_equipo_id, 409, 'La final no tiene campeón oficial.');
                $ganador = FaseEquipo::findOrFail($final->ganador_fase_equipo_id)->campeonato_equipo_id;
            }
            abort_unless($c->inscripcionesEquipos()->whereKey($ganador)->where('estado', 'activo')->exists(), 409, 'El campeón no es elegible.');
            $antes = $c->attributesToArray();
            $c->estado = 'finalizado';
            $c->finalizado_at = now();
            $c->save();
            $integridad->auditar($c, 'campeonato', $c->id, 'finalizar_campeonato', $antes, $c->attributesToArray() + ['campeon_campeonato_equipo_id' => $ganador]);

            return ['campeonato' => $c, 'campeon_campeonato_equipo_id' => $ganador];
        });
    }
}
