<?php

// Procesos Laravel independientes; no simula HTTP ni reemplaza sus pruebas.
use App\Models\AutorizacionCorreccion;
use App\Models\Campeonato;
use App\Models\CampeonatoEquipo;
use App\Models\Fase;
use App\Models\Partido;
use App\Models\User;
use App\Services\ClasificacionGruposService;
use App\Services\ControlPartidoService;
use App\Services\DistribucionGruposService;
use App\Services\ResolucionAdministrativaService;
use App\Services\ResultadoOficialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
try {
    iniciarEntornoIntegral();
} catch (Throwable $error) {
    fwrite(STDERR, 'Aislamiento no confirmado; detener sin escrituras.'.PHP_EOL);
    exit(1);
}
// Un fallo CLI debe devolver error al orquestador, sin imprimir configuración privada.
set_exception_handler(function (Throwable $error) {
    fwrite(STDERR, 'Validación 6.3 fallida: '.get_class($error).PHP_EOL);
    exit(1);
});

$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$modo = $argv[1] ?? '';

function ejecutarOperacion(array $op): int
{
    $usuario = User::findOrFail($op['usuario']);
    Auth::shouldUse('web');
    Auth::guard('web')->setUser($usuario);
    $r = Request::create('/', 'POST', $op['datos'] ?? []);
    $r->setUserResolver(fn () => $usuario);
    try {
        $respuesta = match ($op['tipo']) {
            'resultado' => app(ResolucionAdministrativaService::class)->resolver(Partido::findOrFail($op['id']), $op['datos']),
            'inactivar' => app(ResolucionAdministrativaService::class)->inactivar(CampeonatoEquipo::findOrFail($op['id']), $op['datos']),
            'oficializar' => DB::transaction(function () use ($op) {
                $p = app(ControlPartidoService::class)->bloquearActa(Partido::findOrFail($op['id']));
                app(ResultadoOficialService::class)->oficializar($p);

                return response()->json(['ok' => true]);
            }),
            'clasificar' => app(ClasificacionGruposService::class)->clasificar(Fase::findOrFail($op['id']), $op['datos']['confirmacion']),
        };

        return $respuesta instanceof JsonResponse ? $respuesta->getStatusCode() : 200;
    } catch (HttpExceptionInterface $e) {
        return $e->getStatusCode();
    } catch (ValidationException $e) {
        return 422;
    }
}

if ($modo === 'worker') {
    $ruta = $argv[2];
    if (realpath(dirname($ruta)) !== realpath($carpeta) || ! preg_match('/^sprint63-operacion-\d+-[ab]\.json$/', basename($ruta))) {
        throw new RuntimeException('Operación fuera del laboratorio.');
    }
    $op = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    $conexion = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    file_put_contents($ruta.'.lista', (string) $conexion);
    $estado = ejecutarOperacion($op);
    file_put_contents($ruta.'.resultado', json_encode(compact('estado', 'conexion'), JSON_THROW_ON_ERROR));
    exit;
}
$archivoResultado = $carpeta.'/sprint63-carreras'.($modo === 'ejecutar_grupos' ? '-grupos' : '').'.json';
if (! in_array($modo, ['ejecutar', 'ejecutar_grupos'], true) || is_file($archivoResultado)) {
    throw new RuntimeException('Modo inválido o carreras ya ejecutadas: no sobrescribir.');
}
$motor = json_decode(file_get_contents(getenv('BEESOCC_MARIADB_CONFIG')), true, flags: JSON_THROW_ON_ERROR);
$observador = new PDO('mysql:host=127.0.0.1;port=3317;charset=utf8mb4', 'root', $motor['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (strcasecmp(rtrim(str_replace('\\', '/', $observador->query('SELECT @@datadir')->fetchColumn()), '/'), $motor['datadir']) !== 0) {
    throw new RuntimeException('Observador fuera del laboratorio.');
}
$fixture = json_decode(file_get_contents($carpeta.'/sprint63-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$admin = $fixture['usuarios']['admin']['id'];
$arbitro = $fixture['usuarios']['arbitro']['id'];
$resultados = [];
foreach ($modo === 'ejecutar_grupos' ? ['clasificacion_inactividad'] : ['doble_resultado', 'doble_inactividad', 'resultado_oficializacion', 'doble_rectificacion'] as $caso) {
    $c = User::findOrFail($admin)->campeonatos()->create(['nombre' => 'Carrera ficticia 6.3 '.$caso, 'temporada' => '2026', 'formato' => 'todos_contra_todos', 'estado' => 'en_curso']);
    $ids = [];
    foreach (array_slice($fixture['equipos'], 0, 2) as $e) {
        $ids[] = $c->inscripcionesEquipos()->create(['equipo_id' => $e['equipo_id'], 'estado' => 'activo'])->id;
    }
    $c->asignacionesArbitro()->create(['usuario_id' => $arbitro, 'autorizado_por' => $admin, 'fecha_inicio' => now()]);
    $partido = $c->partidos()->create(['jornada' => 1, 'estado' => 'en_curso']);
    foreach (['local', 'visitante'] as $n => $rol) {
        $partido->participantes()->create(['campeonato_equipo_id' => $ids[$n], 'rol' => $rol]);
    }
    $datos = ['beneficiado_id' => $ids[0], 'sancionado_id' => $ids[1], 'motivo' => 'Decisión ficticia concurrente'];
    $a = ['tipo' => 'resultado', 'usuario' => $admin, 'id' => $partido->id, 'datos' => $datos];
    $b = $a;
    if ($caso === 'doble_inactividad') {
        $a = ['tipo' => 'inactivar', 'usuario' => $admin, 'id' => $ids[0], 'datos' => ['tipo' => 'retiro', 'motivo' => 'Retiro ficticio motivado']];
        $b = array_replace($a, ['datos' => ['tipo' => 'descalificacion', 'motivo' => 'Descalificación ficticia motivada']]);
    }
    if ($caso === 'resultado_oficializacion') {
        $b = ['tipo' => 'oficializar', 'usuario' => $arbitro, 'id' => $partido->id];
    }
    if ($caso === 'doble_rectificacion') {
        Auth::shouldUse('web');
        Auth::guard('web')->setUser(User::findOrFail($admin));
        app(ResolucionAdministrativaService::class)->resolver($partido, $datos);
        $autorizacion = new AutorizacionCorreccion;
        $autorizacion->forceFill(['partido_id' => $partido->id, 'solicitado_por_id' => $admin, 'autorizado_por_id' => $admin, 'motivo' => 'Rectificación ficticia', 'alcance' => 'resultado', 'estado' => 'aprobada', 'fecha_resolucion' => now()])->save();
        $a['datos'] = ['beneficiado_id' => $ids[1], 'sancionado_id' => $ids[0], 'motivo' => 'Rectificación ficticia concurrente', 'autorizacion_id' => $autorizacion->id];
        $b = $a;
    }
    if ($caso === 'clasificacion_inactividad') {
        // Esta carrera nueva no repite las cuatro anteriores ya acreditadas.
        $c = User::findOrFail($admin)->campeonatos()->create(['nombre' => 'Clasificación e inactividad ficticias', 'temporada' => '2026', 'formato' => 'grupos_eliminacion', 'estado' => 'planificacion']);
        $ids = [];
        foreach (array_slice($fixture['equipos'], 0, 4) as $e) {
            $ids[] = $c->inscripcionesEquipos()->create(['equipo_id' => $e['equipo_id'], 'estado' => 'activo'])->id;
        }
        Auth::shouldUse('web');
        Auth::guard('web')->setUser(User::findOrFail($admin));
        $servicio = app(DistribucionGruposService::class);
        $fase = $servicio->crearFase($c, 'Grupos ficticios');
        $v = $servicio->previsualizar($fase, ['metodo' => 'manual', 'cantidad_grupos' => 2, 'clasificados_por_grupo' => 1, 'grupos' => [array_slice($ids, 0, 2), array_slice($ids, 2)]]);
        unset($v['fase_id']);
        $servicio->confirmar($fase, $v);
        $servicio->generarCalendario($fase);
        $c->update(['estado' => 'en_curso']);
        foreach ($c->partidos()->get() as $p) {
            $participantes = $p->participantes()->pluck('campeonato_equipo_id')->all();
            app(ResolucionAdministrativaService::class)->resolver($p, ['beneficiado_id' => $participantes[0], 'sancionado_id' => $participantes[1], 'motivo' => 'Resolución ficticia para carrera']);
        }
        $partido = $c->partidos()->first();
        $fase = $fase->fresh();
        $vista = app(ClasificacionGruposService::class)->preparar($fase);
        if (! $vista['confirmacion']) {
            throw new RuntimeException('Fixture sin clasificación válida');
        }
        $a = ['tipo' => 'inactivar', 'usuario' => $admin, 'id' => $ids[0], 'datos' => ['tipo' => 'descalificacion', 'motivo' => 'Descalificación ficticia concurrente']];
        $b = ['tipo' => 'clasificar', 'usuario' => $admin, 'id' => $fase->id, 'datos' => ['confirmacion' => $vista['confirmacion']]];
    }
    $procesos = [];
    $auditoriasAntes = DB::table('auditoria_cambios')->where('campeonato_id', $c->id)->count();
    DB::beginTransaction();
    try {
        Campeonato::whereKey($c->id)->lockForUpdate()->firstOrFail();
        $bloqueador = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        foreach (['a' => $a, 'b' => $b] as $letra => $op) {
            $ruta = $carpeta.'/sprint63-operacion-'.$c->id.'-'.$letra.'.json';
            file_put_contents($ruta, json_encode($op, JSON_THROW_ON_ERROR));
            $proceso = proc_open([PHP_BINARY, __FILE__, 'worker', $ruta], [0 => ['pipe', 'r'], 1 => ['file', $ruta.'.log', 'a'], 2 => ['file', $ruta.'.log', 'a']], $pipes, null, null, ['bypass_shell' => true]);
            if (! is_resource($proceso)) {
                throw new RuntimeException('No se pudo iniciar un proceso independiente.');
            }
            fclose($pipes[0]);
            $procesos[] = [$proceso, $ruta];
        }
        $limite = microtime(true) + 20;
        $esperadas = [];
        do {
            $esperadas = [];
            foreach ($procesos as [$proceso, $ruta]) {
                if (is_file($ruta.'.lista')) {
                    $esperadas[] = (int) file_get_contents($ruta.'.lista');
                }
            }
            if (count($esperadas) === 2) {
                break;
            }
            usleep(100000);
        } while (microtime(true) < $limite);
        do {
            $q = $observador->prepare("SELECT DISTINCT r.trx_mysql_thread_id FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE b.trx_mysql_thread_id=? AND r.trx_state='LOCK WAIT'");
            $q->execute([$bloqueador]);
            // INNODB_TRX puede conservar una instantánea breve de la carrera anterior.
            $esperas = array_values(array_intersect($esperadas, array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN))));
            if (count($esperas) === 2) {
                break;
            }
            usleep(250000);
        } while (microtime(true) < $limite);
        if (count($esperas) !== 2) {
            throw new RuntimeException('No se acreditaron dos esperas InnoDB: '.$caso);
        }
        DB::commit();
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
    }
    $salidas = [];
    foreach ($procesos as [$proceso, $ruta]) {
        if (proc_close($proceso) !== 0 || ! is_file($ruta.'.resultado')) {
            throw new RuntimeException('Falló un proceso: '.$caso);
        }
        $salidas[] = json_decode(file_get_contents($ruta.'.resultado'), true, flags: JSON_THROW_ON_ERROR);
    }
    $conexiones = array_column($salidas, 'conexion');
    sort($conexiones);
    sort($esperas);
    $estados = array_column($salidas, 'estado');
    $ordenados = $estados;
    sort($ordenados);
    if ($ordenados !== [200, 409] || $conexiones !== $esperas) {
        throw new RuntimeException('Estados o conexiones no acreditados: '.$caso);
    }
    $auditorias = DB::table('auditoria_cambios')->where('campeonato_id', $c->id)->count() - $auditoriasAntes;
    if ($auditorias !== 1 || DB::table('eventos_estadisticos')->count() !== 0 || $c->partidos()->count() !== ($caso === 'clasificacion_inactividad' ? 2 : 1) || $partido->participantes()->count() !== 2) {
        throw new RuntimeException('Cambios parciales o duplicados: '.$caso);
    }
    if ($caso !== 'doble_inactividad' && ! $partido->refresh()->esOficial()) {
        throw new RuntimeException('Oficial inconsistente');
    }
    if ($caso === 'doble_rectificacion' && $autorizacion->refresh()->estado !== 'consumida') {
        throw new RuntimeException('Consumo inconsistente');
    }
    $resultados[] = ['caso' => $caso, 'estados' => $estados, 'dos_conexiones_en_lock_wait' => true, 'auditoria_solo_exito' => true, 'sin_eventos_ficticios' => true, 'sin_cambios_parciales' => true];
    echo 'Carrera aprobada: '.$caso.PHP_EOL;
}
file_put_contents($archivoResultado, json_encode(['ok' => true, 'carreras' => $resultados], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
