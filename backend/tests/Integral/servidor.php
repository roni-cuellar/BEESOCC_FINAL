<?php

use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';

// Se comprueban destino y cuenta antes de atender cada petición HTTP real.
$app = iniciarEntornoIntegral();
register_shutdown_function(function () {
    // Evidencia técnica del ensayo: sin cuerpos, contraseñas ni cookies.
    $registro = ['metodo' => $_SERVER['REQUEST_METHOD'], 'ruta' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
        'estado' => http_response_code(), 'base' => config('database.connections.mysql.database')];
    file_put_contents(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/http.jsonl', json_encode($registro).PHP_EOL, FILE_APPEND);
});
$app->handleRequest(Request::capture());
