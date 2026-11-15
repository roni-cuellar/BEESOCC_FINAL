<?php

use App\Models\AutorizacionCorreccion;
use App\Models\User;
use App\Services\CorreccionPartidoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$id = $argv[1] ?? '';
if (! in_array($id, ['1', '2'], true)) {
    throw new RuntimeException('Competidor no válido.');
}
$fixture = json_decode(file_get_contents($carpeta.'/sprint32-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
Auth::shouldUse('web');
Auth::guard('web')->setUser(User::findOrFail($fixture['arbitro_id']));
file_put_contents($carpeta.'/competidor-'.$id.'-listo.json', json_encode(['conexion' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id]));
try {
    $correccion = AutorizacionCorreccion::findOrFail($fixture['concurrencia']);
    app(CorreccionPartidoService::class)->aplicar($correccion, [['accion' => 'registrar', 'datos' => [
        'tipo' => 'gol', 'minuto' => 77, 'equipo_partido_id' => $fixture['local'], 'plantilla_id' => $fixture['plantilla_local'],
    ]]]);
    $resultado = ['estado' => 200];
} catch (HttpException $e) {
    $resultado = ['estado' => $e->getStatusCode()];
} catch (Throwable $e) {
    $resultado = ['estado' => 500, 'tipo_error' => get_class($e)];
}
file_put_contents($carpeta.'/competidor-'.$id.'-resultado.json', json_encode($resultado));
