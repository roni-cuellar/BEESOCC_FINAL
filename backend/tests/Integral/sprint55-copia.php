<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
require __DIR__.'/sprint33-instantanea.php';

$etapa = 'aislamiento';
try {
    iniciarEntornoIntegral();
    $base = DB::connection()->getDatabaseName();
    $pdo = DB::connection()->getPdo();
    $motor = DB::selectOne('SELECT VERSION() AS version, @@port AS puerto');
    if ($motor->version !== '10.4.32-MariaDB' || (int) $motor->puerto !== 3317 || $pdo->query('SHOW TABLES')->fetchAll()) {
        throw new RuntimeException('Se requiere el motor aislado y una base vacía.');
    }
    foreach (DB::select('SHOW GRANTS') as $fila) {
        $permiso = array_values((array) $fila)[0];
        if (! str_starts_with($permiso, 'GRANT USAGE ON *.*') && ! str_contains($permiso, 'ON `'.$base.'`.* TO')) {
            throw new RuntimeException('Cuenta con permisos fuera del laboratorio.');
        }
    }
    try {
        DB::select('SELECT id FROM beesocc_v2.users LIMIT 1');
        throw new RuntimeException('La cuenta accede a la principal.');
    } catch (QueryException $error) {
        if (! in_array($error->errorInfo[1] ?? null, [1044, 1142], true)) {
            throw $error;
        }
    }
    $conexion = config('database.connections.mysql');
    try {
        new PDO('mysql:host=127.0.0.1;port=3306;dbname=beesocc_v2;charset=utf8mb4', $conexion['username'], $conexion['password']);
        throw new RuntimeException('La cuenta temporal se autenticó en la principal.');
    } catch (PDOException $error) {
        if (! in_array($error->errorInfo[1] ?? null, [1044, 1045], true)) {
            throw $error;
        }
    }
    $manifiesto = json_decode(file_get_contents(getenv('BEESOCC_COPIA_MANIFIESTO')), true, flags: JSON_THROW_ON_ERROR);
    $sql = file_get_contents($manifiesto['respaldo']);
    if (hash('sha256', $sql) !== $manifiesto['sha256_respaldo']
        || preg_match('/(?:CREATE|DROP)\s+DATABASE|\bUSE\s+|`beesocc_v2`\s*\./i', $sql)) {
        throw new RuntimeException('Respaldo modificado o fuera de alcance.');
    }
    $nombre = '2026_11_15_000200_create_desempates_grupos';
    $ruta = 'database/migrations/'.$nombre.'.php';
    if (hash_file('sha256', base_path($ruta)) !== $manifiesto['sha256_migracion']) {
        throw new RuntimeException('La migración cambió desde el respaldo.');
    }
    $etapa = 'restauracion';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli($conexion['host'], $conexion['username'], $conexion['password'], $base, 3317);
    $mysqli->multi_query($sql);
    do {
        if ($resultado = $mysqli->store_result()) {
            $resultado->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
    $mysqli->close();
    if (instantanea($pdo) !== $manifiesto['antes']) {
        throw new RuntimeException('La copia restaurada difiere de la principal.');
    }
    $historial = DB::table('migrations')->orderBy('id')->get()->toArray();
    $esquemas = [];
    $schema = DB::getSchemaBuilder();
    foreach (['fases', 'partidos'] as $tabla) {
        $esquemas[$tabla] = ['columnas' => $schema->getColumns($tabla), 'indices' => $schema->getIndexes($tabla), 'foraneas' => $schema->getForeignKeys($tabla)];
    }
    $etapa = 'migracion_exclusiva';
    if (Artisan::call('migrate', ['--database' => 'mysql', '--path' => $ruta, '--force' => true]) !== 0) {
        throw new RuntimeException('Falló la migración exclusiva en la copia.');
    }
    $etapa = 'integridad';
    foreach ($esquemas as $tabla => $original) {
        $columnas = array_column($schema->getColumns($tabla), null, 'name');
        $indices = array_column($schema->getIndexes($tabla), null, 'name');
        $foraneas = array_column($schema->getForeignKeys($tabla), null, 'name');
        foreach (['columnas' => $columnas, 'indices' => $indices, 'foraneas' => $foraneas] as $tipo => $actuales) {
            foreach ($original[$tipo] as $elemento) {
                if (($actuales[$elemento['name']] ?? null) !== $elemento) {
                    throw new RuntimeException('Se alteró un elemento estructural anterior.');
                }
            }
        }
    }
    $despues = instantanea($pdo, $manifiesto['antes']);
    foreach ($manifiesto['antes'] as $tabla => $original) {
        if ($tabla === 'migrations') {
            if (DB::table('migrations')->where('migration', '!=', $nombre)->orderBy('id')->get()->toArray() != $historial
                || DB::table('migrations')->where('migration', $nombre)->count() !== 1
                || DB::table('migrations')->count() !== count($historial) + 1) {
                throw new RuntimeException('Cambió otra migración o su historial.');
            }

            continue;
        }
        if ($despues[$tabla]['cantidad'] !== $original['cantidad'] || $despues[$tabla]['sha256'] !== $original['sha256']) {
            throw new RuntimeException('Cambió un registro histórico.');
        }
        if (! in_array($tabla, ['fases', 'partidos'], true) && $despues[$tabla]['estructura'] !== $original['estructura']) {
            throw new RuntimeException('Cambió una estructura anterior ajena a la migración.');
        }
    }
    if (DB::table('grupo_desempates')->count() !== 0
        || DB::table('fases')->whereNotNull('fase_origen_id')->orWhereNotNull('clasificacion_huella')->exists()
        || DB::table('partidos')->whereNotNull('grupo_desempate_id')->exists()
        || DB::getSchemaBuilder()->hasTable('personal_access_tokens')
        || DB::table('migrations')->where('migration', 'like', '%personal_access_tokens%')->exists()) {
        throw new RuntimeException('Históricos completados artificialmente o tokens ejecutados.');
    }
    $columnas = DB::select("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='fases' AND COLUMN_NAME IN ('fase_origen_id','clasificacion_huella')) OR TABLE_NAME='grupo_desempates' OR (TABLE_NAME='partidos' AND COLUMN_NAME='grupo_desempate_id'))");
    if (count($columnas) !== 9) {
        throw new RuntimeException('Faltan columnas nuevas.');
    }
    $foraneas = DB::select("SELECT k.TABLE_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND ((k.TABLE_NAME='fases' AND k.COLUMN_NAME='fase_origen_id') OR k.TABLE_NAME='grupo_desempates' OR (k.TABLE_NAME='partidos' AND k.COLUMN_NAME='grupo_desempate_id'))");
    $esperadas = ['fases.fase_origen_id' => 'fases', 'grupo_desempates.grupo_id' => 'grupos', 'grupo_desempates.llave_id' => 'llaves', 'partidos.grupo_desempate_id' => 'grupo_desempates'];
    foreach ($foraneas as $fk) {
        $clave = $fk->TABLE_NAME.'.'.$fk->COLUMN_NAME;
        if (($esperadas[$clave] ?? null) !== $fk->REFERENCED_TABLE_NAME || $fk->REFERENCED_COLUMN_NAME !== 'id' || $fk->DELETE_RULE !== 'RESTRICT') {
            throw new RuntimeException('Clave foránea inesperada.');
        }
        unset($esperadas[$clave]);
    }
    if ($esperadas) {
        throw new RuntimeException('Faltan claves foráneas.');
    }
    $indices = DB::select("SELECT TABLE_NAME,INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND NON_UNIQUE=0 AND TABLE_NAME IN ('fases','grupo_desempates') GROUP BY TABLE_NAME,INDEX_NAME");
    $unicos = array_map(fn ($i) => $i->TABLE_NAME.':'.$i->columnas, $indices);
    foreach (['fases:fase_origen_id', 'grupo_desempates:grupo_id,clave', 'grupo_desempates:llave_id'] as $indice) {
        if (! in_array($indice, $unicos, true)) {
            throw new RuntimeException('Falta un índice único.');
        }
    }
    $ddl = array_values((array) DB::selectOne('SHOW CREATE TABLE grupo_desempates'))[1];
    if (! str_contains(strtolower($ddl), 'json_valid')) {
        throw new RuntimeException('Falta la restricción JSON.');
    }
    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $esperadas = array_merge(array_keys($manifiesto['antes']), ['grupo_desempates']);
    sort($tablas);
    sort($esperadas);
    if ($tablas !== $esperadas) {
        throw new RuntimeException('Se crearon otras tablas.');
    }
    $resultado = ['ok' => true, 'copia_restaurada_identica' => true, 'cuenta_limitada' => true, 'principal_inaccesible' => true,
        'rutas_fisicas_verificadas' => true, 'motor' => $motor->version, 'tablas_restauradas' => count($manifiesto['antes']),
        'registros_historicos' => array_sum(array_column($manifiesto['antes'], 'cantidad')), 'tablas_finales' => count($tablas),
        'migraciones_adicionales' => [$nombre], 'tokens_omitidos' => true, 'columnas_nuevas' => 9, 'foraneas_restrict' => 4,
        'indices_unicos' => 3, 'json_valid' => true, 'datos_historicos_intactos' => true,
        'estructuras_ajenas_intactas' => true, 'sha256_migracion' => $manifiesto['sha256_migracion'], 'sha256_respaldo' => $manifiesto['sha256_respaldo']];
    file_put_contents(dirname(getenv('BEESOCC_COPIA_MANIFIESTO')).'/sprint55-copia.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($resultado).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Falló la copia en '.$etapa.'. Detener; diagnóstico privado requerido.'.PHP_EOL);
    exit(1);
}
