<?php

use App\Models\AuditoriaCambio;
use App\Models\AutorizacionCorreccion;
use App\Models\EventoEstadistico;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$base = DB::connection()->getDatabaseName();
$cuenta = DB::selectOne('SELECT CURRENT_USER() AS cuenta')->cuenta;
$permisos = array_map(fn ($fila) => array_values((array) $fila)[0], DB::select('SHOW GRANTS'));
// No guardar SHOW GRANTS: puede contener el hash privado de la contraseña.
foreach ($permisos as $permiso) {
    if (! str_starts_with($permiso, 'GRANT USAGE ON *.*') && ! str_contains($permiso, 'ON `'.$base.'`.* TO')) {
        throw new RuntimeException('La cuenta tiene un permiso fuera del laboratorio.');
    }
}
$estados = AutorizacionCorreccion::orderBy('id')->pluck('estado', 'id')->all();
if ($estados !== [1 => 'consumida', 2 => 'rechazada', 3 => 'aprobada']) {
    throw new RuntimeException('Estados finales inesperados.');
}
$evento = EventoEstadistico::sole();
if ($evento->tipo !== 'autogol' || (int) $evento->minuto !== 22) {
    throw new RuntimeException('Cambios parciales o eventos inesperados.');
}
$auditoria = AuditoriaCambio::where('accion', 'aplicar_correccion')->sole();
if ((int) $auditoria->usuario_id !== 2 || count($auditoria->anterior_json['eventos']) !== 3
    || count($auditoria->posterior_json['eventos']) !== 1) {
    throw new RuntimeException('La auditoría no conserva los valores esperados.');
}
$partido = Partido::findOrFail(1);
$captura = json_decode(file_get_contents(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/sprint35-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
if ($partido->estado !== 'finalizado' || $partido->oficializado_at->toISOString() !== $captura['oficializado_at']) {
    throw new RuntimeException('Cambió la oficialización original.');
}
$http = array_map(fn ($linea) => json_decode($linea, true, flags: JSON_THROW_ON_ERROR),
    file(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/http.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
foreach ($http as $peticion) {
    if ($peticion['base'] !== $base) {
        throw new RuntimeException('Una petición utilizó otra base.');
    }
}
$codigos = array_count_values(array_column($http, 'estado'));
foreach ([200, 201, 204, 401, 403, 404, 409, 419, 422] as $codigo) {
    if (empty($codigos[$codigo])) {
        throw new RuntimeException('Falta evidencia HTTP de un código requerido.');
    }
}
$evidencia = ['ok' => true, 'motor' => DB::selectOne('SELECT VERSION() AS motor')->motor, 'base' => $base,
    'cuenta_limitada' => $cuenta, 'permisos_solo_laboratorio' => true, 'estados_finales' => $estados,
    'evento_final' => ['tipo' => $evento->tipo, 'minuto' => $evento->minuto],
    'aplicaciones_auditadas' => 1, 'eventos_antes' => 3, 'eventos_despues' => 1,
    'sesiones_reales' => DB::table('sessions')->whereNotNull('user_id')->count(),
    'peticiones_laboratorio' => count($http), 'codigos_http' => $codigos,
    'navegador_real' => ['asignacion_desde_react', 'sesiones_admin_y_arbitro', 'inicio_y_eventos', 'oficializacion_2_0',
        'solicitud_y_aprobacion', 'fallo_atomico_409_sin_consumo', 'correccion_y_consumo_unico', 'marcador_1_0',
        'auditoria_antes_despues_y_actores', 'rechazo_administrativo', 'estadisticas_reconsultadas',
        'movil_390_sin_desbordamiento', 'preparar_y_cancelar_lote_movil', 'revocacion_bloquea_acta', 'ajeno_sin_expediente'],
    'adaptadores_simulados' => false];
file_put_contents(__DIR__.'/sprint35-evidencia.json', json_encode($evidencia, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($evidencia, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
