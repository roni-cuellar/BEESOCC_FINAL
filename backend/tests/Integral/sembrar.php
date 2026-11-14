<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
$app = iniciarEntornoIntegral();

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (DB::selectOne('SELECT COUNT(*) AS cantidad FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->cantidad !== 0) {
    throw new RuntimeException('Detenido: la base nueva ya contiene tablas.');
}
// La cuenta de ejecución debe carecer incluso de lectura sobre la principal.
try {
    DB::select('SELECT id FROM beesocc_v2.users LIMIT 1');
    throw new RuntimeException('Detenido: la cuenta puede acceder a la principal.');
} catch (QueryException $e) {
    if (! in_array($e->errorInfo[1] ?? null, [1044, 1142], true)) {
        throw $e;
    }
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('No fue posible migrar la base aislada.');
}
$usuarios = [];
foreach (['admin' => 'Organizador Integral', 'arbitro-a' => 'Árbitra Ana Integral',
    'arbitro-b' => 'Árbitro Bruno Integral', 'ajeno' => 'Usuario Sin Autorización'] as $correo => $nombre) {
    $usuarios[$correo] = User::create(['name' => $nombre, 'email' => $correo.'@beesocc23c.test', 'password' => 'PruebaArbitral23C!2026']);
}
$admin = $usuarios['admin'];
$campeonato = $admin->campeonatos()->create(['nombre' => 'Copa Integral 2.3C', 'temporada' => '2026', 'formato' => 'todos_contra_todos']);
$inscripciones = [];
foreach (['Águilas Integral' => ['Ana', 'Camila'], 'Leones Integral' => ['Luis', 'Marco']] as $nombre => $jugadores) {
    $equipo = $admin->equipos()->create(['nombre' => $nombre, 'ciudad' => 'Ciudad de prueba']);
    $inscripcion = $campeonato->inscripcionesEquipos()->create(['equipo_id' => $equipo->id, 'estado' => 'activo']);
    foreach ($jugadores as $indice => $nombreJugador) {
        $jugador = $admin->jugadores()->create(['nombre' => $nombreJugador, 'apellido' => 'Integral', 'fecha_nacimiento' => '2000-01-01']);
        $inscripcion->plantillas()->create(['jugador_id' => $jugador->id, 'dorsal' => $indice + 1,
            'posicion' => 'Delantero', 'fecha_ingreso' => '2026-01-01']);
    }
    $inscripciones[] = $inscripcion;
}
$partido = $campeonato->partidos()->create(['jornada' => 1, 'fecha_hora' => now(), 'lugar' => 'Cancha de laboratorio', 'estado' => 'programado']);
$partido->participantes()->createMany([
    ['campeonato_equipo_id' => $inscripciones[0]->id, 'rol' => 'local'],
    ['campeonato_equipo_id' => $inscripciones[1]->id, 'rol' => 'visitante'],
]);
echo json_encode(['base_activa' => DB::selectOne('SELECT DATABASE() AS base')->base,
    'campeonato' => $campeonato->id, 'partido' => $partido->id, 'usuarios_ficticios' => array_map(fn ($u) => $u->email, $usuarios),
    'asignacion' => 'Se realizará desde React con sesión real del administrador.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
