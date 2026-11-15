<?php

use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
require __DIR__.'/sprint33-instantanea.php';
iniciarEntornoIntegral();
$manifesto = json_decode(file_get_contents(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/sprint42-respaldo.json'), true, flags: JSON_THROW_ON_ERROR);
$validada = json_decode(file_get_contents(__DIR__.'/sprint42-migracion.json'), true, flags: JSON_THROW_ON_ERROR);
$migracion = '2026_11_15_000100_create_estructura_eliminacion';
if (hash_file('sha256', $manifesto['respaldo']) !== $manifesto['sha256']
    || hash_file('sha256', database_path('migrations/'.$migracion.'.php')) !== $validada['sha256_migracion']) {
    throw new RuntimeException('Cambió el respaldo o la migración validada.');
}
$pdo = DB::connection()->getPdo();
$despues = instantanea($pdo, $manifesto['antes']);
foreach ($manifesto['antes'] as $tabla => $antes) {
    if ($tabla === 'migrations') {
        continue; // El registro adicional ya se comprobó en el restaurador.
    }
    if ($despues[$tabla]['cantidad'] !== $antes['cantidad'] || $despues[$tabla]['sha256'] !== $antes['sha256']) {
        throw new RuntimeException('Cambió una referencia original de '.$tabla);
    }
    if ($tabla !== 'partidos' && $despues[$tabla]['estructura'] !== $antes['estructura']) {
        throw new RuntimeException('Cambió una estructura anterior de '.$tabla);
    }
}
// El ALTER debe conservar las definiciones de columnas, índices y FK anteriores del partido.
foreach (explode("\n", $manifesto['antes']['partidos']['estructura']) as $linea) {
    $linea = rtrim(trim($linea), ',');
    if (str_starts_with($linea, '`') || str_starts_with($linea, 'PRIMARY ') || str_starts_with($linea, 'UNIQUE ') || str_starts_with($linea, 'KEY ') || str_starts_with($linea, 'CONSTRAINT ')) {
        if (! str_contains($despues['partidos']['estructura'], $linea)) {
            throw new RuntimeException('No se conservó una definición original del partido.');
        }
    }
}
$nuevas = ['fases', 'grupos', 'fase_equipos', 'rondas', 'llaves', 'llave_plazas'];
if (count($pdo->query('SHOW TABLES')->fetchAll()) !== count($manifesto['antes']) + count($nuevas)) {
    throw new RuntimeException('Hay tablas inesperadas en la copia.');
}
foreach ($nuevas as $tabla) {
    if (DB::table($tabla)->count() !== 0) {
        throw new RuntimeException('La estructura nueva no quedó vacía.');
    }
}
$resultados = [];
foreach (['migracion', 'esquema', 'detalle-esquema'] as $tipo) {
    $resultados[$tipo] = json_decode(file_get_contents(__DIR__.'/sprint43-'.$tipo.'.json'), true, flags: JSON_THROW_ON_ERROR);
    if (($resultados[$tipo]['ok'] ?? false) !== true || $resultados[$tipo]['base'] !== DB::connection()->getDatabaseName()) {
        throw new RuntimeException('Evidencia faltante o de otra base.');
    }
}
$resultado = ['ok' => true, 'base_copia' => DB::connection()->getDatabaseName(),
    'respaldo' => $manifesto['respaldo'], 'sha256_respaldo' => $manifesto['sha256'],
    'sha256_migracion' => $validada['sha256_migracion'], 'tablas_originales' => count($manifesto['antes']),
    'registros_originales_conservados' => array_sum(array_column($manifesto['antes'], 'cantidad')),
    'verificaciones_estructurales' => $resultados['esquema']['comprobaciones'] + $resultados['detalle-esquema']['comprobaciones'],
    'ligas_compatibles' => count($resultados['migracion']['ligas_comparadas']), 'otras_migraciones_ejecutadas' => false,
    'estado_principal' => 'Pendiente de autorización; sin migrate en este sprint'];
file_put_contents(__DIR__.'/sprint43-evidencia.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
