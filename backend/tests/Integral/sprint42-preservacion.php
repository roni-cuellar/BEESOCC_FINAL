<?php

// Ambas conexiones de este verificador ejecutan exclusivamente SELECT/SHOW y ajustes de sesión.
require __DIR__.'/sprint33-preflight.php';
require __DIR__.'/sprint33-instantanea.php';
$ruta = getenv('BEESOCC_INTEGRAL_CONFIG');
$entorno = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/^beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/', $entorno['DB_DATABASE'])
    || $entorno['DB_HOST'] !== '127.0.0.1' || $entorno['DB_PORT'] !== '3317'
    || ! str_starts_with($entorno['DB_USERNAME'], 'beesocc23c_')) {
    throw new RuntimeException('Destino de comparación inválido.');
}
$referencia = json_decode(file_get_contents(dirname($ruta).'/sprint42-respaldo.json'), true, flags: JSON_THROW_ON_ERROR);
if (instantanea($pdo) !== $referencia['antes']) {
    throw new RuntimeException('Cambió la principal desde el respaldo.');
}
$copia = new PDO('mysql:host=127.0.0.1;port=3317;dbname='.$entorno['DB_DATABASE'].';charset=utf8mb4', $entorno['DB_USERNAME'], $entorno['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identidad = $copia->query('SELECT DATABASE() AS base, CURRENT_USER() AS cuenta, @@datadir AS datos')->fetch(PDO::FETCH_ASSOC);
if ($identidad['base'] !== $entorno['DB_DATABASE'] || ! str_starts_with($identidad['cuenta'], $entorno['DB_USERNAME'].'@')
    || strcasecmp(rtrim(str_replace('\\', '/', $identidad['datos']), '/'), $entorno['BEESOCC_MARIADB_DATADIR']) !== 0) {
    throw new RuntimeException('Identidad física o cuenta de copia incorrecta.');
}
$copia->exec("SET time_zone = '+00:00'");
$registros = 0;
foreach ($referencia['antes'] as $tabla => $original) {
    // Caché y sesiones son datos transitorios que el tráfico real puede expirar.
    if (in_array($tabla, ['cache', 'cache_locks', 'sessions'], true)) {
        continue;
    }
    $columnas = implode(',', array_map(fn ($campo) => '`'.$campo.'`', $original['columnas']));
    $filas = $pdo->query("SELECT $columnas FROM `$tabla`")->fetchAll(PDO::FETCH_NUM);
    if (! $filas) {
        continue;
    }
    // Las nuevas fixtures no se mezclan con las claves primarias originales.
    $claves = array_column($filas, 0);
    $consulta = $copia->prepare("SELECT $columnas FROM `$tabla` WHERE `".$original['columnas'][0].'` IN ('.implode(',', array_fill(0, count($claves), '?')).')');
    $consulta->execute($claves);
    $actuales = $consulta->fetchAll(PDO::FETCH_NUM);
    $normalizar = function (array $valores): array {
        $valores = array_map(fn ($fila) => json_encode(array_map(fn ($valor) => $valor === null ? null : (string) $valor, $fila), JSON_THROW_ON_ERROR), $valores);
        sort($valores);

        return $valores;
    };
    if ($normalizar($actuales) !== $normalizar($filas)) {
        throw new RuntimeException('Cambió una referencia histórica de '.$tabla);
    }
    $registros += count($filas);
}
$resultado = ['ok' => true, 'principal_intacta' => true, 'tablas_principal' => count($referencia['antes']),
    'registros_principal' => array_sum(array_column($referencia['antes'], 'cantidad')),
    'referencias_persistentes_conservadas_en_copia' => $registros,
    'tablas_transitorias_excluidas_en_copia' => ['cache', 'cache_locks', 'sessions'], 'sha256_respaldo' => $referencia['sha256'],
    'sha256_migracion' => $referencia['sha256_migracion']];
file_put_contents(__DIR__.'/sprint42-preservacion.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "\n".json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
