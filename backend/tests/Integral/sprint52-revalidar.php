<?php

// Solo lectura: se revalida el laboratorio existente antes de reanudar escrituras.
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$identidad = DB::selectOne('SELECT @@port AS puerto, VERSION() AS motor, @@autocommit AS autocommit');
if ($identidad->puerto !== 3317 || $identidad->motor !== '10.4.32-MariaDB') {
    throw new RuntimeException('Motor o puerto inesperados: detener.');
}
$base = DB::connection()->getDatabaseName();
foreach (DB::select('SHOW GRANTS') as $fila) {
    $permiso = array_values((array) $fila)[0];
    if (! str_starts_with($permiso, 'GRANT USAGE ON *.*') && ! str_contains($permiso, 'ON `'.$base.'`.* TO')) {
        throw new RuntimeException('Permisos fuera del laboratorio: detener.');
    }
}
try {
    DB::select('SELECT id FROM beesocc_v2.users LIMIT 1');
    throw new RuntimeException('La principal es accesible: detener.');
} catch (QueryException $error) {
    if (! in_array($error->errorInfo[1] ?? null, [1044, 1142], true)) {
        throw $error;
    }
}
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$comprobaciones = [];
foreach (['grupos' => 133, 'compatibilidad' => 57, 'correcciones' => 16] as $modo => $cantidad) {
    $evidencia = json_decode(file_get_contents($carpeta.'/sprint52-http-'.$modo.'.json'), true, flags: JSON_THROW_ON_ERROR);
    if (($evidencia['ok'] ?? false) !== true || $evidencia['comprobaciones'] !== $cantidad) {
        throw new RuntimeException('La evidencia previa no coincide: detener.');
    }
    $comprobaciones[$modo] = $cantidad;
}
$fixture = json_decode(file_get_contents($carpeta.'/sprint52-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
if (DB::table('users')->count() !== 3 || DB::table('equipos')->count() !== count($fixture['equipos'])) {
    throw new RuntimeException('Cambió el fixture del laboratorio: detener.');
}
$manual = json_decode(file_get_contents($carpeta.'/sprint52-manual.json'), true, flags: JSON_THROW_ON_ERROR);
if (DB::table('auditoria_cambios')->where('entidad_tipo', 'fase')->where('entidad_id', $manual['fase'])->where('accion', 'clasificar_grupos')->count() !== 2
    || DB::table('auditoria_cambios')->where('entidad_tipo', 'fase')->where('entidad_id', $manual['fase'])->where('accion', 'invalidar_clasificacion_grupos')->count() !== 1) {
    throw new RuntimeException('Cambió la clasificación verificada: detener.');
}
echo json_encode(['ok' => true, 'motor' => $identidad->motor, 'puerto' => $identidad->puerto,
    'rutas_y_cuenta_verificadas' => true, 'principal_inaccesible' => true, 'estado_previo_conservado' => true, 'comprobaciones_previas' => $comprobaciones]).PHP_EOL;
