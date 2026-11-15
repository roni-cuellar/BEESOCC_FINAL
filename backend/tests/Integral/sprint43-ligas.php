<?php

use App\Models\Campeonato;
use App\Services\EstadisticaService;
use App\Services\TablaPosicionesService;

// Solo consulta la copia; conserva hashes, no nombres ni datos deportivos privados.
function consultarLigas43(): array
{
    $resultado = [];
    foreach (Campeonato::where('formato', 'todos_contra_todos')->orderBy('id')->get() as $liga) {
        $tabla = app(TablaPosicionesService::class)->index($liga);
        if ($tabla->getStatusCode() !== 200) {
            throw new RuntimeException('No se pudo consultar una tabla histórica.');
        }
        $estadisticas = [];
        foreach (['goleadores', 'asistencias', 'amarillas', 'rojas', 'faltas', 'equipos'] as $tipo) {
            $respuesta = app(EstadisticaService::class)->{$tipo}($liga);
            if ($respuesta->getStatusCode() !== 200) {
                throw new RuntimeException('No se pudo consultar una estadística histórica.');
            }
            $estadisticas[$tipo] = hash('sha256', $respuesta->getContent());
        }
        $resultado[] = ['campeonato_id' => $liga->id, 'tabla_sha256' => hash('sha256', $tabla->getContent()), 'estadisticas_sha256' => $estadisticas];
    }

    return $resultado;
}
