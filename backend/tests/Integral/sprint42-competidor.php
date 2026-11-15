<?php

use App\Http\Controllers\Api\PartidoController;
use App\Models\AutorizacionCorreccion;
use App\Models\Llave;
use App\Models\Partido;
use App\Models\User;
use App\Services\AvanceEliminacionService;
use App\Services\CorreccionPartidoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
[$script, $caso, $id, $accion] = $argv;
if (! in_array($caso, ['avance', 'correccion', 'inicio_correccion'], true) || ! in_array($id, ['1', '2'], true)
    || ! in_array($accion, ['avanzar', 'corregir', 'iniciar'], true)) {
    throw new RuntimeException('Competidor no permitido.');
}
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$fixture = json_decode(file_get_contents($carpeta.'/sprint42-carrera.json'), true, flags: JSON_THROW_ON_ERROR);
Auth::shouldUse('web');
Auth::guard('web')->setUser(User::findOrFail($fixture[$accion === 'avanzar' ? 'admin' : 'arbitro']));
$prefijo = $carpeta.'/carrera42-'.($fixture['ensayo'] ?? 'inicial').'-'.$caso.'-'.$id;
file_put_contents($prefijo.'-listo.json', json_encode(['conexion' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id]));
try {
    if ($accion === 'avanzar') {
        app(AvanceEliminacionService::class)->avanzar(Llave::findOrFail($fixture['llave']));
    } elseif ($accion === 'corregir') {
        $correccion = $caso === 'inicio_correccion' ? $fixture['correccion_conflicto'] : $fixture['correccion'];
        $operaciones = $caso === 'inicio_correccion' ? $fixture['operaciones_conflicto'] : $fixture['operaciones'];
        app(CorreccionPartidoService::class)->aplicar(AutorizacionCorreccion::findOrFail($correccion), $operaciones);
    } else {
        $partido = Partido::findOrFail($fixture['final']);
        app(PartidoController::class)->cambiarEstado(Request::create('/api/partidos/'.$partido->id, 'PATCH', ['estado' => 'en_curso']), $partido);
    }
    $resultado = ['estado' => 200];
} catch (HttpException $error) {
    $resultado = ['estado' => $error->getStatusCode()];
} catch (Throwable $error) {
    $resultado = ['estado' => 500, 'tipo_error' => get_class($error)];
}
file_put_contents($prefijo.'-resultado.json', json_encode($resultado));
