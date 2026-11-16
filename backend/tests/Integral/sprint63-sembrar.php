<?php

use App\Models\Campeonato;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
require __DIR__.'/sprint33-instantanea.php';
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

$base = DB::connection()->getDatabaseName();
$identidad = DB::selectOne('SELECT @@port AS puerto, VERSION() AS motor');
if ($identidad->puerto !== 3317 || $identidad->motor !== '10.4.32-MariaDB') {
    throw new RuntimeException('Motor o puerto inesperados: detener.');
}
$permisos = [];
foreach (DB::select('SHOW GRANTS') as $fila) {
    $permiso = array_values((array) $fila)[0];
    if (! str_starts_with($permiso, 'GRANT USAGE ON *.*') && ! str_contains($permiso, 'ON `'.$base.'`.* TO')) {
        throw new RuntimeException('Permisos fuera del laboratorio: detener.');
    }
    $permisos[] = str_starts_with($permiso, 'GRANT USAGE') ? 'USAGE sin acceso global' : 'Privilegios solo en la base nueva';
}
try {
    DB::select('SELECT id FROM beesocc_v2.users LIMIT 1');
    throw new RuntimeException('La cuenta accede a la principal: detener.');
} catch (QueryException $error) {
    if (! in_array($error->errorInfo[1] ?? null, [1044, 1142], true)) {
        throw $error;
    }
}
// La misma cuenta tampoco puede autenticarse contra el servidor principal.
try {
    $conexion = config('database.connections.mysql');
    new PDO('mysql:host=127.0.0.1;port=3306;dbname=beesocc_v2;charset=utf8mb4', $conexion['username'], $conexion['password']);
    throw new RuntimeException('La cuenta temporal se autenticó en la principal: detener.');
} catch (PDOException $error) {
    if (! in_array($error->errorInfo[1] ?? null, [1045, 1044], true)) {
        throw new RuntimeException('No se comprobó el rechazo de la cuenta temporal en la principal.');
    }
}
if (DB::select('SHOW TABLES')) {
    throw new RuntimeException('La base no está vacía: detener sin migrar.');
}
$rutas = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => ! str_contains(basename($p), 'personal_access_tokens') && ! str_contains(basename($p), '000300') && ! str_contains(basename($p), '000400')));
if (Artisan::call('migrate', ['--database' => 'mysql', '--path' => $rutas, '--realpath' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Falló el esquema exclusivo del laboratorio.');
}
if (DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
    throw new RuntimeException('No debe ejecutarse la migración de tokens.');
}
$fixture = DB::transaction(function () {
    $usuarios = [];
    // Clave exclusiva del ensayo derivada de la clave aleatoria temporal.
    $clave = hash_hmac('sha256', 'sprint63-login', config('app.key'));
    foreach (['admin' => 'Organizador ficticio 6.3', 'arbitro' => 'Árbitro ficticio 6.3', 'ajeno' => 'Ajeno ficticio 6.3'] as $rol => $nombre) {
        $usuarios[$rol] = User::create(['name' => $nombre, 'email' => $rol.'@sprint63.example.test', 'password' => $clave]);
    }
    $equipos = [];
    for ($i = 1; $i <= 7; $i++) {
        $equipo = $usuarios['admin']->equipos()->create(['nombre' => 'Equipo ficticio 6.3 '.$i, 'ciudad' => 'Ciudad ficticia']);
        $jugador = $usuarios['admin']->jugadores()->create(['nombre' => 'Jugador '.$i, 'apellido' => 'Ficticio 6.3', 'fecha_nacimiento' => '2000-01-01']);
        $equipos[] = ['equipo_id' => $equipo->id, 'jugador_id' => $jugador->id];
    }

    return ['usuarios' => array_map(fn ($u) => $u->only('id', 'email'), $usuarios), 'equipos' => $equipos];
});
// Histórico ficticio anterior a 000300, sin reescribir sus datos originales.
$historico = DB::transaction(function () use ($fixture) {
    $c = Campeonato::create(['administrador_id' => $fixture['usuarios']['admin']['id'], 'nombre' => 'Histórico ficticio 2020', 'temporada' => '2020', 'formato' => 'todos_contra_todos', 'estado' => 'planificacion']);
    $e = $c->inscripcionesEquipos()->create(['equipo_id' => $fixture['equipos'][0]['equipo_id'], 'estado' => 'activo']);

    return $e->plantillas()->create(['jugador_id' => $fixture['equipos'][0]['jugador_id'], 'dorsal' => 8, 'posicion' => 'Ficticia', 'fecha_ingreso' => '2020-01-01', 'fecha_salida' => '2021-01-01'])->id;
});
$antes = (array) DB::table('plantillas')->find($historico);
if (Artisan::call('migrate', ['--database' => 'mysql', '--path' => 'database/migrations/2026_11_15_000300_add_ciclo_y_minimos_campeonato.php', '--force' => true]) !== 0) {
    throw new RuntimeException('Falló 000300 en la copia aislada.');
}
$despues = (array) DB::table('plantillas')->find($historico);
foreach ($antes as $campo => $valor) {
    if ($despues[$campo] !== $valor) {
        throw new RuntimeException('El histórico ficticio cambió: detener.');
    }
}
if ($despues['dorsal_vigente'] !== null) {
    throw new RuntimeException('Dorsal vigente inesperado en inscripción cerrada.');
}
// Ensayo de 000400 sobre registros ficticios anteriores a sus columnas.
DB::table('partidos')->insert(['campeonato_id' => DB::table('campeonatos')->value('id'), 'jornada' => 1, 'estado' => 'finalizado']);
$originales = instantanea(DB::connection()->getPdo());
if (Artisan::call('migrate', ['--database' => 'mysql', '--path' => 'database/migrations/2026_11_16_000400_add_resoluciones_administrativas.php', '--force' => true]) !== 0) {
    throw new RuntimeException('Falló 000400 en laboratorio.');
}
$posteriores = instantanea(DB::connection()->getPdo(), $originales);
foreach ($originales as $tabla => $original) {
    if ($tabla !== 'migrations' && ($posteriores[$tabla]['cantidad'] !== $original['cantidad'] || $posteriores[$tabla]['sha256'] !== $original['sha256'])) {
        throw new RuntimeException('000400 cambió datos históricos ficticios.');
    }
}
$schema = DB::getSchemaBuilder();
foreach (['partidos' => ['administrativo_beneficiado_id', 'administrativo_sancionado_id', 'administrativo_por_id', 'motivo_administrativo'], 'campeonato_equipos' => ['motivo_inactividad', 'inactivado_at']] as $tabla => $columnas) {
    foreach ($columnas as $campo) {
        $col = collect($schema->getColumns($tabla))->firstWhere('name', $campo);
        if (! $col || ! $col['nullable'] || DB::table($tabla)->whereNotNull($campo)->exists()) {
            throw new RuntimeException('Columna nueva incorrecta o rellenada artificialmente.');
        }
    }
}
$fks = collect($schema->getForeignKeys('partidos'))->filter(fn ($fk) => str_starts_with($fk['columns'][0], 'administrativo_'));
if ($fks->count() !== 3 || $fks->contains(fn ($fk) => strtolower($fk['on_delete']) !== 'restrict')) {
    throw new RuntimeException('Faltan FK restrictivas.');
}
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
file_put_contents($carpeta.'/sprint63-fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$resultado = ['ok' => true, 'historico_conservado' => true, 'columnas_000400' => 6, 'fk_000400' => 3, 'datos_previos_000400_conservados' => true, 'motor' => $identidad->motor, 'puerto' => $identidad->puerto, 'cuenta_limitada' => true,
    'tokens_no_ejecutada' => true, 'rutas_fisicas_verificadas' => true, 'principal_inaccesible' => true, 'autenticacion_principal_rechazada' => true, 'permisos' => $permisos];
file_put_contents($carpeta.'/sprint63-aislamiento.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($resultado).PHP_EOL;
