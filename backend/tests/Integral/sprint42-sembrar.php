<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

$claveLaboratorio = getenv('BEESOCC_LAB_PASSWORD');
if (! is_string($claveLaboratorio) || strlen($claveLaboratorio) < 16) {
    throw new RuntimeException('Define BEESOCC_LAB_PASSWORD con una clave nueva de al menos 16 caracteres.');
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$archivo = dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/sprint42-fixture.json';
if (is_file($archivo)) {
    throw new RuntimeException('No se repite la siembra.');
}
$fixture = DB::transaction(function () use ($claveLaboratorio) {
    $sufijo = bin2hex(random_bytes(5));
    $admin = User::create(['name' => 'Organizador ficticio 4.2', 'email' => 's42-admin-'.$sufijo.'@example.test', 'password' => $claveLaboratorio]);
    $arbitro = User::create(['name' => 'Árbitro ficticio 4.2', 'email' => 's42-arbitro-'.$sufijo.'@example.test', 'password' => $claveLaboratorio]);
    $ajeno = User::create(['name' => 'Ajeno ficticio 4.2', 'email' => 's42-ajeno-'.$sufijo.'@example.test', 'password' => $claveLaboratorio]);
    $equipos = [];
    for ($i = 1; $i <= 6; $i++) {
        $equipo = $admin->equipos()->create(['nombre' => 'Equipo ficticio 4.2 '.$i, 'ciudad' => 'Laboratorio']);
        $jugador = $admin->jugadores()->create(['nombre' => 'Jugador '.$i, 'apellido' => 'Ficticio', 'fecha_nacimiento' => '2000-01-01']);
        $equipos[] = ['equipo' => $equipo->id, 'jugador' => $jugador->id];
    }

    return ['admin' => $admin->only('id', 'email'), 'arbitro' => $arbitro->only('id', 'email'), 'ajeno' => $ajeno->only('id', 'email'), 'equipos' => $equipos];
});
file_put_contents($archivo, json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['ok' => true, 'usuarios_ficticios' => 3, 'equipos_ficticios' => 6]);
