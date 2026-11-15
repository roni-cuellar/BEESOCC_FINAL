<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DistribucionGruposService
{
    public function bloquear(Fase $fase): Fase
    {
        Campeonato::whereKey($fase->campeonato_id)->lockForUpdate()->firstOrFail();
        $fase = Fase::whereKey($fase->id)->lockForUpdate()->firstOrFail();
        Gate::authorize('update', $fase);
        abort_unless($fase->tipo === 'grupos' && $fase->campeonato->formato === 'grupos_eliminacion', 409, 'Se requiere una fase de grupos del formato grupos_eliminacion.');

        return $fase;
    }

    public function crearFase(Campeonato $campeonato, string $nombre): Fase
    {
        return DB::transaction(function () use ($campeonato, $nombre) {
            $campeonato = Campeonato::whereKey($campeonato->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $campeonato);
            abort_unless($campeonato->formato === 'grupos_eliminacion' && $campeonato->estado === 'planificacion', 409, 'Se requiere un campeonato de grupos en planificación.');
            abort_if($campeonato->fases()->exists() || $campeonato->partidos()->exists(), 409, 'El campeonato ya tiene una estructura deportiva.');
            $fase = $campeonato->fases()->create(['nombre' => $nombre, 'orden' => 1, 'tipo' => 'grupos', 'estado' => 'planificacion']);
            $this->auditar($fase, 'crear_fase_grupos', 'Creación de fase de grupos.', null, $fase->attributesToArray());

            return $fase;
        });
    }

    private function comprobarEditable(Fase $fase): void
    {
        abort_unless($fase->estado === 'planificacion' && $fase->campeonato->estado === 'planificacion'
            && ! $fase->grupos()->exists() && ! $fase->equipos()->exists() && ! $fase->campeonato->partidos()->exists(), 409, 'La distribución ya fue confirmada o el campeonato comenzó.');
    }

    private function preparar(Fase $fase, array $datos, bool $sortear): array
    {
        $this->comprobarEditable($fase);
        $activos = $fase->campeonato->inscripcionesEquipos()->where('estado', 'activo')->orderBy('id')->lockForUpdate()->get();
        foreach ($activos as $inscripcion) {
            Gate::authorize('update', $inscripcion);
        }
        $ids = $activos->modelKeys();
        $cantidad = (int) $datos['cantidad_grupos'];
        $cupo = (int) $datos['clasificados_por_grupo'];
        abort_unless(count($ids) >= 2 && $cantidad >= 1 && $cantidad <= count($ids)
            && $cantidad <= 65535 && $cupo >= 1 && $cupo <= 255 && $cantidad * $cupo >= 2, 422, 'Cantidad de grupos o cupos incompatible con los inscritos y la eliminación posterior.');
        if ($sortear && $datos['metodo'] === 'automatico') {
            abort_if(isset($datos['grupos']), 422, 'La vista previa automática no recibe una distribución impuesta.');
            $orden = $ids;
            // Sorteo sin preferencias; el contenido sorteado queda firmado y auditado.
            for ($i = count($orden) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$orden[$i], $orden[$j]] = [$orden[$j], $orden[$i]];
            }
            $grupos = array_fill(0, $cantidad, []);
            foreach ($orden as $i => $id) {
                $grupos[$i % $cantidad][] = $id;
            }
        } else {
            abort_unless(isset($datos['grupos']) && count($datos['grupos']) === $cantidad, 422, 'Indica exactamente la distribución de todos los grupos.');
            $grupos = array_map(fn ($grupo) => array_map('intval', $grupo), $datos['grupos']);
        }
        $enviados = array_merge(...$grupos);
        sort($enviados);
        abort_unless($enviados === $ids && count(array_unique($enviados)) === count($ids), 422, 'Cada inscripción activa del campeonato debe aparecer exactamente una vez.');
        $tamanos = array_map('count', $grupos);
        abort_unless(min($tamanos) >= $cupo, 422, 'Todos los grupos deben tener equipos suficientes para el cupo uniforme.');
        abort_if($datos['metodo'] === 'automatico' && max($tamanos) - min($tamanos) > 1, 422, 'La distribución automática debe estar equilibrada.');
        $vista = ['fase_id' => $fase->id, 'metodo' => $datos['metodo'], 'cantidad_grupos' => $cantidad,
            'clasificados_por_grupo' => $cupo, 'grupos' => $grupos];

        return $vista + ['confirmacion' => hash_hmac('sha256', json_encode($vista, JSON_THROW_ON_ERROR), config('app.key'))];
    }

    public function previsualizar(Fase $fase, array $datos): array
    {
        return DB::transaction(fn () => $this->preparar($this->bloquear($fase), $datos, true));
    }

    public function confirmar(Fase $fase, array $datos): Fase
    {
        return DB::transaction(function () use ($fase, $datos) {
            $fase = $this->bloquear($fase);
            // No se repite el sorteo: se verifica exactamente la vista previa recibida.
            $vista = $this->preparar($fase, $datos, false);
            abort_unless(hash_equals($vista['confirmacion'], $datos['confirmacion']), 409, 'La confirmación no coincide con la distribución actual.');
            $fase->update(['clasificados_por_grupo' => $vista['clasificados_por_grupo']]);
            foreach ($vista['grupos'] as $i => $inscripciones) {
                $grupo = $fase->grupos()->create(['codigo' => 'G'.($i + 1), 'orden' => $i + 1]);
                foreach ($inscripciones as $id) {
                    $fase->equipos()->create(['grupo_id' => $grupo->id, 'campeonato_equipo_id' => $id, 'estado' => 'activo']);
                }
            }
            $this->auditar($fase, 'confirmar_grupos', 'Distribución confirmada por el administrador.', null, $vista);

            return $fase->load('grupos.equipos.inscripcion.equipo');
        });
    }

    public function generarCalendario(Fase $fase): array
    {
        return DB::transaction(function () use ($fase) {
            $fase = $this->bloquear($fase);
            abort_unless($fase->estado === 'planificacion' && $fase->campeonato->estado === 'planificacion' && $fase->grupos()->exists(), 409, 'Confirma los grupos antes de generar el calendario.');
            abort_if($fase->partidos()->exists() || AuditoriaCambio::where('entidad_tipo', 'fase')->where('entidad_id', $fase->id)->where('accion', 'generar_calendario_grupos')->exists(), 409, 'El calendario de grupos ya fue generado.');
            $miembros = $fase->equipos()->orderBy('campeonato_equipo_id')->get();
            $activos = $fase->campeonato->inscripcionesEquipos()->where('estado', 'activo')->orderBy('id')->lockForUpdate()->pluck('id')->all();
            abort_unless($miembros->pluck('campeonato_equipo_id')->all() === $activos, 409, 'Cambió la elegibilidad de las inscripciones; no se generan adjudicaciones automáticas.');
            $gruposIds = $fase->grupos()->pluck('id')->all();
            abort_if($miembros->contains(fn ($miembro) => $miembro->estado !== 'activo' || ! in_array($miembro->grupo_id, $gruposIds, true)), 409, 'La pertenencia de los equipos a los grupos es inconsistente.');
            $creados = 0;
            $descansos = [];
            foreach ($fase->grupos as $grupo) {
                $rotacion = $grupo->equipos()->pluck('campeonato_equipo_id')->all();
                abort_unless(count($rotacion) >= $fase->clasificados_por_grupo && $grupo->equipos()->where('fase_id', '!=', $fase->id)->doesntExist(), 409, 'Pertenencia de grupo inconsistente.');
                if (count($rotacion) % 2) {
                    $rotacion[] = null;
                }
                $tamano = count($rotacion);
                for ($jornada = 1; $jornada < $tamano; $jornada++) {
                    for ($i = 0; $i < $tamano / 2; $i++) {
                        [$local, $visitante] = [$rotacion[$i], $rotacion[$tamano - 1 - $i]];
                        if ($local === null || $visitante === null) {
                            $descansos[] = ['grupo_id' => $grupo->id, 'jornada' => $jornada, 'campeonato_equipo_id' => $local ?? $visitante];

                            continue;
                        }
                        if ($jornada % 2 === 0) {
                            [$local, $visitante] = [$visitante, $local];
                        }
                        $partido = new Partido(['campeonato_id' => $fase->campeonato_id, 'jornada' => $jornada, 'estado' => 'programado']);
                        $partido->fase_id = $fase->id;
                        $partido->grupo_id = $grupo->id;
                        $partido->save();
                        $partido->participantes()->createMany([
                            ['campeonato_equipo_id' => $local, 'rol' => 'local'],
                            ['campeonato_equipo_id' => $visitante, 'rol' => 'visitante'],
                        ]);
                        $creados++;
                    }
                    $ultimo = array_pop($rotacion);
                    array_splice($rotacion, 1, 0, [$ultimo]);
                }
            }
            $resultado = ['fase_id' => $fase->id, 'vueltas' => 1, 'partidos_creados' => $creados, 'descansos' => $descansos];
            $this->auditar($fase, 'generar_calendario_grupos', 'Una vuelta dentro de cada grupo, sin partidos ficticios.', null, $resultado);

            return $resultado + ['partidos' => $fase->partidos()->with('participantes.campeonatoEquipo.equipo')->orderBy('grupo_id')->orderBy('jornada')->get()];
        });
    }

    public function auditar(Fase $fase, string $accion, string $motivo, ?array $antes, array $despues): void
    {
        app(AuditoriaEliminacionService::class)->registrar($fase, 'fase', $fase->id, $accion, $motivo, $antes, $despues);
    }
}
