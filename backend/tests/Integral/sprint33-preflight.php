<?php

use Illuminate\Contracts\Console\Kernel;

// Solo lectura: no ejecuta migraciones ni modifica la configuración de Laravel.
$etapa = 'configuracion';
try {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if ($app->configurationIsCached() || config('database.default') !== 'mysql') {
        throw new RuntimeException('Configuración inesperada o en caché.');
    }
    $configuracion = config('database.connections.mysql');
    if ($configuracion['database'] !== 'beesocc_v2' || ! empty($configuracion['url'])) {
        throw new RuntimeException('No se identificó inequívocamente la conexión principal.');
    }
    $etapa = 'huella_migracion';
    $migracion = database_path('migrations/2026_11_14_235900_add_oficializacion_and_auditoria.php');
    $evidencia = json_decode(file_get_contents(__DIR__.'/sprint32-evidencia.json'), true, flags: JSON_THROW_ON_ERROR);
    if (strtoupper(hash_file('sha256', $migracion)) !== $evidencia['sha256_migracion']) {
        throw new RuntimeException('La migración difiere de la versión validada.');
    }
    $etapa = 'conexion_principal';
    $pdo = new PDO('mysql:host='.$configuracion['host'].';port='.$configuracion['port'].';dbname=beesocc_v2;charset=utf8mb4',
        $configuracion['username'], $configuracion['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
    $identidad = $pdo->query('SELECT DATABASE() AS base, VERSION() AS motor')->fetch(PDO::FETCH_ASSOC);
    if ($identidad['base'] !== 'beesocc_v2' || ! str_starts_with($identidad['motor'], '10.4.32-MariaDB')) {
        throw new RuntimeException('El destino no corresponde a la principal esperada.');
    }
    echo json_encode(['ok' => true, 'base' => $identidad['base'], 'motor' => $identidad['motor'],
        'host' => $configuracion['host'], 'puerto' => $configuracion['port'], 'migracion_identica' => true], JSON_PRETTY_PRINT);
} catch (Throwable $error) {
    // No se muestran DSN, usuarios, contraseñas ni trazas con configuración privada.
    echo json_encode(['ok' => false, 'etapa' => $etapa,
        'motivo' => $etapa === 'conexion_principal' ? 'No se pudo confirmar la conexión y la identidad de la principal.' : 'Falló una verificación previa; detener sin escrituras.'], JSON_PRETTY_PRINT);
    exit(1);
}
