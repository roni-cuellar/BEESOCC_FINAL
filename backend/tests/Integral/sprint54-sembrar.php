<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
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
$nueva = '2026_11_15_000200_create_desempates_grupos.php';
$rutas = [];
foreach (glob(database_path('migrations/*.php')) as $archivo) {
    if (basename($archivo) !== $nueva && ! str_contains(basename($archivo), 'personal_access_tokens')) {
        $rutas[] = $archivo;
    }
}
if (Artisan::call('migrate', ['--database' => 'mysql', '--path' => $rutas, '--realpath' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Falló el esquema anterior aislado.');
}
$historico = DB::table('campeonatos')->insertGetId(['nombre' => 'Histórico ficticio 5.4', 'temporada' => '2025', 'formato' => 'todos_contra_todos']);
$partidoHistorico = DB::table('partidos')->insertGetId(['campeonato_id' => $historico, 'jornada' => 9, 'estado' => 'finalizado', 'lugar' => 'Sede ficticia']);
$antes = (array) DB::table('partidos')->find($partidoHistorico);
if (Artisan::call('migrate', ['--database' => 'mysql', '--path' => [database_path('migrations/'.$nueva)], '--realpath' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Falló la migración 000200 aislada.');
}
$despues = (array) DB::table('partidos')->find($partidoHistorico);
foreach ($antes as $campo => $valor) {
    if ($despues[$campo] !== $valor) {
        throw new RuntimeException('La migración alteró el histórico ficticio.');
    }
}
if ($despues['grupo_desempate_id'] !== null || DB::getSchemaBuilder()->hasTable('personal_access_tokens')
    || DB::table('migrations')->where('migration', 'like', '%personal_access_tokens%')->exists()) {
    throw new RuntimeException('Migración de tokens ejecutada o histórico alterado.');
}

$fixture = DB::transaction(function () {
    $usuarios = [];
    // Clave exclusiva del ensayo derivada de la clave aleatoria temporal.
    $clave = hash_hmac('sha256', 'sprint54-login', config('app.key'));
    foreach (['admin' => 'Organizador ficticio 5.4', 'arbitro' => 'Árbitro ficticio 5.4', 'ajeno' => 'Ajeno ficticio 5.4'] as $rol => $nombre) {
        $usuarios[$rol] = User::create(['name' => $nombre, 'email' => $rol.'@sprint54.example.test', 'password' => $clave]);
    }
    $equipos = [];
    for ($i = 1; $i <= 7; $i++) {
        $equipo = $usuarios['admin']->equipos()->create(['nombre' => 'Equipo ficticio 5.4 '.$i, 'ciudad' => 'Ciudad ficticia']);
        $jugador = $usuarios['admin']->jugadores()->create(['nombre' => 'Jugador '.$i, 'apellido' => 'Ficticio 5.4', 'fecha_nacimiento' => '2000-01-01']);
        $equipos[] = ['equipo_id' => $equipo->id, 'jugador_id' => $jugador->id];
    }

    return ['usuarios' => array_map(fn ($u) => $u->only('id', 'email'), $usuarios), 'equipos' => $equipos];
});
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
file_put_contents($carpeta.'/sprint54-fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$resultado = ['ok' => true, 'motor' => $identidad->motor, 'puerto' => $identidad->puerto, 'cuenta_limitada' => true,
    'historico_preservado' => true, 'tokens_no_ejecutada' => true, 'rutas_fisicas_verificadas' => true, 'principal_inaccesible' => true, 'autenticacion_principal_rechazada' => true, 'permisos' => $permisos];
file_put_contents($carpeta.'/sprint54-aislamiento.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($resultado).PHP_EOL;
