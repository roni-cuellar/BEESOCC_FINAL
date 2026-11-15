<?php

// La principal solo admite SELECT y ajustes de sesión; nunca DDL ni DML.
require __DIR__.'/sprint33-preflight.php';
require __DIR__.'/sprint33-instantanea.php';
$carpeta = str_replace('\\', '/', getenv('BEESOCC_INTEGRAL_DIR') ?: '');
if (! preg_match('#/beesocc_23c_(?:test|mariadb)_\d{8}_\d{6}_[a-f0-9]{8}$#', $carpeta)) {
    throw new RuntimeException('Directorio de evidencia inesperado.');
}
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$actual = ['base' => 'beesocc_v2', 'tablas' => instantanea($pdo), 'archivos' => []];
foreach ([base_path('.env'), base_path('phpunit.xml'), base_path('../frontend/.env'), database_path('migrations/2026_11_15_000100_create_estructura_eliminacion.php')] as $archivo) {
    $actual['archivos'][basename(dirname($archivo)).'/'.basename($archivo)] = is_file($archivo) ? hash_file('sha256', $archivo) : null;
}
$ruta = $carpeta.'/principal-45-antes.json';
if (($argv[1] ?? '') === 'antes' && ! is_file($ruta)) {
    file_put_contents($ruta, json_encode($actual, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} elseif (($argv[1] ?? '') === 'despues') {
    if ($actual !== json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR)) {
        throw new RuntimeException('Cambió la principal o su configuración: detener.');
    }
} else {
    throw new RuntimeException('Modo inválido o referencia ya existente.');
}
$resultado = ['ok' => true, 'modo' => $argv[1], 'base' => 'beesocc_v2', 'tablas' => count($actual['tablas']),
    'registros' => array_sum(array_column($actual['tablas'], 'cantidad')), 'solo_lectura' => true];
if ($argv[1] === 'despues') {
    file_put_contents(__DIR__.'/sprint45-principal.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
echo PHP_EOL.json_encode($resultado).PHP_EOL;
