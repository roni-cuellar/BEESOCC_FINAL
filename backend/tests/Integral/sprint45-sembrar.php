<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$claveLaboratorio = getenv('BEESOCC_LAB_PASSWORD');
if (! is_string($claveLaboratorio) || strlen($claveLaboratorio) < 16) {
    throw new RuntimeException('Define BEESOCC_LAB_PASSWORD con una clave nueva de al menos 16 caracteres.');
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$base = DB::connection()->getDatabaseName();
if (DB::selectOne('SELECT @@port AS puerto, VERSION() AS motor')->puerto !== 3317
    || DB::selectOne('SELECT VERSION() AS motor')->motor !== '10.4.32-MariaDB') {
    throw new RuntimeException('Motor o puerto inesperados: detener.');
}
$permisos = [];
foreach (DB::select('SHOW GRANTS') as $fila) {
    $permiso = array_values((array) $fila)[0];
    if (! str_starts_with($permiso, 'GRANT USAGE ON *.*') && ! str_contains($permiso, 'ON `'.$base.'`.* TO')) {
        throw new RuntimeException('Permisos fuera del laboratorio: detener.');
    }
    // No se publican cláusulas de autenticación de SHOW GRANTS.
    $permisos[] = str_starts_with($permiso, 'GRANT USAGE') ? 'USAGE global sin acceso a datos' : 'Privilegios limitados a '.$base;
}
try {
    DB::select('SELECT id FROM beesocc_v2.users LIMIT 1');
    throw new RuntimeException('La cuenta accede a la principal: detener.');
} catch (QueryException $error) {
    if (! in_array($error->errorInfo[1] ?? null, [1044, 1142], true)) {
        throw $error;
    }
}
if (DB::select('SHOW TABLES')) {
    throw new RuntimeException('La base no está vacía: detener sin migrar.');
}
if (Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]) !== 0) {
    throw new RuntimeException('Falló el esquema efímero.');
}
$fixture = DB::transaction(function () use ($claveLaboratorio) {
    $usuarios = [];
    foreach (['admin' => 'Organizador 4.5', 'arbitro' => 'Árbitro 4.5', 'ajeno' => 'Ajeno 4.5'] as $rol => $nombre) {
        $usuarios[$rol] = User::create(['name' => $nombre, 'email' => $rol.'@sprint45.example.test', 'password' => $claveLaboratorio]);
    }
    $admin = $usuarios['admin'];
    $equipos = [];
    foreach (['Águilas', 'Leones', 'Tigres', 'Cóndores', 'Pumas', 'Toros'] as $indice => $nombre) {
        $equipo = $admin->equipos()->create(['nombre' => $nombre.' laboratorio 4.5', 'ciudad' => 'Ciudad ficticia']);
        $jugador = $admin->jugadores()->create(['nombre' => 'Jugador '.($indice + 1), 'apellido' => 'Laboratorio', 'fecha_nacimiento' => '2000-01-01']);
        $equipos[] = ['equipo' => $equipo, 'jugador' => $jugador];
    }
    $torneos = [];
    foreach (['copa' => ['Copa React real 4.5', 6, 'eliminacion_directa'], 'correccion' => ['Copa correcciones 4.5', 4, 'eliminacion_directa'], 'liga' => ['Liga compatible 4.5', 3, 'todos_contra_todos']] as $clave => [$nombre, $cantidad, $formato]) {
        $campeonato = $admin->campeonatos()->create(['nombre' => $nombre, 'temporada' => '2026', 'formato' => $formato]);
        $siembra = $plantillas = [];
        foreach (array_slice($equipos, 0, $cantidad) as $par) {
            $inscripcion = $campeonato->inscripcionesEquipos()->create(['equipo_id' => $par['equipo']->id, 'estado' => 'activo']);
            $plantilla = $inscripcion->plantillas()->create(['jugador_id' => $par['jugador']->id, 'dorsal' => 1, 'posicion' => 'Delantero', 'fecha_ingreso' => '2026-01-01']);
            $siembra[] = $inscripcion->id;
            $plantillas[$inscripcion->id] = $plantilla->id;
        }
        $campeonato->asignacionesArbitro()->create(['usuario_id' => $usuarios['arbitro']->id, 'autorizado_por' => $admin->id, 'fecha_inicio' => now()]);
        $torneos[$clave] = ['id' => $campeonato->id, 'siembra' => $siembra, 'plantillas' => $plantillas];
    }

    return ['usuarios' => array_map(fn ($u) => $u->only('id', 'email'), $usuarios), 'torneos' => $torneos];
});
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
file_put_contents($carpeta.'/sprint45-fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$resultado = ['ok' => true, 'base' => $base, 'puerto' => 3317, 'motor' => '10.4.32-MariaDB', 'permisos' => $permisos, 'principal_inaccesible' => true, 'torneos' => array_map(fn ($t) => $t['id'], $fixture['torneos'])];
file_put_contents(__DIR__.'/sprint45-aislamiento.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($resultado).PHP_EOL;
