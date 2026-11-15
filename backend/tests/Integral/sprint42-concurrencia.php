<?php

use App\Models\AuditoriaCambio;
use App\Models\AutorizacionCorreccion;
use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Llave;
use App\Models\Partido;
use App\Models\User;
use App\Services\AuditoriaPartidoService;
use App\Services\CorreccionPartidoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
try {
    $carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
    $fixture = json_decode(file_get_contents($carpeta.'/sprint42-carrera.json'), true, flags: JSON_THROW_ON_ERROR);
    $servidor = json_decode(file_get_contents(getenv('BEESOCC_MARIADB_CONFIG')), true, flags: JSON_THROW_ON_ERROR);
    $observador = new PDO('mysql:host=127.0.0.1;port=3317;charset=utf8mb4', 'root', $servidor['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if (strcasecmp(rtrim(str_replace('\\', '/', $observador->query('SELECT @@datadir')->fetchColumn()), '/'), $servidor['datadir']) !== 0) {
        throw new RuntimeException('Observador fuera del laboratorio.');
    }
    function carrera42(string $caso, array $acciones): array
    {
        global $fixture, $carpeta, $observador;
        $procesos = [];
        $conexiones = [];
        DB::beginTransaction();
        try {
            Campeonato::whereKey($fixture['campeonato'])->lockForUpdate()->firstOrFail();
            foreach ($acciones as $indice => $accion) {
                $id = (string) ($indice + 1);
                $prefijo = $carpeta.'/carrera42-'.($fixture['ensayo'] ?? 'inicial').'-'.$caso.'-'.$id;
                if (is_file($prefijo.'-listo.json')) {
                    throw new RuntimeException('El caso ya se ejecutó; no sobrescribir evidencia.');
                }
                $proceso = new Process([PHP_BINARY, __DIR__.'/sprint42-competidor.php', $caso, $id, $accion], base_path());
                $proceso->setTimeout(35);
                $proceso->start();
                $procesos[] = $proceso;
                // Esperar cada admisión permite ordenar el inicio antes de la corrección en el tercer caso.
                $limite = microtime(true) + 15;
                do {
                    if (is_file($prefijo.'-listo.json')) {
                        $conexion = (int) json_decode(file_get_contents($prefijo.'-listo.json'), true)['conexion'];
                        $espera = (int) $observador->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state='LOCK WAIT' AND trx_mysql_thread_id=".$conexion)->fetchColumn();
                        if ($espera === 1) {
                            $conexiones[] = $conexion;
                            break;
                        }
                    }
                    usleep(100000);
                } while (microtime(true) < $limite);
                if (count($conexiones) !== $indice + 1) {
                    throw new RuntimeException('No se observó un competidor en LOCK WAIT.');
                }
            }
            $esperando = (int) $observador->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state='LOCK WAIT' AND trx_mysql_thread_id IN (".implode(',', $conexiones).')')->fetchColumn();
            if ($esperando !== 2) {
                throw new RuntimeException('No estaban ambos procesos esperando en InnoDB.');
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
        $resultados = [];
        foreach (['1', '2'] as $id) {
            $resultados[] = json_decode(file_get_contents($carpeta.'/carrera42-'.($fixture['ensayo'] ?? 'inicial').'-'.$caso.'-'.$id.'-resultado.json'), true, flags: JSON_THROW_ON_ERROR);
        }
        if (array_column($resultados, 'estado') !== [200, 409]) {
            throw new RuntimeException('Resultado concurrente inesperado en '.$caso.': '.json_encode($resultados));
        }

        return ['caso' => $caso, 'competidores_en_lock_wait' => $esperando, 'estados' => array_column($resultados, 'estado')];
    }
    $evidencias = [];
    $antes = Partido::where('campeonato_id', $fixture['campeonato'])->count();
    $evidencias[] = carrera42('avance', ['avanzar', 'avanzar']);
    $fase = Fase::findOrFail($fixture['fase']);
    $final = $fase->rondas()->where('numero', 2)->first()->llaves()->first()->partidos()->sole();
    if (Partido::where('campeonato_id', $fixture['campeonato'])->count() !== $antes + 1
        || AuditoriaCambio::where('entidad_tipo', 'llave')->where('entidad_id', $fixture['llave'])->where('accion', 'resolver_llave')->count() !== 1) {
        throw new RuntimeException('Partido o clasificación duplicados.');
    }
    $evidencias[] = carrera42('correccion', ['corregir', 'corregir']);
    $llave = Llave::findOrFail($fixture['llave']);
    if (AutorizacionCorreccion::findOrFail($fixture['correccion'])->estado !== 'consumida'
        || (int) $llave->ganador_fase_equipo_id !== (int) $llave->plazas()->where('posicion', 2)->first()->fase_equipo_id
        || (int) $final->participantes()->where('rol', 'local')->first()->campeonato_equipo_id !== (int) $llave->ganador->campeonato_equipo_id
        || AuditoriaCambio::where('accion', 'aplicar_correccion')->where('entidad_id', $fixture['partido'])->count() !== 1) {
        throw new RuntimeException('Consumo, ganador o propagación concurrente incorrectos.');
    }
    $fixture['final'] = $final->id;
    $otra = Llave::findOrFail($fixture['otra_llave'])->partidos()->first();
    Auth::shouldUse('web');
    Auth::guard('web')->setUser(User::findOrFail($fixture['arbitro']));
    $correccion = app(CorreccionPartidoService::class)->solicitar($otra, ['motivo' => 'Conflicto ficticio con inicio posterior', 'alcance' => 'eventos']);
    Auth::guard('web')->setUser(User::findOrFail($fixture['admin']));
    app(CorreccionPartidoService::class)->resolver($correccion, 'aprobada');
    $local = $otra->participantes()->where('rol', 'local')->first();
    $visitante = $otra->participantes()->where('rol', 'visitante')->first();
    $fixture['correccion_conflicto'] = $correccion->id;
    $fixture['operaciones_conflicto'] = [['accion' => 'eliminar', 'evento_id' => $local->eventos()->sole()->id],
        ['accion' => 'registrar', 'datos' => ['tipo' => 'gol', 'minuto' => 25, 'equipo_partido_id' => $visitante->id, 'plantilla_id' => $visitante->campeonatoEquipo->plantillas()->sole()->id]]];
    file_put_contents($carpeta.'/sprint42-carrera.json', json_encode($fixture));
    // Comparar valores serializados, no la identidad de dos objetos Carbon equivalentes.
    $captura = json_encode(app(AuditoriaPartidoService::class)->capturar($otra), JSON_THROW_ON_ERROR);
    $auditorias = AuditoriaCambio::where('entidad_tipo', 'partido')->where('entidad_id', $otra->id)->count();
    $evidencias[] = carrera42('inicio_correccion', ['iniciar', 'corregir']);
    if (json_encode(app(AuditoriaPartidoService::class)->capturar($otra), JSON_THROW_ON_ERROR) !== $captura
        || AuditoriaCambio::where('entidad_tipo', 'partido')->where('entidad_id', $otra->id)->count() !== $auditorias
        || $correccion->refresh()->estado !== 'aprobada' || $correccion->fecha_consumo !== null
        || $final->refresh()->estado !== 'en_curso') {
        throw new RuntimeException('El conflicto dejó cambios parciales o consumió la aprobación.');
    }
    $resultado = ['ok' => true, 'base' => DB::connection()->getDatabaseName(), 'carreras' => $evidencias,
        'sin_duplicados' => true, 'sin_cambios_parciales' => true, 'final_id_conservado' => $final->id,
        'tipo' => 'Procesos PHP independientes, servicios/controlador Laravel y bloqueos InnoDB reales'];
    file_put_contents(__DIR__.'/sprint42-concurrencia.json', json_encode($resultado, JSON_PRETTY_PRINT));
    echo json_encode($resultado, JSON_PRETTY_PRINT);
} catch (Throwable $error) {
    fwrite(STDERR, 'Falló la comprobación concurrente: '.$error->getMessage());
    exit(1);
}
