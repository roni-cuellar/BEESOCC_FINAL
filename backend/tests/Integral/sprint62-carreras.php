<?php

// Procesos Laravel independientes; no simula HTTP ni reemplaza sus pruebas.
use App\Http\Controllers\Api\CampeonatoEquipoController;
use App\Http\Controllers\Api\EventoEstadisticoController;
use App\Http\Controllers\Api\PartidoController;
use App\Http\Controllers\Api\PlantillaController;
use App\Models\Campeonato;
use App\Models\CampeonatoEquipo;
use App\Models\Llave;
use App\Models\Partido;
use App\Models\Plantilla;
use App\Models\User;
use App\Services\AvanceEliminacionService;
use App\Services\CicloCampeonatoService;
use App\Services\GeneracionEliminacionService;
use App\Services\SiembraEliminacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
// Un fallo CLI debe devolver error al orquestador, sin imprimir configuración privada.
set_exception_handler(function (Throwable $error) {
    fwrite(STDERR, 'Validación 6.2 fallida: '.get_class($error).PHP_EOL);
    exit(1);
});
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
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
            'alta' => app(PlantillaController::class)->store($r, CampeonatoEquipo::findOrFail($op['id'])),
            'editar' => app(PlantillaController::class)->update($r, Plantilla::findOrFail($op['id'])),
            'retiro' => app(CampeonatoEquipoController::class)->update($r, CampeonatoEquipo::findOrFail($op['id'])),
            'calendario' => app(PartidoController::class)->generarCalendario(Campeonato::findOrFail($op['id'])),
            'evento' => app(EventoEstadisticoController::class)->store($r, Partido::findOrFail($op['id'])),
            'inicio' => app(CicloCampeonatoService::class)->iniciar(Campeonato::findOrFail($op['id'])),
            'avance' => app(AvanceEliminacionService::class)->avanzar(Llave::findOrFail($op['id'])),
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
    if (realpath(dirname($ruta)) !== realpath($carpeta) || ! preg_match('/^sprint62-operacion-\d+-[ab]\.json$/', basename($ruta))) {
        throw new RuntimeException('Operación fuera del laboratorio.');
    }
    $op = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    $conexion = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    file_put_contents($ruta.'.lista', (string) $conexion);
    $estado = ejecutarOperacion($op);
    file_put_contents($ruta.'.resultado', json_encode(compact('estado', 'conexion'), JSON_THROW_ON_ERROR));
    exit;
}
if ($modo !== 'ejecutar' || is_file($carpeta.'/sprint62-carreras.json')) {
    throw new RuntimeException('Modo inválido o carreras ya ejecutadas: no sobrescribir.');
}
$motor = json_decode(file_get_contents(getenv('BEESOCC_MARIADB_CONFIG')), true, flags: JSON_THROW_ON_ERROR);
$observador = new PDO('mysql:host=127.0.0.1;port=3317;charset=utf8mb4', 'root', $motor['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (strcasecmp(rtrim(str_replace('\\', '/', $observador->query('SELECT @@datadir')->fetchColumn()), '/'), $motor['datadir']) !== 0) {
    throw new RuntimeException('Observador fuera del laboratorio.');
}
$fixture = json_decode(file_get_contents($carpeta.'/sprint62-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$admin = $fixture['usuarios']['admin']['id'];
$arbitro = $fixture['usuarios']['arbitro']['id'];
$resultados = [];
foreach (['jugador', 'dorsal', 'alta_baja', 'cambio_dorsal', 'baja_evento', 'retiro_calendario', 'inicio_alta', 'retiro_avance'] as $numero => $caso) {
    $c = User::findOrFail($admin)->campeonatos()->create(['nombre' => 'Carrera ficticia '.$caso, 'temporada' => '2026', 'formato' => 'todos_contra_todos', 'estado' => 'planificacion']);
    $c->configuracion()->create(['numero_vueltas' => 1, 'orden_desempate' => 'puntos,diferencia_goles,goles_favor', 'modalidad' => 'Ficticia', 'minimo_jugadores' => 1]);
    $ids = [];
    foreach (array_slice($fixture['equipos'], 0, 2) as $e) {
        $ids[] = $c->inscripcionesEquipos()->create(['equipo_id' => $e['equipo_id'], 'estado' => 'activo'])->id;
    }
    $datos = ['jugador_id' => $fixture['equipos'][0]['jugador_id'], 'dorsal' => 10, 'posicion' => 'Ficticia', 'fecha_ingreso' => today()->toDateString()];
    $a = ['tipo' => 'alta', 'usuario' => $admin, 'id' => $ids[0], 'datos' => $datos];
    $b = $a;
    if ($caso === 'jugador') {
        $b['id'] = $ids[1];
    } elseif ($caso === 'dorsal') {
        $b['datos']['jugador_id'] = $fixture['equipos'][1]['jugador_id'];
    } elseif (in_array($caso, ['alta_baja', 'cambio_dorsal', 'baja_evento', 'inicio_alta'])) {
        $p = Plantilla::create($datos + ['campeonato_equipo_id' => $ids[0]]);
        if ($caso === 'cambio_dorsal') {
            $otro = Plantilla::create(array_replace($datos, ['campeonato_equipo_id' => $ids[0], 'jugador_id' => $fixture['equipos'][1]['jugador_id'], 'dorsal' => 11]));
            $a = ['tipo' => 'editar', 'usuario' => $admin, 'id' => $p->id, 'datos' => ['dorsal' => 12]];
            $b = array_replace($a, ['id' => $otro->id]);
        } else {
            $b = ['tipo' => 'editar', 'usuario' => $admin, 'id' => $p->id, 'datos' => ['fecha_salida' => today()->toDateString()]];
        }
        if (in_array($caso, ['baja_evento', 'inicio_alta'])) {
            Plantilla::create(array_replace($datos, ['campeonato_equipo_id' => $ids[1], 'jugador_id' => $fixture['equipos'][1]['jugador_id']]));
            $c->asignacionesArbitro()->create(['usuario_id' => $arbitro, 'autorizado_por' => $admin, 'fecha_inicio' => now()]);
            $partido = $c->partidos()->create(['jornada' => 1, 'estado' => $caso === 'baja_evento' ? 'en_curso' : 'programado']);
            $local = $partido->participantes()->create(['campeonato_equipo_id' => $ids[0], 'rol' => 'local']);
            $partido->participantes()->create(['campeonato_equipo_id' => $ids[1], 'rol' => 'visitante']);
            if ($caso === 'baja_evento') {
                $c->update(['estado' => 'en_curso']);
                $a = ['tipo' => 'evento', 'usuario' => $arbitro, 'id' => $partido->id, 'datos' => ['tipo' => 'gol', 'minuto' => 10, 'equipo_partido_id' => $local->id, 'plantilla_id' => $p->id]];
            } else {
                $a = ['tipo' => 'inicio', 'usuario' => $admin, 'id' => $c->id];
                $b = ['tipo' => 'alta', 'usuario' => $admin, 'id' => $ids[0], 'datos' => array_replace($datos, ['jugador_id' => $fixture['equipos'][2]['jugador_id'], 'dorsal' => 12])];
            }
        }
    } elseif ($caso === 'retiro_avance') {
        Auth::shouldUse('web');
        Auth::guard('web')->setUser(User::findOrFail($admin));
        $c->update(['formato' => 'eliminacion_directa']);
        $servicio = app(GeneracionEliminacionService::class);
        $fase = $servicio->crearFase($c, 'Final ficticia');
        $datosCuadro = ['siembra' => $ids, 'rondas' => [['modalidad' => 'unico', 'desempate' => 'penales']]];
        $vista = app(SiembraEliminacionService::class)->previsualizar($fase, $datosCuadro);
        $servicio->generar($fase, $datosCuadro + ['confirmacion' => $vista['confirmacion']]);
        $partido = $c->partidos()->sole();
        $plantilla = Plantilla::create($datos + ['campeonato_equipo_id' => $ids[0]]);
        $local = $partido->participantes()->where('rol', 'local')->sole();
        // Resultado oficial ficticio preparado; la carrera ejercita retiro contra avance.
        DB::table('partidos')->where('id', $partido->id)->update(['estado' => 'finalizado', 'tipo_resultado' => 'deportivo', 'oficializado_at' => now()]);
        $local->eventos()->create(['plantilla_id' => $plantilla->id, 'tipo' => 'gol', 'minuto' => 10]);
        $c->update(['estado' => 'en_curso']);
        $a = ['tipo' => 'retiro', 'usuario' => $admin, 'id' => $ids[0], 'datos' => ['estado' => 'retirado']];
        $b = ['tipo' => 'avance', 'usuario' => $admin, 'id' => $partido->llave_id];
    } else {
        $a = ['tipo' => 'retiro', 'usuario' => $admin, 'id' => $ids[0], 'datos' => ['estado' => 'retirado']];
        $b = ['tipo' => 'calendario', 'usuario' => $admin, 'id' => $c->id];
    }
    $procesos = [];
    $auditoriasAntes = DB::table('auditoria_cambios')->where('campeonato_id', $c->id)->count();
    DB::beginTransaction();
    try {
        Campeonato::whereKey($c->id)->lockForUpdate()->firstOrFail();
        $bloqueador = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        foreach (['a' => $a, 'b' => $b] as $letra => $op) {
            $ruta = $carpeta.'/sprint62-operacion-'.$c->id.'-'.$letra.'.json';
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
    $permitidos = match ($caso) {
        'jugador', 'dorsal' => [[201, 409]],
        'alta_baja' => [[200, 201], [200, 409]],
        'cambio_dorsal' => [[200, 409]],
        'baja_evento' => [[201, 409]],
        'retiro_calendario' => [[200, 422], [201, 409]],
        'inicio_alta' => [[200, 201], [200, 409]],
        'retiro_avance' => [[200, 409]],
    };
    $activas = Plantilla::whereHas('campeonatoEquipo', fn ($q) => $q->where('campeonato_id', $c->id))->whereNull('fecha_salida')->get();
    $duplicadosJugador = $activas->count() !== $activas->pluck('jugador_id')->unique()->count();
    $duplicadosDorsal = $activas->count() !== $activas->map(fn ($p) => $p->campeonato_equipo_id.':'.$p->dorsal)->unique()->count();
    $auditorias = DB::table('auditoria_cambios')->where('campeonato_id', $c->id)->count() - $auditoriasAntes;
    $exitos = count(array_filter($estados, fn ($s) => $s < 300));
    // Calendario no genera auditoría propia; retiro sí. Los rechazos nunca auditan.
    $auditoriaEsperada = $caso === 'retiro_calendario' && $estados[1] === 201 ? 0 : $exitos;
    $calendarioInvalido = $caso === 'retiro_calendario' && $c->partidos()->exists() && $c->inscripcionesEquipos()->where('estado', 'retirado')->exists();
    if ($conexiones !== $esperas || ! in_array($ordenados, $permitidos, true) || $duplicadosJugador || $duplicadosDorsal || $auditorias !== $auditoriaEsperada || $calendarioInvalido) {
        file_put_contents($carpeta.'/sprint62-fallo-'.$caso.'.json', json_encode(compact('estados', 'auditorias', 'auditoriaEsperada', 'conexiones', 'esperas')));
        throw new RuntimeException('Integridad o rollback no acreditados: '.$caso);
    }
    $resultados[] = ['caso' => $caso, 'estados' => $estados, 'dos_conexiones_en_lock_wait' => true, 'sin_duplicados' => true, 'auditoria_solo_exitos' => true];
    echo 'Carrera aprobada: '.$caso.PHP_EOL;
}
file_put_contents($carpeta.'/sprint62-carreras.json', json_encode(['ok' => true, 'carreras' => $resultados], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
