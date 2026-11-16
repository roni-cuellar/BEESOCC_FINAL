<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Models\Fase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GeneracionEliminacionService
{
    public function crearFase(Campeonato $campeonato, string $nombre): Fase
    {
        return DB::transaction(function () use ($campeonato, $nombre) {
            $campeonato = app(IntegridadCampeonatoService::class)->bloquear($campeonato->id);
            Gate::authorize('update', $campeonato);
            abort_unless($campeonato->formato === 'eliminacion_directa' && $campeonato->estado === 'planificacion', 409, 'Se requiere un campeonato de eliminación directa en planificación.');
            abort_if($campeonato->fases()->exists() || $campeonato->partidos()->exists(), 409, 'El campeonato ya tiene una estructura deportiva.');
            $fase = $campeonato->fases()->create(['orden' => 1, 'nombre' => $nombre, 'tipo' => 'eliminacion', 'estado' => 'planificacion']);
            app(AuditoriaEliminacionService::class)->registrar($fase, 'fase', $fase->id, 'crear_fase', 'Creación de la fase eliminatoria.', null, $fase->attributesToArray());

            return $fase;
        });
    }

    public function generar(Fase $fase, array $datos): Fase
    {
        return DB::transaction(function () use ($fase, $datos) {
            $campeonato = app(IntegridadCampeonatoService::class)->bloquear($fase->campeonato_id);
            Gate::authorize('update', $campeonato);
            $fase = Fase::whereKey($fase->id)->lockForUpdate()->firstOrFail();
            $vista = app(SiembraEliminacionService::class)->previsualizar($fase, $datos);
            abort_unless(hash_equals($vista['confirmacion'], $datos['confirmacion']), 409, 'La confirmación no coincide con la vista previa actual.');

            return $this->construir($fase, $vista);
        });
    }

    // Construcción compartida: cada formato valida antes su fuente de participantes.
    public function construir(Fase $fase, array $vista, array $procedencias = []): Fase
    {
        $participantes = [];
        foreach ($vista['siembra'] as $indice => $inscripcion) {
            $participantes[$inscripcion] = $fase->equipos()->create(['campeonato_equipo_id' => $inscripcion, 'siembra' => $indice + 1, 'estado' => 'activo'])->id;
        }
        $anteriores = [];
        foreach ($vista['rondas'] as $indice => $configuracion) {
            $ronda = $fase->rondas()->create(['numero' => $indice + 1, 'nombre' => 'Ronda '.($indice + 1),
                'modalidad' => $configuracion['modalidad'], 'desempate' => $configuracion['desempate'], 'estado' => 'planificacion']);
            $actuales = [];
            $cantidad = count($vista['cruces']) / (2 ** $indice);
            for ($numero = 1; $numero <= $cantidad; $numero++) {
                $llave = $ronda->llaves()->create(['numero' => $numero, 'estado' => 'pendiente']);
                for ($posicion = 1; $posicion <= 2; $posicion++) {
                    $inscripcion = $indice === 0 ? $vista['cruces'][$numero - 1]['inscripciones'][$posicion - 1] : null;
                    $origen = $indice === 0 && $inscripcion ? ($procedencias[$inscripcion] ?? []) : [];
                    $llave->plazas()->create(['posicion' => $posicion, 'fase_equipo_id' => $participantes[$inscripcion] ?? null,
                        'origen_tipo' => $indice > 0 ? 'ganador_llave' : ($inscripcion ? ($origen ? 'grupo_puesto' : 'directo') : 'bye'),
                        'origen_grupo_id' => $origen['origen_grupo_id'] ?? null, 'origen_puesto' => $origen['origen_puesto'] ?? null,
                        'origen_llave_id' => $indice > 0 ? $anteriores[($numero - 1) * 2 + $posicion - 1] : null]);
                }
                $actuales[] = $llave->id;
            }
            $anteriores = $actuales;
        }
        foreach ($fase->rondas()->first()->llaves as $llave) {
            app(AvanceEliminacionService::class)->preparar($llave);
        }
        app(AuditoriaEliminacionService::class)->registrar($fase, 'fase', $fase->id, 'generar_llaves', 'Confirmación administrativa de siembra y cuadro.', null, $vista);

        return $fase->load('equipos.inscripcion.equipo', 'rondas.llaves.plazas', 'rondas.llaves.partidos.participantes');
    }
}
