<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
require __DIR__.'/sprint33-instantanea.php';
iniciarEntornoIntegral();
$pdo = DB::connection()->getPdo();
if ($pdo->query('SHOW TABLES')->fetchAll()) {
    throw new RuntimeException('Solo se restaura sobre una base nueva vacía.');
}
foreach (DB::select('SHOW GRANTS') as $fila) {
    $permiso = array_values((array) $fila)[0];
    if (! str_starts_with($permiso, 'GRANT USAGE ON *.*') && ! str_contains($permiso, 'ON `'.DB::connection()->getDatabaseName().'`.* TO')) {
        throw new RuntimeException('Permisos fuera del laboratorio.');
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
$manifesto = json_decode(file_get_contents(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/sprint42-respaldo.json'), true, flags: JSON_THROW_ON_ERROR);
$sql = file_get_contents($manifesto['respaldo']);
if (hash('sha256', $sql) !== $manifesto['sha256'] || preg_match('/(?:CREATE|DROP)\s+DATABASE|\bUSE\s+|`beesocc_v2`\s*\./i', $sql)) {
    throw new RuntimeException('Respaldo fuera del alcance permitido.');
}
$config = config('database.connections.mysql');
$mysqli = new mysqli($config['host'], $config['username'], $config['password'], $config['database'], (int) $config['port']);
$mysqli->multi_query($sql);
do {
    if ($resultado = $mysqli->store_result()) {
        $resultado->free();
    }
} while ($mysqli->more_results() && $mysqli->next_result());
$mysqli->close();
if (instantanea($pdo) !== $manifesto['antes']) {
    throw new RuntimeException('La copia restaurada difiere del respaldo.');
}
$ruta = 'database/migrations/2026_11_15_000100_create_estructura_eliminacion.php';
if (hash_file('sha256', base_path($ruta)) !== $manifesto['sha256_migracion']) {
    throw new RuntimeException('La migración cambió desde el respaldo.');
}
$migracionesAntes = DB::table('migrations')->orderBy('id')->get()->toArray();
$prefijoEvidencia = getenv('BEESOCC_SPRINT_EVIDENCIA') === '43' ? 'sprint43' : 'sprint42';
if ($prefijoEvidencia === 'sprint43') {
    // Comparar la liga restaurada sin sobrescribir la evidencia de 4.2.
    require __DIR__.'/sprint43-ligas.php';
    $ligasAntes = consultarLigas43();
}
if (Artisan::call('migrate', ['--database' => 'mysql', '--path' => $ruta, '--force' => true]) !== 0) {
    throw new RuntimeException('Falló la migración exclusiva sobre la copia.');
}
$despues = instantanea($pdo, $manifesto['antes']);
foreach ($manifesto['antes'] as $tabla => $referencia) {
    if ($tabla === 'migrations') {
        $anteriores = DB::table('migrations')->where('migration', '!=', basename($ruta, '.php'))->orderBy('id')->get()->toArray();
        if ($anteriores != $migracionesAntes || DB::table('migrations')->count() !== count($migracionesAntes) + 1) {
            throw new RuntimeException('Se ejecutó otra migración o cambió su historial.');
        }
    } elseif ($despues[$tabla]['cantidad'] !== $referencia['cantidad'] || $despues[$tabla]['sha256'] !== $referencia['sha256']) {
        throw new RuntimeException('Cambió un registro anterior.');
    }
}
if ((int) $pdo->query('SELECT COUNT(*) FROM partidos WHERE fase_id IS NOT NULL OR grupo_id IS NOT NULL OR llave_id IS NOT NULL OR numero_serie IS NOT NULL')->fetchColumn() !== 0) {
    throw new RuntimeException('Se asignaron relaciones a partidos históricos.');
}
if ($prefijoEvidencia === 'sprint43' && consultarLigas43() !== $ligasAntes) {
    throw new RuntimeException('Cambió la tabla o estadística de una liga histórica.');
}
file_put_contents(__DIR__.'/'.$prefijoEvidencia.'-migracion.json', json_encode(['ok' => true, 'base' => $config['database'],
    'tablas_restauradas' => count($manifesto['antes']), 'registros_conservados' => array_sum(array_column($manifesto['antes'], 'cantidad')),
    'sha256_migracion' => $manifesto['sha256_migracion'], 'otras_migraciones' => false, 'ligas_comparadas' => $ligasAntes ?? null], JSON_PRETTY_PRINT));
echo json_encode(['ok' => true, 'copia_restaurada' => $config['database'], 'migracion_exclusiva' => true]);
