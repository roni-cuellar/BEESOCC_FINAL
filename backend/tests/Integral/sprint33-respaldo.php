<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/sprint33-instantanea.php';

$bloqueada = false;
$archivoCredenciales = null;
$etapa = 'configuracion';
$fallo = false;
try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $configuracion = config('database.connections.mysql');
    if ($app->configurationIsCached() || config('database.default') !== 'mysql'
        || $configuracion['database'] !== 'beesocc_v2' || ! empty($configuracion['url'])
        || $configuracion['host'] !== '127.0.0.1' || (int) $configuracion['port'] !== 3306) {
        throw new RuntimeException('Configuración principal inesperada.');
    }
    $ruta = database_path('migrations/2026_11_14_235900_add_oficializacion_and_auditoria.php');
    $evidencia = json_decode(file_get_contents(__DIR__.'/sprint32-evidencia.json'), true, flags: JSON_THROW_ON_ERROR);
    if (strtoupper(hash_file('sha256', $ruta)) !== $evidencia['sha256_migracion']) {
        throw new RuntimeException('Migración diferente.');
    }
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=beesocc_v2;charset=utf8mb4', $configuracion['username'], $configuracion['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'beesocc_v2'
        || ! str_starts_with($pdo->query('SELECT VERSION()')->fetchColumn(), '10.4.32-MariaDB')) {
        throw new RuntimeException('Identidad principal incorrecta.');
    }
    $etapa = 'objetos_y_conflictos';
    $objetos = $pdo->query("SELECT TABLE_NAME, ENGINE, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA='beesocc_v2'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($objetos as $objeto) {
        if ($objeto['ENGINE'] !== 'InnoDB' || $objeto['TABLE_TYPE'] !== 'BASE TABLE') {
            throw new RuntimeException('Objeto no cubierto por esta exportación.');
        }
    }
    foreach (['TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $tabla => $campo) {
        if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.$tabla WHERE $campo='beesocc_v2'")->fetchColumn() !== 0) {
            throw new RuntimeException('Objeto adicional pendiente de revisar.');
        }
    }
    $antes = instantanea($pdo);
    if (isset($antes['auditoria_cambios']) || isset($antes['autorizaciones_correccion'])
        || array_intersect(['tipo_resultado', 'oficializado_at'], $antes['partidos']['columnas'])) {
        throw new RuntimeException('Conflicto de esquema.');
    }
    $consulta = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
    $consulta->execute([basename($ruta, '.php')]);
    if ((int) $consulta->fetchColumn() !== 0) {
        throw new RuntimeException('Migración ya aplicada.');
    }
    $etapa = 'respaldo';
    $carpeta = storage_path('app/private/respaldos_historicos');
    if (! is_dir($carpeta) && ! mkdir($carpeta, 0700, true)) {
        throw new RuntimeException('No se pudo crear carpeta externa.');
    }
    $sufijo = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Ymd_His').'_'.bin2hex(random_bytes(4));
    $respaldo = $carpeta.'/beesocc_v2_pre_oficializacion_'.$sufijo.'.sql';
    if (file_exists($respaldo)) {
        throw new RuntimeException('El respaldo ya existe.');
    }
    $archivoCredenciales = tempnam(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')), 'cliente_');
    $escapar = fn ($valor) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $valor).'"';
    file_put_contents($archivoCredenciales, "[client]\nhost=127.0.0.1\nport=3306\nuser=".$escapar($configuracion['username'])."\npassword=".$escapar($configuracion['password'])."\n");
    // Bloqueo breve de lectura: exportación y comparación coherentes, sin DDL principal.
    $pdo->exec('LOCK TABLES '.implode(',', array_map(fn ($tabla) => '`'.$tabla.'` READ', array_keys($antes))));
    $bloqueada = true;
    $inicio = microtime(true);
    $antes = instantanea($pdo);
    $proceso = new Process(['C:/xampp/mysql/bin/mysqldump.exe', '--defaults-extra-file='.$archivoCredenciales,
        '--single-transaction', '--quick', '--skip-lock-tables', '--skip-add-drop-table', '--hex-blob', '--tz-utc', '--no-tablespaces', '--result-file='.$respaldo, 'beesocc_v2']);
    $proceso->setTimeout(60);
    $proceso->run();
    if (! $proceso->isSuccessful() || ! is_file($respaldo) || filesize($respaldo) === 0) {
        throw new RuntimeException('Exportación fallida.');
    }
    $manifiesto = ['respaldo' => $respaldo, 'sha256' => hash_file('sha256', $respaldo), 'antes' => $antes];
    $rutaManifiesto = dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/respaldo-manifiesto.json';
    file_put_contents($rutaManifiesto, json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $etapa = 'restauracion_y_migracion_aislada';
    $restaurar = new Process(['C:/xampp/php/php.exe', __DIR__.'/sprint33-restaurar.php'], base_path(), ['BEESOCC_COPIA_MANIFIESTO' => $rutaManifiesto]);
    $restaurar->setTimeout(60);
    $restaurar->run();
    $resultado = json_decode($restaurar->getOutput(), true);
    if (! $restaurar->isSuccessful() || ($resultado['ok'] ?? false) !== true) {
        throw new RuntimeException('Falló la validación de la copia.');
    }
    if (instantanea($pdo) !== $antes || strtoupper(hash_file('sha256', $ruta)) !== $evidencia['sha256_migracion']) {
        throw new RuntimeException('Cambió la principal o la migración.');
    }
    $pdo->exec('UNLOCK TABLES');
    $bloqueada = false;
    $salida = ['ok' => true, 'principal' => 'beesocc_v2', 'principal_intacta' => true, 'respaldo' => $respaldo,
        'sha256_respaldo' => $manifiesto['sha256'], 'bytes' => filesize($respaldo), 'sha256_migracion' => $evidencia['sha256_migracion'],
        'copia' => $resultado, 'segundos_bloqueo_lectura' => round(microtime(true) - $inicio, 2)];
    file_put_contents(__DIR__.'/sprint33-evidencia.json', json_encode($salida, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($salida, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'etapa' => $etapa, 'mensaje' => 'Verificación fallida; detener sin aplicar migraciones en la principal.']);
    $fallo = true;
} finally {
    if ($bloqueada) {
        $pdo->exec('UNLOCK TABLES');
    }
    if ($archivoCredenciales && is_file($archivoCredenciales)) {
        unlink($archivoCredenciales);
    }
}
exit($fallo ? 1 : 0);
