<?php

// Solo lectura sobre la principal: compara estructuras y huellas, sin publicar sus datos.
require __DIR__.'/sprint33-preflight.php';
require __DIR__.'/sprint33-instantanea.php';
$carpeta = getenv('BEESOCC_INTEGRAL_DIR');
if (! preg_match('#/beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$#', str_replace('\\', '/', $carpeta ?: ''))) {
    throw new RuntimeException('Se requiere el directorio temporal del laboratorio.');
}
$actual = ['base' => 'beesocc_v2', 'tablas' => instantanea($pdo), 'archivos' => []];
foreach ([base_path('.env'), base_path('phpunit.xml'), base_path('../frontend/.env'), $migracion] as $archivo) {
    $actual['archivos'][basename($archivo).':'.basename(dirname($archivo))] = is_file($archivo) ? hash_file('sha256', $archivo) : null;
}
$ruta = $carpeta.'/principal-35-antes.json';
if (($argv[1] ?? '') === 'antes' && ! is_file($ruta)) {
    file_put_contents($ruta, json_encode($actual, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} elseif (($argv[1] ?? '') === 'despues') {
    if ($actual !== json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR)) {
        throw new RuntimeException('Cambió la principal o su configuración.');
    }
} else {
    throw new RuntimeException('Modo inválido o referencia preexistente.');
}
echo "\n".json_encode(['ok' => true, 'modo' => $argv[1], 'tablas' => count($actual['tablas']), 'registros' => array_sum(array_column($actual['tablas'], 'cantidad'))]);
