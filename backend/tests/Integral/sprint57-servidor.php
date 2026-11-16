<?php

use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';

// Solo atiende después de validar la identidad y el aislamiento del laboratorio.
$app = iniciarEntornoIntegral();
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$archivoRetardo = $carpeta.'/sprint57-retardo.json';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_file($archivoRetardo)) {
    $retardo = json_decode(file_get_contents($archivoRetardo), true, flags: JSON_THROW_ON_ERROR);
    if (($retardo['ruta'] ?? '') === $ruta && ($retardo['milisegundos'] ?? 0) > 0 && $retardo['milisegundos'] <= 1500) {
        // Se retrasa una respuesta real; nunca se sustituye por datos simulados.
        usleep($retardo['milisegundos'] * 1000);
        file_put_contents($carpeta.'/sprint57-retardos.jsonl', json_encode(['ruta' => $ruta, 'milisegundos' => $retardo['milisegundos']]).PHP_EOL, FILE_APPEND);
    }
}
register_shutdown_function(function () use ($carpeta, $ruta) {
    file_put_contents($carpeta.'/http.jsonl', json_encode([
        'metodo' => $_SERVER['REQUEST_METHOD'], 'ruta' => $ruta,
        'estado' => http_response_code(), 'base' => config('database.connections.mysql.database'),
    ]).PHP_EOL, FILE_APPEND);
});
$app->handleRequest(Request::capture());
