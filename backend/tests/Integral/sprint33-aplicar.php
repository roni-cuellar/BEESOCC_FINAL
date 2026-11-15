<?php

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

// Aplicación autorizada: una sola ruta y sin rollback automático ante errores.
require __DIR__.'/sprint33-preflight.php';
require __DIR__.'/sprint33-instantanea.php';

$etapa = 'respaldo_y_referencias';
try {
    $preparacion = json_decode(file_get_contents(__DIR__.'/sprint33-evidencia.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifiesto = json_decode(file_get_contents('C:/Users/admin/AppData/Local/Temp/beesocc_23c_test_20261115_015357_fa852a1e/respaldo-manifiesto.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! is_file($preparacion['respaldo'])
        || hash_file('sha256', $preparacion['respaldo']) !== $preparacion['sha256_respaldo']
        || $manifiesto['sha256'] !== $preparacion['sha256_respaldo']) {
        throw new RuntimeException('El respaldo no coincide con el validado.');
    }
    $antes = instantanea($pdo);
    if ($antes !== $manifiesto['antes'] || count($antes) !== 19 || array_sum(array_column($antes, 'cantidad')) !== 48) {
        throw new RuntimeException('La principal cambió respecto al respaldo.');
    }
    $nombre = '2026_11_14_235900_add_oficializacion_and_auditoria';
    $aplicadasAntes = $pdo->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
    if (in_array($nombre, $aplicadasAntes, true) || DB::connection()->getDatabaseName() !== 'beesocc_v2'
        || DB::selectOne('SELECT DATABASE() AS base')->base !== 'beesocc_v2') {
        throw new RuntimeException('Destino incorrecto o migración ya aplicada.');
    }
    $registro = ['destino' => 'beesocc_v2', 'respaldo' => $preparacion['respaldo'], 'sha256_respaldo' => $preparacion['sha256_respaldo'],
        'sha256_migracion' => $preparacion['sha256_migracion'], 'migracion_pendiente_confirmada' => true, 'fecha' => date(DATE_ATOM)];
    file_put_contents(__DIR__.'/sprint33-aplicacion.json', json_encode($registro, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $etapa = 'migracion_por_ruta';
    $proceso = new Process(['C:/xampp/php/php.exe', 'artisan', 'migrate', '--database=mysql',
        '--path=database/migrations/'.$nombre.'.php', '--force', '--no-interaction'], base_path());
    $proceso->setTimeout(60);
    $proceso->run();
    if (! $proceso->isSuccessful()) {
        throw new RuntimeException('Falló la migración; revisar manualmente sin rollback.');
    }
    $etapa = 'integridad_despues';
    $aplicadasDespues = $pdo->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
    $esperadas = [...$aplicadasAntes, $nombre];
    sort($esperadas);
    if ($aplicadasDespues !== $esperadas) {
        throw new RuntimeException('Se registraron migraciones inesperadas.');
    }
    $despues = instantanea($pdo, $antes);
    foreach ($antes as $tabla => $referencia) {
        if ($tabla === 'migrations') {
            // Excluir solo la nueva fila permite comparar el historial sin modificarlo.
            $filas = $pdo->query('SELECT * FROM migrations WHERE migration <> '.$pdo->quote($nombre))->fetchAll(PDO::FETCH_NUM);
            $filas = array_map(fn ($fila) => json_encode(array_map(fn ($valor) => $valor === null ? null : (string) $valor, $fila), JSON_THROW_ON_ERROR), $filas);
            sort($filas);
            if (count($filas) !== $referencia['cantidad'] || hash('sha256', implode("\n", $filas)) !== $referencia['sha256']) {
                throw new RuntimeException('Cambió el historial anterior.');
            }
        } elseif ($referencia['cantidad'] !== $despues[$tabla]['cantidad'] || $referencia['sha256'] !== $despues[$tabla]['sha256']) {
            throw new RuntimeException('Cambió un registro histórico.');
        }
        if (! in_array($tabla, ['partidos', 'migrations'], true) && $referencia['estructura'] !== $despues[$tabla]['estructura']) {
            throw new RuntimeException('Cambió una estructura no prevista.');
        }
    }
    $etapa = 'esquema_nuevo';
    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $esperadas = [...array_keys($antes), 'auditoria_cambios', 'autorizaciones_correccion'];
    sort($tablas);
    sort($esperadas);
    if ($tablas !== $esperadas) {
        throw new RuntimeException('Conjunto de tablas inesperado.');
    }
    $claves = $pdo->query("SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('auditoria_cambios','autorizaciones_correccion') ORDER BY TABLE_NAME, CONSTRAINT_NAME")->fetchAll(PDO::FETCH_ASSOC);
    $columnas = $pdo->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='partidos' AND COLUMN_NAME IN ('tipo_resultado','oficializado_at') ORDER BY COLUMN_NAME")->fetchAll(PDO::FETCH_ASSOC);
    $indices = $pdo->query('SHOW INDEX FROM auditoria_cambios')->fetchAll(PDO::FETCH_ASSOC);
    $compuesto = array_values(array_filter($indices, fn ($fila) => $fila['Key_name'] === 'auditoria_cambios_campeonato_id_entidad_tipo_entidad_id_index'));
    $validado = $preparacion['esquema_copia'];
    if ($claves !== $validado['claves_nuevas'] || $columnas !== $validado['columnas_partidos'] || array_column($compuesto, 'Column_name') !== $validado['indice_compuesto']) {
        throw new RuntimeException('El esquema difiere de la copia validada.');
    }
    foreach (['auditoria_cambios', 'autorizaciones_correccion'] as $tabla) {
        if ((int) $pdo->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn() !== 0) {
            throw new RuntimeException('Tabla nueva con registros inesperados.');
        }
    }
    if ((int) $pdo->query("SELECT COUNT(*) FROM partidos WHERE tipo_resultado <> 'deportivo' OR oficializado_at IS NOT NULL")->fetchColumn() !== 0) {
        throw new RuntimeException('Un histórico fue oficializado automáticamente.');
    }
    $registro += ['ok' => true, 'tablas_referencia' => 19, 'registros_conservados' => 48, 'tablas_actuales' => 21,
        'claves_foraneas_verificadas' => count($claves), 'esquema_identico_a_copia' => true, 'historicos_no_oficializados' => true,
        'otras_migraciones_ejecutadas' => false];
    file_put_contents(__DIR__.'/sprint33-aplicacion.json', json_encode($registro, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "\n".json_encode($registro, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo "\n".json_encode(['ok' => false, 'etapa' => $etapa, 'mensaje' => $error instanceof RuntimeException ? $error->getMessage() : 'Error técnico; detener sin rollback automático.']);
    exit(1);
}
