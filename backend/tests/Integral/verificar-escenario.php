<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();

use Illuminate\Support\Facades\DB;

$fase = $argv[1] ?? '';
$esperados = ['gol' => ['en_curso', 1], 'eventos' => ['en_curso', 6],
    'eliminado' => ['en_curso', 5], 'finalizado' => ['finalizado', 6]];
if (! isset($esperados[$fase])) {
    throw new RuntimeException('Indica gol, eventos, eliminado o finalizado.');
}
$partido = DB::table('partidos')->find(1);
$tipos = DB::table('eventos_estadisticos')->selectRaw('tipo, COUNT(*) AS cantidad')->groupBy('tipo')->pluck('cantidad', 'tipo')->all();
if ($partido->estado !== $esperados[$fase][0] || array_sum($tipos) !== $esperados[$fase][1]) {
    throw new RuntimeException('El estado o la cantidad de eventos no coincide.');
}
$registros = array_map(fn ($linea) => json_decode($linea, true, flags: JSON_THROW_ON_ERROR), file(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/http.jsonl', FILE_IGNORE_NEW_LINES));
$altas = array_filter($registros, fn ($r) => $r['metodo'] === 'POST' && $r['ruta'] === '/api/partidos/1/eventos' && $r['estado'] === 201);
if ($fase === 'gol' && count($altas) !== 1) {
    throw new RuntimeException('El doble clic produjo más de una petición de alta exitosa.');
}
echo json_encode(['fase' => $fase, 'base' => DB::selectOne('SELECT DATABASE() AS base')->base,
    'estado' => $partido->estado, 'eventos_por_tipo' => $tipos, 'altas_http_201' => count($altas),
    'sesiones_reales' => DB::table('sessions')->whereNotNull('user_id')->count()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
