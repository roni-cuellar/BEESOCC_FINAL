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
    $nombre = '2026_11_15_000300_add_ciclo_y_minimos_campeonato';
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
    foreach (['campeonatos', 'configuracion_campeonato', 'plantillas'] as $tabla) {
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
    foreach (['campeonatos' => ['iniciado_at', 'finalizado_at'], 'configuracion_campeonato' => ['modalidad', 'minimo_jugadores'], 'plantillas' => ['dorsal_vigente']] as $tabla => $columnas) {
        foreach ($columnas as $columna) {
            if (! $schema->hasColumn($tabla, $columna)) {
                throw new RuntimeException('Falta columna nueva.');
            }
            if ($columna !== 'dorsal_vigente' && DB::table($tabla)->whereNotNull($columna)->exists()) {
                throw new RuntimeException('Se rellenó un histórico automáticamente.');
            }
        }
    }
    $indice = collect($schema->getIndexes('plantillas'))->firstWhere('name', 'plantillas_dorsal_vigente_unique');
    if (! $indice || ! $indice['unique'] || $indice['columns'] !== ['campeonato_equipo_id', 'dorsal_vigente'] || $schema->hasTable('personal_access_tokens')) {
        throw new RuntimeException('Índice o exclusión de tokens incorrectos.');
    }
    $generada = collect($schema->getColumns('plantillas'))->firstWhere('name', 'dorsal_vigente');
    if (! $generada['generation'] || DB::selectOne('SELECT COUNT(*) AS n FROM plantillas WHERE (fecha_salida IS NULL AND NOT (dorsal_vigente <=> dorsal)) OR (fecha_salida IS NOT NULL AND dorsal_vigente IS NOT NULL)')->n !== 0) {
        throw new RuntimeException('Generación de dorsal incorrecta.');
    }
    // DML ficticio reversible únicamente en la copia; no altera sus filas históricas.
    $etapa = 'restriccion_fisica';
    DB::beginTransaction();
    try {
        $inscripcion = DB::table('campeonato_equipos')->value('id');
        if (! $inscripcion) {
            $inscripcion = DB::table('campeonato_equipos')->insertGetId(['campeonato_id' => DB::table('campeonatos')->value('id'), 'equipo_id' => DB::table('equipos')->value('id'), 'estado' => 'activo']);
        }
        $jugador = DB::table('jugadores')->value('id');
        $dorsal = 99;
        while (DB::table('plantillas')->where('campeonato_equipo_id', $inscripcion)->whereNull('fecha_salida')->where('dorsal', $dorsal)->exists()) {
            $dorsal--;
            if ($dorsal < 1) {
                throw new RuntimeException('No hay dorsal libre para el ensayo reversible.');
            }
        }
        $datos = ['campeonato_equipo_id' => $inscripcion, 'jugador_id' => $jugador, 'dorsal' => $dorsal, 'posicion' => 'Ensayo ficticio', 'fecha_ingreso' => '2020-01-01'];
        $id = DB::table('plantillas')->insertGetId($datos);
        $rechazado = false;
        try {
            DB::table('plantillas')->insert($datos);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            $rechazado = true;
        }
        if (! $rechazado) {
            throw new RuntimeException('Duplicado aceptado.');
        }
        DB::table('plantillas')->where('id', $id)->update(['fecha_salida' => '2020-01-02']);
        DB::table('plantillas')->insert($datos);
    } finally {
        DB::rollBack();
    }
    $final = instantanea($pdo, $restaurada);
    foreach ($restaurada as $tabla => $original) {
        if ($tabla !== 'migrations' && ($final[$tabla]['cantidad'] !== $original['cantidad'] || $final[$tabla]['sha256'] !== $original['sha256'])) {
            throw new RuntimeException('El ensayo no conservó los registros anteriores.');
        }
    }
    $resultado = ['ok' => true, 'copia_restaurada_identica_excepto_sessions' => true, 'sessions_dump_coinciden_referencia' => $restaurada['sessions']['sha256'] === $manifiesto['antes']['sessions']['sha256'],
        'cuenta_limitada' => true, 'rutas_fisicas_verificadas' => true, 'principal_inaccesible' => true, 'tablas' => count($restaurada), 'columnas_nuevas' => 5, 'indice_unico' => true,
        'migraciones_agregadas' => [$nombre], 'tokens_omitidos' => true, 'datos_historicos_conservados' => true, 'plantillas_historicas' => $restaurada['plantillas']['cantidad'],
        'campeonatos_conservados' => $restaurada['campeonatos']['cantidad'], 'duplicado_rechazado_y_dorsal_liberado' => true, 'sonda_revertida' => true,
        'sha256_migracion' => $manifiesto['sha256_migracion'], 'sha256_respaldo' => $manifiesto['sha256_respaldo']];
    file_put_contents(dirname(getenv('BEESOCC_COPIA_MANIFIESTO')).'/sprint62-cierre-copia.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($resultado).PHP_EOL;
} catch (Throwable $error) {
    file_put_contents(dirname(getenv('BEESOCC_COPIA_MANIFIESTO')).'/diagnostico-copia-privado.txt', (string) $error);
    fwrite(STDERR, 'Falló la copia en '.$etapa.'. Detener; diagnóstico privado requerido.'.PHP_EOL);
    exit(1);
}
