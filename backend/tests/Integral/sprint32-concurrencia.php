<?php

use App\Models\AuditoriaCambio;
use App\Models\AutorizacionCorreccion;
use App\Models\EventoEstadistico;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$fixture = json_decode(file_get_contents($carpeta.'/sprint32-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$servidor = json_decode(file_get_contents(getenv('BEESOCC_MARIADB_CONFIG')), true, flags: JSON_THROW_ON_ERROR);
$observador = new PDO('mysql:host=127.0.0.1;port=3317;charset=utf8mb4', 'root', $servidor['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (strcasecmp(rtrim(str_replace('\\', '/', $observador->query('SELECT @@datadir')->fetchColumn()), '/'), $servidor['datadir']) !== 0) {
    throw new RuntimeException('Observador fuera del laboratorio.');
}
if (AutorizacionCorreccion::findOrFail($fixture['concurrencia'])->estado !== 'aprobada') {
    throw new RuntimeException('Se requiere una autorización nueva aprobada.');
}
foreach (['1', '2'] as $id) {
    if (is_file($carpeta.'/competidor-'.$id.'-listo.json')) {
        throw new RuntimeException('El ensayo ya se inició; no se sobrescribe evidencia.');
    }
}
$eventosAntes = EventoEstadistico::whereHas('equipoPartido', fn ($q) => $q->where('partido_id', $fixture['partido']))->count();
$auditoriaAntes = AuditoriaCambio::where('entidad_tipo', 'partido')->where('entidad_id', $fixture['partido'])->where('accion', 'aplicar_correccion')->count();
$procesos = [];
DB::beginTransaction();
try {
    DB::table('campeonatos')->where('id', $fixture['campeonato'])->lockForUpdate()->first();
    foreach (['1', '2'] as $id) {
        $proceso = new Process([PHP_BINARY, __DIR__.'/sprint32-competidor.php', $id], base_path());
        $proceso->setTimeout(35);
        $proceso->start();
        $procesos[] = $proceso;
    }
    $limite = microtime(true) + 20;
    $esperando = 0;
    do {
        $conexiones = [];
        foreach (['1', '2'] as $id) {
            $ruta = $carpeta.'/competidor-'.$id.'-listo.json';
            if (is_file($ruta)) {
                $conexiones[] = (int) json_decode(file_get_contents($ruta), true)['conexion'];
            }
        }
        if (count($conexiones) === 2) {
            $esperando = (int) $observador->query('SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state=\'LOCK WAIT\' AND trx_mysql_thread_id IN ('.implode(',', $conexiones).')')->fetchColumn();
        }
        if ($esperando === 2) {
            break;
        }
        usleep(100000);
    } while (microtime(true) < $limite);
    if ($esperando !== 2) {
        throw new RuntimeException('No se observaron ambos competidores esperando en InnoDB.');
    }
    DB::commit();
    foreach ($procesos as $proceso) {
        if ($proceso->wait() !== 0) {
            throw new RuntimeException('Falló un proceso competidor.');
        }
    }
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach ($procesos as $proceso) {
        if ($proceso->isRunning()) {
            $proceso->stop();
        }
    }
}
$estados = [];
foreach (['1', '2'] as $id) {
    $estados[] = json_decode(file_get_contents($carpeta.'/competidor-'.$id.'-resultado.json'), true, flags: JSON_THROW_ON_ERROR)['estado'];
}
sort($estados);
if ($estados !== [200, 409]) {
    throw new RuntimeException('Resultado concurrente distinto de 200/409.');
}
$eventosDespues = EventoEstadistico::whereHas('equipoPartido', fn ($q) => $q->where('partido_id', $fixture['partido']))->count();
$auditoriaDespues = AuditoriaCambio::where('entidad_tipo', 'partido')->where('entidad_id', $fixture['partido'])->where('accion', 'aplicar_correccion')->count();
if ($eventosDespues !== $eventosAntes + 1 || $auditoriaDespues !== $auditoriaAntes + 1
    || AutorizacionCorreccion::findOrFail($fixture['concurrencia'])->estado !== 'consumida') {
    throw new RuntimeException('Consumo, evento o auditoría duplicados.');
}
$resultado = ['base' => DB::selectOne('SELECT DATABASE() AS nombre')->nombre, 'competidores_en_lock_wait' => $esperando,
    'estados' => $estados, 'eventos_agregados' => $eventosDespues - $eventosAntes, 'auditorias_agregadas' => $auditoriaDespues - $auditoriaAntes,
    'consumo' => 'consumida', 'tipo' => 'Dos procesos PHP independientes; servicios Laravel y conexiones InnoDB reales'];
file_put_contents($carpeta.'/sprint32-concurrencia.json', json_encode($resultado, JSON_PRETTY_PRINT));
echo json_encode($resultado, JSON_PRETTY_PRINT);
