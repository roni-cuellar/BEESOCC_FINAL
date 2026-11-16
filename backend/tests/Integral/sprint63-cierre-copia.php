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
    $nombre = '2026_11_16_000400_add_resoluciones_administrativas';
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
    $restaurada = instantanea($pdo);
    if (array_diff_key($restaurada, ['sessions' => true]) !== array_diff_key($manifiesto['antes'], ['sessions' => true]) || $restaurada['sessions']['estructura'] !== $manifiesto['antes']['sessions']['estructura']) {
        throw new RuntimeException('La copia restaurada difiere de la principal.');
    }
    $historial = DB::table('migrations')->orderBy('id')->get()->toArray();
    $schema = DB::getSchemaBuilder();
    $esquemas = [];
    foreach (['partidos', 'campeonato_equipos'] as $tabla) {
        $esquemas[$tabla] = ['columnas' => $schema->getColumns($tabla), 'indices' => $schema->getIndexes($tabla), 'foraneas' => $schema->getForeignKeys($tabla)];
    }
    $etapa = 'migracion_exclusiva';
    if (Artisan::call('migrate', ['--database' => 'mysql', '--path' => $ruta, '--force' => true]) !== 0) {
        throw new RuntimeException('Falló la migración exclusiva en la copia.');
    }
    foreach ($esquemas as $tabla => $original) {
        $actuales = ['columnas' => array_column($schema->getColumns($tabla), null, 'name'), 'indices' => array_column($schema->getIndexes($tabla), null, 'name'), 'foraneas' => array_column($schema->getForeignKeys($tabla), null, 'name')];
        foreach ($original as $tipo => $elementos) {
            foreach ($elementos as $elemento) {
                if (($actuales[$tipo][$elemento['name']] ?? null) !== $elemento) {
                    throw new RuntimeException('Se alteró un elemento estructural anterior.');
                }
            }
        }
    }
    if (DB::table('migrations')->where('migration', '!=', $nombre)->orderBy('id')->get()->toArray() != $historial || DB::table('migrations')->where('migration', $nombre)->count() !== 1 || DB::table('migrations')->count() !== count($historial) + 1) {
        throw new RuntimeException('Se alteró otra entrada del historial.');
    }
    $despues = instantanea($pdo, $restaurada);
    foreach ($restaurada as $tabla => $original) {
        if ($tabla === 'migrations') {
            continue;
        }
        if ($despues[$tabla]['cantidad'] !== $original['cantidad'] || $despues[$tabla]['sha256'] !== $original['sha256'] || (! isset($esquemas[$tabla]) && $despues[$tabla]['estructura'] !== $original['estructura'])) {
            throw new RuntimeException('Se alteró un dato o estructura anterior.');
        }
    }
    foreach (['partidos' => ['administrativo_beneficiado_id', 'administrativo_sancionado_id', 'administrativo_por_id', 'motivo_administrativo'], 'campeonato_equipos' => ['motivo_inactividad', 'inactivado_at']] as $tabla => $campos) {
        $columnas = array_column($schema->getColumns($tabla), null, 'name');
        if (count($columnas) !== count($restaurada[$tabla]['columnas']) + count($campos)) {
            throw new RuntimeException('Cantidad de columnas inesperada');
        }
        foreach ($campos as $campo) {
            if (! isset($columnas[$campo]) || ! $columnas[$campo]['nullable'] || DB::table($tabla)->whereNotNull($campo)->exists()) {
                throw new RuntimeException('Columna nueva incorrecta o histórico rellenado');
            }
        }
    }
    $fks = collect($schema->getForeignKeys('partidos'))->filter(fn ($fk) => str_starts_with($fk['columns'][0], 'administrativo_'));
    $esperadas = ['administrativo_beneficiado_id' => 'campeonato_equipos', 'administrativo_sancionado_id' => 'campeonato_equipos', 'administrativo_por_id' => 'users'];
    foreach ($fks as $fk) {
        $campo = $fk['columns'][0];
        if (($esperadas[$campo] ?? null) !== $fk['foreign_table'] || $fk['foreign_columns'] !== ['id'] || strtolower($fk['on_delete']) !== 'restrict') {
            throw new RuntimeException('FK incorrecta');
        }
        unset($esperadas[$campo]);
    }
    if ($esperadas || $fks->count() !== 3 || $schema->hasTable('personal_access_tokens') || count(DB::select('SHOW TABLES')) !== 28) {
        throw new RuntimeException('Esquema inesperado');
    }
    // Solo DDL vacío y metadatos de migraciones para otro laboratorio ficticio.
    $sqlVacio = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
        $sqlVacio .= $pdo->query('SHOW CREATE TABLE `'.$tabla.'`')->fetch(PDO::FETCH_NUM)[1].";\n";
    }
    foreach ($pdo->query('SELECT id,migration,batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_NUM) as $fila) {
        $sqlVacio .= 'INSERT INTO migrations (id,migration,batch) VALUES ('.implode(',', array_map(fn ($v) => $pdo->quote((string) $v), $fila)).");\n";
    }
    $sqlVacio .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $carpeta = dirname(getenv('BEESOCC_COPIA_MANIFIESTO'));
    $archivo = $carpeta.'/esquema-vacio-000400.sql';
    if (is_file($archivo)) {
        throw new RuntimeException('Esquema previo existente');
    }
    file_put_contents($archivo, $sqlVacio);
    file_put_contents($carpeta.'/esquema-vacio.json', json_encode(['archivo' => $archivo, 'sha256' => hash('sha256', $sqlVacio), 'tablas' => 28, 'migraciones' => 21], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $resultado = ['ok' => true, 'restauracion_identica_excepto_sessions' => true, 'sessions_iguales_referencia' => $restaurada['sessions']['sha256'] === $manifiesto['antes']['sessions']['sha256'], 'tablas' => 28, 'columnas_nuevas' => 6, 'fk_restrict' => 3, 'historial_anterior_intacto' => true, 'migraciones_anteriores' => count($historial), 'migraciones_finales' => DB::table('migrations')->count(), 'solo_migracion_aplicada' => $nombre, 'datos_previos_conservados' => true, 'plantillas_conservadas' => $restaurada['plantillas']['cantidad'], 'campeonatos_conservados' => $restaurada['campeonatos']['cantidad'], 'tokens_omitidos' => true, 'cuenta_limitada' => true, 'principal_inaccesible' => true, 'sha256_migracion' => $manifiesto['sha256_migracion'], 'sha256_respaldo' => $manifiesto['sha256_respaldo']];
    file_put_contents($carpeta.'/sprint63-cierre-copia.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($resultado).PHP_EOL;
} catch (Throwable $error) {
    file_put_contents(dirname(getenv('BEESOCC_COPIA_MANIFIESTO')).'/diagnostico-copia400-privado.txt', (string) $error);
    fwrite(STDERR, 'Ensayo detenido en '.$etapa.'; diagnóstico privado conservado.'.PHP_EOL);
    exit(1);
}
