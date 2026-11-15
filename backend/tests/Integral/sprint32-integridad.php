<?php

use App\Models\AuditoriaCambio;
use App\Models\AutorizacionCorreccion;
use App\Models\EventoEstadistico;
use App\Models\User;
use App\Services\CorreccionPartidoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$fixture = json_decode(file_get_contents($carpeta.'/sprint32-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$pruebas = [];
function comprobarIntegridad(bool $valor, string $nombre): void
{
    global $pruebas;
    if (! $valor) {
        throw new RuntimeException($nombre);
    }
    $pruebas[] = $nombre;
}
$columnas = [];
foreach (DB::select("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,DATA_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_MAXIMUM_LENGTH,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('partidos','auditoria_cambios','autorizaciones_correccion')") as $c) {
    $columnas[$c->TABLE_NAME][$c->COLUMN_NAME] = $c;
}
$tipos = [
    'auditoria_cambios' => ['id' => 'bigint', 'campeonato_id' => 'bigint', 'usuario_id' => 'bigint', 'entidad_tipo' => 'varchar', 'entidad_id' => 'bigint', 'accion' => 'varchar', 'motivo' => 'text', 'anterior_json' => 'longtext', 'posterior_json' => 'longtext', 'fecha' => 'datetime'],
    'autorizaciones_correccion' => ['id' => 'bigint', 'partido_id' => 'bigint', 'solicitado_por_id' => 'bigint', 'autorizado_por_id' => 'bigint', 'motivo' => 'text', 'estado' => 'enum', 'alcance' => 'enum', 'fecha_resolucion' => 'datetime', 'fecha_consumo' => 'datetime'],
];
foreach ($tipos as $tabla => $campos) {
    foreach ($campos as $nombre => $tipo) {
        $c = $columnas[$tabla][$nombre];
        $nullable = in_array($nombre, ['anterior_json', 'posterior_json', 'autorizado_por_id', 'fecha_resolucion', 'fecha_consumo'], true);
        comprobarIntegridad($c->DATA_TYPE === $tipo && ($c->IS_NULLABLE === 'YES') === $nullable
            && ($tipo !== 'bigint' || str_contains($c->COLUMN_TYPE, 'unsigned')), 'Tipo y nulabilidad completos: '.$tabla.'.'.$nombre);
    }
    comprobarIntegridad(str_contains($columnas[$tabla]['id']->EXTRA, 'auto_increment'), 'ID autoincremental: '.$tabla);
}
comprobarIntegridad(collect(DB::select("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('auditoria_cambios','autorizaciones_correccion')"))->every(fn ($t) => $t->ENGINE === 'InnoDB'), 'Tablas nuevas en InnoDB');
comprobarIntegridad($columnas['partidos']['tipo_resultado']->COLUMN_TYPE === "enum('deportivo','administrativo')"
    && trim($columnas['partidos']['tipo_resultado']->COLUMN_DEFAULT, "'") === 'deportivo', 'Tipo de resultado y valor predeterminado');
comprobarIntegridad($columnas['partidos']['oficializado_at']->DATA_TYPE === 'datetime'
    && $columnas['partidos']['oficializado_at']->IS_NULLABLE === 'YES', 'Fecha oficial datetime nullable');
comprobarIntegridad($columnas['autorizaciones_correccion']['estado']->COLUMN_TYPE === "enum('solicitada','aprobada','rechazada','consumida')", 'Cuatro estados de autorización');
comprobarIntegridad($columnas['autorizaciones_correccion']['alcance']->COLUMN_TYPE === "enum('eventos','resultado')", 'Alcances de autorización');
foreach (['autorizado_por_id', 'fecha_resolucion', 'fecha_consumo'] as $nombre) {
    comprobarIntegridad($columnas['autorizaciones_correccion'][$nombre]->IS_NULLABLE === 'YES', 'Nullable: '.$nombre);
}
foreach (['entidad_tipo', 'accion'] as $nombre) {
    comprobarIntegridad((int) $columnas['auditoria_cambios'][$nombre]->CHARACTER_MAXIMUM_LENGTH === 40, 'Longitud de '.$nombre);
}
foreach (['anterior_json', 'posterior_json'] as $nombre) {
    comprobarIntegridad($columnas['auditoria_cambios'][$nombre]->IS_NULLABLE === 'YES', 'JSON nullable: '.$nombre);
}
comprobarIntegridad((int) DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auditoria_cambios' AND COLUMN_NAME='entidad_id' AND REFERENCED_TABLE_NAME IS NOT NULL")->n === 0, 'Referencia de entidad como metadato, sin FK inventada');

function debeRechazarSql(callable $operacion, int $codigo, string $nombre): void
{
    DB::beginTransaction();
    try {
        $operacion();
        throw new RuntimeException('La base aceptó un valor inválido: '.$nombre);
    } catch (QueryException $e) {
        comprobarIntegridad(($e->errorInfo[1] ?? null) === $codigo, $nombre);
    } finally {
        DB::rollBack();
    }
}
$usuario = User::findOrFail($fixture['arbitro_id']);
$fila = ['campeonato_id' => $fixture['campeonato'], 'usuario_id' => $usuario->id,
    'entidad_tipo' => 'partido', 'entidad_id' => $fixture['partido'], 'accion' => 'ensayo_constraint',
    'motivo' => 'Dato ficticio de validación', 'fecha' => now()->toDateTimeString()];
foreach (['anterior_json', 'posterior_json'] as $nombre) {
    debeRechazarSql(fn () => DB::table('auditoria_cambios')->insert($fila + [$nombre => '{invalido']), 4025, 'CHECK JSON real: '.$nombre);
}
debeRechazarSql(fn () => DB::table('auditoria_cambios')->insert(array_replace($fila, ['usuario_id' => 99999999])), 1452, 'FK de auditoría rechaza actor inexistente');
debeRechazarSql(fn () => DB::table('autorizaciones_correccion')->where('id', $fixture['concurrencia'])->update(['estado' => 'inventado']), 1265, 'ENUM rechaza estado inexistente');

Auth::shouldUse('web');
Auth::guard('web')->setUser($usuario);
$cantidadEventos = EventoEstadistico::count();
$cantidadAuditorias = AuditoriaCambio::count();
Event::listen('eloquent.creating: '.AuditoriaCambio::class, function ($a) {
    if ($a->accion === 'aplicar_correccion') {
        throw new RuntimeException('Fallo controlado de auditoría 3.2');
    }
});
try {
    app(CorreccionPartidoService::class)->aplicar(AutorizacionCorreccion::findOrFail($fixture['rollback']), [[
        'accion' => 'registrar', 'datos' => ['tipo' => 'gol', 'minuto' => 80,
            'equipo_partido_id' => $fixture['local'], 'plantilla_id' => $fixture['plantilla_local']],
    ]]);
    throw new RuntimeException('No se detectó el fallo de auditoría.');
} catch (RuntimeException $e) {
    comprobarIntegridad($e->getMessage() === 'Fallo controlado de auditoría 3.2', 'Fallo de bitácora reproducido en MariaDB');
} finally {
    Event::forget('eloquent.creating: '.AuditoriaCambio::class);
}
$pendiente = AutorizacionCorreccion::findOrFail($fixture['rollback']);
comprobarIntegridad(EventoEstadistico::count() === $cantidadEventos && AuditoriaCambio::count() === $cantidadAuditorias
    && $pendiente->estado === 'aprobada' && $pendiente->fecha_consumo === null, 'Rollback real ante fallo de bitácora, sin consumo ni escrituras parciales');
$auditoria = AuditoriaCambio::where('entidad_id', $fixture['partido'])->where('accion', 'oficializar')->sole();
$motivo = $auditoria->motivo;
try {
    $auditoria->update(['motivo' => 'Cambio no permitido']);
    throw new RuntimeException('Bitácora modificable.');
} catch (LogicException) {
    comprobarIntegridad($auditoria->fresh()->motivo === $motivo, 'Inmutabilidad Eloquent frente a MariaDB');
}
file_put_contents($carpeta.'/sprint32-integridad.json', json_encode(['aprobadas' => count($pruebas), 'pruebas' => $pruebas], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['aprobadas' => count($pruebas), 'pruebas' => $pruebas], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
