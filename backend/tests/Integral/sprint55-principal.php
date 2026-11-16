<?php

use Symfony\Component\Process\Process;

// Este ejecutor solo consulta y exporta la principal; nunca invoca Artisan.
require __DIR__.'/sprint33-preflight.php';
require __DIR__.'/sprint33-instantanea.php';

$etapa = 'preflight';
$credenciales = null;
try {
    $modo = $argv[1] ?? '';
    $carpeta = realpath(getenv('BEESOCC_BACKUP_DIR') ?: '');
    $proyecto = realpath(dirname(__DIR__, 3));
    if (! in_array($modo, ['preparar', 'comparar'], true) || ! $carpeta || ! is_writable($carpeta)
        || str_starts_with(strtolower(str_replace('\\', '/', $carpeta)).'/', strtolower(str_replace('\\', '/', $proyecto)).'/')
        || $configuracion['host'] !== '127.0.0.1' || (int) $configuracion['port'] !== 3306) {
        throw new RuntimeException('Modo, carpeta externa o servidor inesperados.');
    }
    $nombre = '2026_11_15_000200_create_desempates_grupos';
    $ruta = database_path('migrations/'.$nombre.'.php');
    $sha = hash_file('sha256', $ruta);
    if ($sha !== 'c48ee2d69c8a6b1c3322401b4f7573e2120833c57b84d5836c90619ba70baebb') {
        throw new RuntimeException('El código difiere del validado.');
    }
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    $aplicadas = $pdo->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
    $archivos = array_map(fn ($p) => basename($p, '.php'), glob(database_path('migrations/*.php')));
    $pendientes = array_values(array_diff($archivos, $aplicadas));
    sort($pendientes);
    if ($pendientes !== ['2026_11_12_224943_create_personal_access_tokens_table', $nombre]
        || array_diff($aplicadas, $archivos)) {
        throw new RuntimeException('Historial de migraciones inesperado.');
    }
    $antes = instantanea($pdo);
    if (isset($antes['grupo_desempates'])
        || isset($antes['personal_access_tokens']) || in_array('fase_origen_id', $antes['fases']['columnas'], true)
        || in_array('clasificacion_huella', $antes['fases']['columnas'], true)
        || in_array('grupo_desempate_id', $antes['partidos']['columnas'], true)) {
        throw new RuntimeException('Esquema parcialmente migrado o tokens presentes.');
    }
    foreach ($pdo->query("SELECT ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA='beesocc_v2'")->fetchAll(PDO::FETCH_ASSOC) as $objeto) {
        if ($objeto['ENGINE'] !== 'InnoDB' || $objeto['TABLE_TYPE'] !== 'BASE TABLE') {
            throw new RuntimeException('Objeto no cubierto por el respaldo.');
        }
    }
    foreach (['TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $tabla => $campo) {
        if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.$tabla WHERE $campo='beesocc_v2'")->fetchColumn() !== 0) {
            throw new RuntimeException('Objetos adicionales requieren revisión.');
        }
    }
    $pdo->commit();
    $manifiesto = $carpeta.'/sprint55-respaldo.json';
    if ($modo === 'preparar') {
        if (is_file($manifiesto)) {
            throw new RuntimeException('No se sobrescribe un respaldo previo.');
        }
        $etapa = 'exportacion';
        $respaldo = $carpeta.'/beesocc_v2_pre_grupos_desempates.sql';
        if (is_file($respaldo)) {
            throw new RuntimeException('Ya existe el SQL.');
        }
        $credenciales = tempnam($carpeta, 'cliente55_');
        $escapar = fn ($valor) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $valor).'"';
        file_put_contents($credenciales, "[client]\nhost=127.0.0.1\nport=3306\nuser=".$escapar($configuracion['username'])."\npassword=".$escapar($configuracion['password'])."\n");
        $proceso = new Process(['C:/xampp/mysql/bin/mysqldump.exe', '--defaults-extra-file='.$credenciales,
            '--single-transaction', '--quick', '--skip-lock-tables', '--skip-add-locks', '--skip-add-drop-table', '--hex-blob', '--tz-utc', '--no-tablespaces', '--result-file='.$respaldo, 'beesocc_v2']);
        $proceso->setTimeout(60);
        $proceso->run();
        if (! $proceso->isSuccessful() || ! is_file($respaldo) || filesize($respaldo) === 0) {
            throw new RuntimeException('Exportación SQL fallida.');
        }
        $pdo->beginTransaction();
        $igual = instantanea($pdo) === $antes;
        $pdo->commit();
        if (! $igual) {
            throw new RuntimeException('La principal cambió durante el respaldo.');
        }
        $referencia = ['respaldo' => $respaldo, 'sha256_respaldo' => hash_file('sha256', $respaldo), 'sha256_migracion' => $sha,
            'antes' => $antes, 'migraciones' => $aplicadas, 'pendientes' => $pendientes];
        file_put_contents($manifiesto, json_encode($referencia, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    } else {
        $etapa = 'comparacion';
        $referencia = json_decode(file_get_contents($manifiesto), true, flags: JSON_THROW_ON_ERROR);
        if ($referencia['antes'] !== $antes || $referencia['migraciones'] !== $aplicadas
            || $referencia['sha256_migracion'] !== $sha || hash_file('sha256', $referencia['respaldo']) !== $referencia['sha256_respaldo']) {
            throw new RuntimeException('Cambió la principal, la migración o el respaldo.');
        }
    }
    $resultado = ['ok' => true, 'modo' => $modo, 'principal' => 'beesocc_v2', 'servidor' => '127.0.0.1:3306', 'solo_lectura' => true,
        'tablas' => count($antes), 'registros' => array_sum(array_column($antes, 'cantidad')), 'aplicadas' => count($aplicadas), 'pendientes' => $pendientes,
        'sha256_migracion' => $sha, 'sha256_respaldo' => $referencia['sha256_respaldo'], 'bytes_respaldo' => filesize($referencia['respaldo'])];
    file_put_contents($carpeta.'/sprint55-principal-'.$modo.'.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo PHP_EOL.json_encode($resultado).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Falló la preparación en '.$etapa.': '.$error->getMessage().PHP_EOL);
    exit(1);
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($credenciales && is_file($credenciales)) {
        unlink($credenciales);
    }
}
