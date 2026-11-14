<?php

$antes = json_decode(file_get_contents(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/principal_antes.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($antes as $archivo) {
    if (! is_file($archivo['ruta']) || filesize($archivo['ruta']) !== $archivo['bytes']
        || strtoupper(hash_file('sha256', $archivo['ruta'])) !== $archivo['sha256']) {
        throw new RuntimeException('Cambió un archivo de la principal; detener y revisar.');
    }
}
$cantidadOriginal = count(array_filter($antes, fn ($archivo) => str_contains(str_replace('\\', '/', $archivo['ruta']), '/data/beesocc_v2/')));
if (count(glob('C:/xampp/mysql/data/beesocc_v2/*')) !== $cantidadOriginal) {
    throw new RuntimeException('Cambió el inventario de archivos de la principal.');
}
echo 'Principal sin cambios: '.count($antes).' archivos con tamaño y SHA-256 idénticos; inventario conservado.'.PHP_EOL;
