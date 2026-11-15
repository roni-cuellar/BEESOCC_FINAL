<?php

use Symfony\Component\Process\Process;

// La conexión principal se usa exclusivamente para lectura y exportación.
require __DIR__.'/sprint33-preflight.php';
require __DIR__.'/sprint33-instantanea.php';
$entorno = getenv('BEESOCC_INTEGRAL_CONFIG');
if (! $entorno || ! preg_match('#/beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}/entorno.json$#', str_replace('\\', '/', $entorno))) {
    throw new RuntimeException('Falta la carpeta nueva del laboratorio.');
}
$manifiesto = dirname($entorno).'/sprint42-respaldo.json';
if (is_file($manifiesto)) {
    throw new RuntimeException('No se sobrescribe un respaldo previo.');
}
$objetos = $pdo->query("SELECT ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA='beesocc_v2'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($objetos as $objeto) {
    if ($objeto['ENGINE'] !== 'InnoDB' || $objeto['TABLE_TYPE'] !== 'BASE TABLE') {
        throw new RuntimeException('Objeto no cubierto por esta exportación.');
    }
}
foreach (['TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $tabla => $campo) {
    if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.$tabla WHERE $campo='beesocc_v2'")->fetchColumn() !== 0) {
        throw new RuntimeException('Se requiere revisar objetos adicionales antes de exportar.');
    }
}
$directorioConfigurado = getenv('BEESOCC_BACKUP_DIR');
$dumpConfigurado = getenv('BEESOCC_MYSQLDUMP_BINARY');
if (! $directorioConfigurado || ! $dumpConfigurado) {
    throw new RuntimeException('Falta configurar BEESOCC_BACKUP_DIR o BEESOCC_MYSQLDUMP_BINARY.');
}
$directorioRespaldo = realpath($directorioConfigurado);
$ejecutableDump = realpath($dumpConfigurado);
$raizProyecto = realpath(dirname(__DIR__, 3));
if (! $directorioRespaldo || ! is_dir($directorioRespaldo) || ! is_writable($directorioRespaldo)
    || str_starts_with(strtolower(str_replace('\\', '/', $directorioRespaldo)).'/', strtolower(str_replace('\\', '/', $raizProyecto)).'/')
    || ! $ejecutableDump || ! is_file($ejecutableDump)) {
    throw new RuntimeException('Define BEESOCC_BACKUP_DIR fuera del proyecto y BEESOCC_MYSQLDUMP_BINARY con un ejecutable válido.');
}
$respaldo = $directorioRespaldo.'/beesocc_42_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.sql';
$credenciales = tempnam(dirname($entorno), 'cliente42_');
$escapar = fn ($valor) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $valor).'"';
file_put_contents($credenciales, "[client]\nhost=".$escapar($configuracion['host'])."\nport=".$configuracion['port']."\nuser=".$escapar($configuracion['username'])."\npassword=".$escapar($configuracion['password'])."\n");
$bloqueada = false;
try {
    $antes = instantanea($pdo);
    if (isset($antes['fases'])) {
        throw new RuntimeException('La estructura eliminatoria ya existe en la principal.');
    }
    $pdo->exec('LOCK TABLES '.implode(',', array_map(fn ($tabla) => '`'.$tabla.'` READ', array_keys($antes))));
    $bloqueada = true;
    $antes = instantanea($pdo);
    $proceso = new Process([$ejecutableDump, '--defaults-extra-file='.$credenciales,
        '--single-transaction', '--quick', '--skip-lock-tables', '--skip-add-drop-table', '--hex-blob', '--tz-utc', '--no-tablespaces', '--result-file='.$respaldo, 'beesocc_v2']);
    $proceso->setTimeout(60);
    $proceso->run();
    if (! $proceso->isSuccessful() || ! is_file($respaldo) || filesize($respaldo) === 0 || instantanea($pdo) !== $antes) {
        throw new RuntimeException('Exportación o comparación fallida.');
    }
    $evidencia = ['respaldo' => $respaldo, 'sha256' => hash_file('sha256', $respaldo), 'antes' => $antes,
        'sha256_migracion' => hash_file('sha256', database_path('migrations/2026_11_15_000100_create_estructura_eliminacion.php'))];
    file_put_contents($manifiesto, json_encode($evidencia, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "\n".json_encode(['ok' => true, 'respaldo' => $respaldo, 'tablas' => count($antes), 'registros' => array_sum(array_column($antes, 'cantidad'))]);
} finally {
    if ($bloqueada) {
        $pdo->exec('UNLOCK TABLES');
    }
    unlink($credenciales);
}
