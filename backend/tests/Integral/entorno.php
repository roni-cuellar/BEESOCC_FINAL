<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

function iniciarEntornoIntegral(): Application
{
    $ruta = getenv('BEESOCC_INTEGRAL_CONFIG');
    if (! $ruta || ! is_file($ruta)) {
        throw new RuntimeException('Falta la configuración temporal aislada.');
    }
    $entorno = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    if (! preg_match('/^beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/', $entorno['DB_DATABASE'] ?? '')
        || ! str_starts_with($entorno['DB_USERNAME'] ?? '', 'beesocc23c_')
        || ($entorno['APP_ENV'] ?? '') !== 'integral') {
        throw new RuntimeException('La configuración no corresponde a este entorno aislado.');
    }
    foreach ($entorno as $nombre => $valor) {
        putenv($nombre.'='.$valor);
        $_ENV[$nombre] = $_SERVER[$nombre] = $valor;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->addAbsoluteCachePathPrefix(substr(dirname($ruta), 0, 2));
    $app->useEnvironmentPath(dirname($ruta));
    $app->useStoragePath(dirname($ruta).'/storage');
    $app->make(Kernel::class)->bootstrap();
    if ($app->configurationIsCached() || ! $app->environment('integral')) {
        throw new RuntimeException('Entorno o caché inesperados.');
    }

    // Ningún alias puede recuperar la conexión original de desarrollo.
    $conexion = config('database.connections.mysql');
    $conexion['name'] = 'mysql';
    config(['database.default' => 'mysql', 'database.connections' => ['mysql' => $conexion]]);
    DB::purge();
    $activa = DB::selectOne('SELECT DATABASE() AS base, CURRENT_USER() AS cuenta, @@datadir AS datos, @@innodb_log_group_home_dir AS redo, @@innodb_data_home_dir AS innodb, @@aria_log_dir_path AS aria');
    if ($activa->base !== $entorno['DB_DATABASE'] || ! str_starts_with($activa->cuenta, $entorno['DB_USERNAME'].'@')) {
        throw new RuntimeException('La base o cuenta activa no coincide con el aislamiento.');
    }
    foreach (['datos', 'redo', 'innodb', 'aria'] as $rutaMotor) {
        $directorio = rtrim(str_replace('\\', '/', $activa->{$rutaMotor} ?? ''), '/');
        if (strcasecmp($directorio, $entorno['BEESOCC_MARIADB_DATADIR']) !== 0) {
            throw new RuntimeException('Un archivo del motor no está en el directorio aislado.');
        }
    }

    return $app;
}
