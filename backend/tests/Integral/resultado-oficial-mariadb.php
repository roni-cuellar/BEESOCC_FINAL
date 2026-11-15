<?php

// Ensayo de servicios y transacciones reales; no sustituye pruebas HTTP o de navegador.
require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();

use App\Models\AuditoriaCambio;
use App\Models\Campeonato;
use App\Models\EventoEstadistico;
use App\Models\Partido;
use App\Models\User;
use App\Services\AsignacionArbitroService;
use App\Services\ControlPartidoService;
use App\Services\CorreccionPartidoService;
use App\Services\EventoEstadisticoService;
use App\Services\MarcadorService;
use App\Services\ResultadoOficialService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

$pruebas = [];
function comprobarResultado(bool $condicion, string $nombre): void
{
    global $pruebas;
    if (! $condicion) {
        throw new RuntimeException($nombre);
    }
    $pruebas[] = $nombre;
}
Auth::shouldUse('web');
$admin = User::where('email', 'admin@beesocc23c.test')->sole();
$arbitro = User::where('email', 'arbitro-a@beesocc23c.test')->sole();
$liga = Campeonato::findOrFail(1);
$partido = Partido::findOrFail(1);
comprobarResultado($partido->estado === 'programado' && ! $partido->oficializado_at, 'Escenario ficticio sin oficialización previa');
Auth::guard('web')->setUser($admin);
app(AsignacionArbitroService::class)->asignar($liga, $admin, $arbitro);
Auth::guard('web')->setUser($arbitro);
$local = $partido->participantes()->where('rol', 'local')->sole();
$visitante = $partido->participantes()->where('rol', 'visitante')->sole();
$plantillaLocal = $local->campeonatoEquipo->plantillas()->firstOrFail();
$plantillaVisitante = $visitante->campeonatoEquipo->plantillas()->firstOrFail();
DB::transaction(function () use ($partido, $local, $plantillaLocal) {
    $partido = app(ControlPartidoService::class)->bloquearActa($partido);
    $partido->update(['estado' => 'en_curso']);
    $servicio = app(EventoEstadisticoService::class);
    $servicio->registrar($partido, $servicio->validar(['tipo' => 'gol', 'minuto' => 10,
        'equipo_partido_id' => $local->id, 'plantilla_id' => $plantillaLocal->id]));
    app(ResultadoOficialService::class)->oficializar($partido);
});
$partido->refresh();
comprobarResultado($partido->estado === 'finalizado' && $partido->oficializado_at !== null, 'Oficialización persistida en MariaDB');
$oficializado = $partido->oficializado_at->toISOString();
$a = AuditoriaCambio::where('accion', 'oficializar')->sole();
comprobarResultado($a->usuario_id === $arbitro->id && count($a->posterior_json['eventos']) === 1, 'Actor y JSON de oficialización recuperables');
$servicio = app(CorreccionPartidoService::class);
$correccion = $servicio->solicitar($partido, ['alcance' => 'eventos', 'motivo' => 'Revisión ficticia de autogol.']);
Auth::guard('web')->setUser($admin);
$servicio->resolver($correccion, 'aprobada');
Auth::guard('web')->setUser($arbitro);
$alta = ['accion' => 'registrar', 'datos' => ['tipo' => 'autogol', 'minuto' => 12,
    'equipo_partido_id' => $local->id, 'plantilla_id' => $plantillaVisitante->id]];
$servicio->aplicar($correccion, [$alta]);
comprobarResultado($correccion->fresh()->estado === 'consumida' && $correccion->fresh()->fecha_consumo !== null, 'Aprobación consumida con fecha');
comprobarResultado(app(MarcadorService::class)->calcular($partido)['local']['goles'] === 2, 'Marcador derivado de gol y autogol');
try {
    $servicio->aplicar($correccion, [$alta]);
    throw new RuntimeException('Se reutilizó una autorización consumida.');
} catch (HttpException $e) {
    comprobarResultado($e->getStatusCode() === 409 && EventoEstadistico::count() === 2, 'Reutilización rechazada sin duplicar eventos');
}
$a = AuditoriaCambio::where('accion', 'aplicar_correccion')->sole();
comprobarResultado(count($a->anterior_json['eventos']) === 1 && count($a->posterior_json['eventos']) === 2, 'Auditoría JSON antes y después conservada');
comprobarResultado($partido->fresh()->estado === 'finalizado' && $partido->fresh()->oficializado_at->toISOString() === $oficializado, 'Corrección sin reapertura ni cambio de fecha oficial original');
$segunda = $servicio->solicitar($partido, ['alcance' => 'eventos', 'motivo' => 'Ensayo de rollback.']);
Auth::guard('web')->setUser($admin);
$servicio->resolver($segunda, 'aprobada');
Auth::guard('web')->setUser($arbitro);
$cantidad = AuditoriaCambio::count();
try {
    $servicio->aplicar($segunda, [$alta, ['accion' => 'eliminar', 'evento_id' => 999999]]);
    throw new RuntimeException('Debió revertirse el lote inválido.');
} catch (ModelNotFoundException) {
    comprobarResultado(EventoEstadistico::count() === 2 && $segunda->fresh()->estado === 'aprobada'
        && AuditoriaCambio::count() === $cantidad, 'Rollback real de eventos, autorización y auditoría');
}
$claves = DB::select("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN ('auditoria_cambios', 'autorizaciones_correccion')");
comprobarResultado(count($claves) === 5 && collect($claves)->every(fn ($c) => $c->DELETE_RULE === 'RESTRICT'), 'Cinco FK RESTRICT en MariaDB');
$resultado = ['motor' => DB::selectOne('SELECT VERSION() AS version')->version,
    'base' => DB::selectOne('SELECT DATABASE() AS base')->base, 'tipo_prueba' => 'Servicios Laravel y transacciones MariaDB, sin HTTP ni navegador',
    'aprobadas' => count($pruebas), 'pruebas' => $pruebas];
file_put_contents(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/resultado-oficial.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
