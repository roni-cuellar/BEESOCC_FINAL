<?php

use App\Models\User;
use Illuminate\Database\QueryException;
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
$manifiesto = json_decode(file_get_contents(getenv('BEESOCC_SCHEMA_MANIFIESTO')), true, flags: JSON_THROW_ON_ERROR);
$sql = file_get_contents($manifiesto['archivo']);
if (hash('sha256', $sql) !== $manifiesto['sha256'] || preg_match('/(?:CREATE|DROP)\s+DATABASE|\bUSE\s+|`beesocc_v2`\s*\./i', $sql)) {
    throw new RuntimeException('Esquema vacío fuera de alcance');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$cn = config('database.connections.mysql');
$m = new mysqli('127.0.0.1', $cn['username'], $cn['password'], $base, 3317);
$m->multi_query($sql);
do {
    if ($r = $m->store_result()) {
        $r->free();
    }
} while ($m->more_results() && $m->next_result());
$m->close();
foreach (DB::select('SHOW TABLES') as $fila) {
    $tabla = array_values((array) $fila)[0];
    if ($tabla !== 'migrations' && DB::table($tabla)->count() !== 0) {
        throw new RuntimeException('El esquema contiene datos reales');
    }
}
if (DB::table('migrations')->count() !== 21 || DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
    throw new RuntimeException('Historial inesperado');
}
$fixture = DB::transaction(function () {
    $usuarios = [];
    // Clave exclusiva del ensayo derivada de la clave aleatoria temporal.
    $clave = hash_hmac('sha256', 'cierre63-login', config('app.key'));
    foreach (['admin' => 'Organizador ficticio Cierre 6.3', 'arbitro' => 'Árbitro ficticio Cierre 6.3', 'ajeno' => 'Ajeno ficticio Cierre 6.3'] as $rol => $nombre) {
        $usuarios[$rol] = User::create(['name' => $nombre, 'email' => $rol.'@cierre63.example.test', 'password' => $clave]);
    }
    $equipos = [];
    for ($i = 1; $i <= 7; $i++) {
        $equipo = $usuarios['admin']->equipos()->create(['nombre' => 'Equipo ficticio Cierre 6.3 '.$i, 'ciudad' => 'Ciudad ficticia']);
        $jugador = $usuarios['admin']->jugadores()->create(['nombre' => 'Jugador '.$i, 'apellido' => 'Ficticio Cierre 6.3', 'fecha_nacimiento' => '2000-01-01']);
        $equipos[] = ['equipo_id' => $equipo->id, 'jugador_id' => $jugador->id];
    }

    return ['usuarios' => array_map(fn ($u) => $u->only('id', 'email'), $usuarios), 'equipos' => $equipos];
});
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
file_put_contents($carpeta.'/cierre63-fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$resultado = ['ok' => true, 'motor' => $identidad->motor, 'puerto' => $identidad->puerto, 'cuenta_limitada' => true,
    'tokens_no_ejecutada' => true, 'rutas_fisicas_verificadas' => true, 'principal_inaccesible' => true, 'autenticacion_principal_rechazada' => true, 'permisos' => $permisos];
file_put_contents($carpeta.'/cierre63-aislamiento.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($resultado).PHP_EOL;
