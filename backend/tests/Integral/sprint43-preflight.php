<?php

// Conexión principal de solo lectura. No contiene llamadas a migrate ni DDL.
require __DIR__.'/sprint33-preflight.php';
$rutaEliminacion = database_path('migrations/2026_11_15_000100_create_estructura_eliminacion.php');
$validada = json_decode(file_get_contents(__DIR__.'/sprint42-migracion.json'), true, flags: JSON_THROW_ON_ERROR);
if (hash_file('sha256', $rutaEliminacion) !== $validada['sha256_migracion']) {
    throw new RuntimeException('La migración difiere de la validada en 4.2.');
}
$consulta = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
$consulta->execute([basename($rutaEliminacion, '.php')]);
if ((int) $consulta->fetchColumn() !== 0) {
    throw new RuntimeException('La migración ya no está pendiente.');
}
echo "\n".json_encode(['ok' => true, 'principal' => 'beesocc_v2', 'migracion_pendiente' => true, 'sha256_validado' => $validada['sha256_migracion']]);
