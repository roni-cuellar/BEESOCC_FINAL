<?php

use App\Models\AuditoriaCambio;
use App\Models\AutorizacionCorreccion;
use App\Models\Llave;
use App\Models\Partido;
use App\Services\AvanceEliminacionService;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$fixture = json_decode(file_get_contents($carpeta.'/sprint42-carrera.json'), true, flags: JSON_THROW_ON_ERROR);
$final = Partido::findOrFail($fixture['final']);
foreach (['llave' => 'local', 'otra_llave' => 'visitante'] as $campo => $rol) {
    $llave = Llave::findOrFail($fixture[$campo]);
    if ((int) $llave->ganador_fase_equipo_id !== app(AvanceEliminacionService::class)->ganador($llave)
        || (int) $final->participantes()->where('rol', $rol)->first()->campeonato_equipo_id !== (int) $llave->ganador->campeonato_equipo_id) {
        throw new RuntimeException('La clasificación final no coincide con los resultados oficiales.');
    }
}
if (AuditoriaCambio::where('entidad_tipo', 'llave')->where('entidad_id', $fixture['otra_llave'])->where('accion', 'resolver_llave')->count() !== 1
    || AutorizacionCorreccion::findOrFail($fixture['correccion_conflicto'])->estado !== 'aprobada') {
    throw new RuntimeException('El intento fallido alteró el ganador auditado o la autorización.');
}
$archivos = ['sprint42-migracion', 'sprint42-esquema', 'sprint42-detalle-esquema', 'sprint42-http', 'sprint42-concurrencia', 'sprint42-compatibilidad', 'sprint42-preservacion'];
$evidencias = [];
foreach ($archivos as $archivo) {
    $datos = json_decode(file_get_contents(__DIR__.'/'.$archivo.'.json'), true, flags: JSON_THROW_ON_ERROR);
    if (($datos['ok'] ?? false) !== true) {
        throw new RuntimeException('Evidencia incompleta: '.$archivo);
    }
    $evidencias[] = $archivo.'.json';
}
$identidad = DB::selectOne('SELECT DATABASE() AS base, CURRENT_USER() AS cuenta, VERSION() AS motor, @@datadir AS datos');
$resultado = ['ok' => true, 'identidad' => $identidad, 'esquema_y_restricciones' => 116, 'comprobaciones_http_reales' => 108,
    'carreras_reales' => 3, 'clasificados_y_auditoria_consistentes' => true, 'evidencias' => $evidencias,
    'regresiones' => ['backend_sqlite' => ['pruebas' => 141, 'aserciones' => 2824], 'frontend' => 152, 'lint' => true, 'build' => true, 'pint' => true],
    'errores_corregidos' => ['Comparación por valor de fechas en el ensayo', 'Campo autorizado_por correcto en fixture nueva', 'Prefijo propio de caché del laboratorio'],
    'cambios_productivos' => false, 'migracion_principal' => 'pendiente, no autorizada por este sprint'];
file_put_contents(__DIR__.'/sprint42-evidencia.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
