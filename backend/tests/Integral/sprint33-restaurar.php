<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
require __DIR__.'/sprint33-instantanea.php';

try {
    iniciarEntornoIntegral();
    $pdo = DB::connection()->getPdo();
    if ($pdo->query('SHOW TABLES')->fetchAll()) {
        throw new RuntimeException('La copia debe estar vacía.');
    }
    $manifiesto = json_decode(file_get_contents(getenv('BEESOCC_COPIA_MANIFIESTO')), true, flags: JSON_THROW_ON_ERROR);
    $sql = file_get_contents($manifiesto['respaldo']);
    if (preg_match('/(?:CREATE|DROP)\s+DATABASE|\bUSE\s+|`beesocc_v2`\s*\./i', $sql)
        || hash('sha256', $sql) !== $manifiesto['sha256']) {
        throw new RuntimeException('Respaldo inesperado.');
    }
    $conexion = config('database.connections.mysql');
    $mysqli = new mysqli($conexion['host'], $conexion['username'], $conexion['password'], $conexion['database'], (int) $conexion['port']);
    $mysqli->multi_query($sql);
    do {
        if ($resultado = $mysqli->store_result()) {
            $resultado->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
    $mysqli->close();
    if (instantanea($pdo) !== $manifiesto['antes']) {
        throw new RuntimeException('La restauración no coincide.');
    }
    // Se ejecuta únicamente la migración autorizada para validar la copia nueva.
    $ruta = 'database/migrations/2026_11_14_235900_add_oficializacion_and_auditoria.php';
    $codigo = Artisan::call('migrate', ['--database' => 'mysql', '--path' => $ruta, '--force' => true]);
    if ($codigo !== 0) {
        throw new RuntimeException('Falló la migración aislada.');
    }
    $despues = instantanea($pdo, $manifiesto['antes']);
    foreach ($manifiesto['antes'] as $tabla => $original) {
        if ($tabla === 'migrations') {
            $nombre = basename($ruta, '.php');
            $pdo->beginTransaction();
            $consulta = $pdo->prepare('DELETE FROM migrations WHERE migration = ?');
            $consulta->execute([$nombre]);
            $comparacion = instantanea($pdo, $manifiesto['antes']);
            $pdo->rollBack();
            if ($consulta->rowCount() !== 1 || $comparacion[$tabla]['sha256'] !== $original['sha256']) {
                throw new RuntimeException('Historial de migraciones inesperado.');
            }
        } elseif ($despues[$tabla]['cantidad'] !== $original['cantidad'] || $despues[$tabla]['sha256'] !== $original['sha256']) {
            throw new RuntimeException('Se alteraron datos históricos.');
        }
    }
    if ((int) $pdo->query("SELECT COUNT(*) FROM partidos WHERE tipo_resultado <> 'deportivo' OR oficializado_at IS NOT NULL")->fetchColumn() !== 0) {
        throw new RuntimeException('Se oficializaron históricos.');
    }
    foreach (['auditoria_cambios', 'autorizaciones_correccion'] as $tabla) {
        if ((int) $pdo->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn() !== 0) {
            throw new RuntimeException('Tabla nueva no vacía.');
        }
    }
    echo json_encode(['ok' => true, 'base' => $pdo->query('SELECT DATABASE()')->fetchColumn(), 'tablas_restauradas' => count($despues), 'filas_conservadas' => array_sum(array_column($manifiesto['antes'], 'cantidad')), 'migracion_aislada' => true]);
} catch (Throwable $error) {
    fwrite(STDERR, 'Falló la restauración o comparación aislada; detener.');
    exit(1);
}
