<?php

namespace App\Services;

use App\Models\Fase;
use Illuminate\Support\Facades\Gate;

class SiembraEliminacionService
{
    public function previsualizar(Fase $fase, array $datos): array
    {
        $campeonato = $fase->campeonato;
        abort_unless($campeonato->formato === 'eliminacion_directa' && $fase->tipo === 'eliminacion', 409, 'La fase no es de eliminación directa.');
        abort_unless($campeonato->estado === 'planificacion' && $fase->estado === 'planificacion'
            && ! $fase->rondas()->exists() && ! $campeonato->partidos()->exists(), 409, 'La estructura ya fue confirmada o el campeonato ya comenzó.');
        $inscripciones = $campeonato->inscripcionesEquipos()->where('estado', 'activo')->with('equipo')->lockForUpdate()->get();
        foreach ($inscripciones as $inscripcion) {
            Gate::authorize('update', $inscripcion);
        }
        $orden = array_map('intval', $datos['siembra']);
        $activos = $inscripciones->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $enviados = $orden;
        sort($enviados);
        abort_unless($enviados === $activos && count(array_unique($orden)) === count($orden), 422, 'La siembra debe incluir exactamente una vez todas las inscripciones activas del campeonato.');
        $capacidad = 2;
        $rondas = 1;
        while ($capacidad < count($orden)) {
            $capacidad *= 2;
            $rondas++;
        }
        abort_unless(count($datos['rondas']) === $rondas, 422, 'Debes configurar todas las rondas del cuadro.');
        // Expansión simétrica: 1–8, 4–5, 2–7, 3–6 para ocho plazas.
        $posiciones = [1, 2];
        for ($tamano = 4; $tamano <= $capacidad; $tamano *= 2) {
            $nuevas = [];
            foreach ($posiciones as $posicion) {
                $nuevas[] = $posicion;
                $nuevas[] = $tamano + 1 - $posicion;
            }
            $posiciones = $nuevas;
        }
        $cruces = [];
        foreach (array_chunk($posiciones, 2) as $indice => $par) {
            $cruces[] = ['numero' => $indice + 1, 'siembras' => $par,
                'inscripciones' => array_map(fn ($siembra) => $orden[$siembra - 1] ?? null, $par)];
        }
        $configuracion = ['fase_id' => $fase->id, 'siembra' => $orden, 'rondas' => $datos['rondas'], 'cruces' => $cruces];

        return $configuracion + ['confirmacion' => hash_hmac('sha256', json_encode($configuracion), config('app.key'))];
    }
}
