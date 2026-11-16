<?php

use App\Models\Campeonato;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$caso = $argv[1] ?? '';
$campeonatoId = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT);
if (! in_array($caso, ['desempate', 'eliminacion', 'clasificacion_correccion', 'generacion_correccion'], true) || ! $campeonatoId) {
    throw new RuntimeException('Barrera no permitida.');
}
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$prefijo = $carpeta.'/sprint54-barrera-'.$caso.'-'.$campeonatoId;
if (is_file($prefijo.'-lista.json')) {
    throw new RuntimeException('La carrera ya se ejecutó; no se sobrescribe evidencia.');
}
$servidor = json_decode(file_get_contents(getenv('BEESOCC_MARIADB_CONFIG')), true, flags: JSON_THROW_ON_ERROR);
$observador = new PDO('mysql:host=127.0.0.1;port=3317;charset=utf8mb4', 'root', $servidor['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (strcasecmp(rtrim(str_replace('\\', '/', $observador->query('SELECT @@datadir')->fetchColumn()), '/'), $servidor['datadir']) !== 0) {
    throw new RuntimeException('Observador fuera del laboratorio.');
}
$conexionBarrera = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
$esperas = 0;
$conexiones = [];
$diagnostico = [];
DB::beginTransaction();
try {
    $campeonato = Campeonato::whereKey($campeonatoId)->lockForUpdate()->firstOrFail();
    if ($campeonato->formato !== 'grupos_eliminacion') {
        throw new RuntimeException('La barrera solo prueba grupos.');
    }
    file_put_contents($prefijo.'-lista.json', json_encode(['lista' => true]));
    $limite = microtime(true) + 20;
    do {
        // Identifica al bloqueador concreto; no depende del nombre en PROCESSLIST.
        $consulta = $observador->prepare('SELECT DISTINCT r.trx_mysql_thread_id FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE b.trx_mysql_thread_id=? AND r.trx_state=\'LOCK WAIT\'');
        $consulta->execute([$conexionBarrera]);
        $conexiones = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
        $esperas = count($conexiones);
        $diagnostico = [
            'bloqueador' => $conexionBarrera,
            'transacciones' => $observador->query('SELECT trx_mysql_thread_id, trx_state FROM information_schema.INNODB_TRX')->fetchAll(PDO::FETCH_ASSOC),
            'esperas' => $observador->query('SELECT requesting_trx_id, blocking_trx_id FROM information_schema.INNODB_LOCK_WAITS')->fetchAll(PDO::FETCH_ASSOC),
        ];
        if ($esperas === 2) {
            break;
        }
        // Deja renovar la instantánea interna de metadatos entre observaciones.
        usleep(250000);
    } while (microtime(true) < $limite);
    if ($esperas !== 2) {
        throw new RuntimeException('No se observaron ambas solicitudes HTTP esperando el bloqueo.');
    }
    DB::commit();
    file_put_contents($prefijo.'-resultado.json', json_encode(['ok' => true, 'caso' => $caso, 'solicitudes_http_en_lock_wait' => $esperas, 'conexiones' => $conexiones]));
} catch (Throwable $error) {
    file_put_contents($prefijo.'-diagnostico.json', json_encode($diagnostico + ['error' => get_class($error)], JSON_PRETTY_PRINT));
    file_put_contents($prefijo.'-resultado.json', json_encode(['ok' => false, 'caso' => $caso, 'solicitudes_http_en_lock_wait' => $esperas, 'conexiones' => $conexiones]));
    // Laravel puede renderizar una excepción CLI con código 0; se exige fallo explícito.
    fwrite(STDERR, 'La barrera no acreditó dos esperas InnoDB; detener.'.PHP_EOL);
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    exit(1);
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}
