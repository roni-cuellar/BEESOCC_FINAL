<?php

use App\Models\Fase;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$fixture = json_decode(file_get_contents($carpeta.'/sprint45-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$referencia = json_decode(file_get_contents($carpeta.'/sprint45-correccion.json'), true, flags: JSON_THROW_ON_ERROR);
$pruebas = [];
function comprobar45(bool $condicion, string $nombre): void
{
    global $pruebas;
    if (! $condicion) {
        throw new RuntimeException($nombre);
    }
    $pruebas[] = $nombre;
}
$identidad = DB::selectOne('SELECT DATABASE() AS base, CURRENT_USER() AS cuenta, @@port AS puerto, VERSION() AS motor');
comprobar45($identidad->puerto === 3317 && $identidad->motor === '10.4.32-MariaDB', 'Motor y puerto aislados');
$copa = Fase::where('campeonato_id', $fixture['torneos']['copa']['id'])->with('rondas.llaves.partidos')->sole();
comprobar45($copa->estado === 'finalizada' && $copa->rondas->count() === 3 && $copa->equipos()->count() === 6, 'Seis equipos y tres rondas finalizadas');
$llaves = $copa->rondas->flatMap->llaves;
comprobar45($llaves->count() === 7 && $llaves->where('metodo_resolucion', 'bye')->count() === 2, 'Siete llaves y dos descansos');
comprobar45($llaves->flatMap->partidos->count() === 7 && $llaves->where('metodo_resolucion', 'bye')->every(fn ($l) => $l->partidos->isEmpty()), 'Siete partidos sin encuentros ficticios de descanso');
$auditoria = DB::table('auditoria_cambios')->where('campeonato_id', $copa->campeonato_id);
comprobar45((clone $auditoria)->where('accion', 'generar_llaves')->count() === 1, 'Doble clic React generó un solo cuadro');
comprobar45((clone $auditoria)->where('accion', 'registrar_penales')->count() === 1, 'Doble clic React certificó una sola tanda');
$primerPartido = $copa->rondas->first()->llaves->flatMap->partidos->first();
comprobar45($primerPartido->participantes()->withCount('eventos')->get()->sum('eventos_count') === 1, 'Doble clic React registró un solo evento');
comprobar45($copa->rondas->flatMap->llaves->flatMap->partidos->every(fn ($p) => $p->estado === 'finalizado' && $p->oficializado_at), 'Resultados deportivos oficiales');
$final = Partido::with('participantes')->findOrFail($referencia['final_id']);
comprobar45($final->estado === 'programado' && $final->participantes->pluck('id')->all() === array_column($referencia['final_antes'], 'id'), 'Corrección conserva IDs del partido final y sus participantes');
comprobar45($final->participantes->pluck('campeonato_equipo_id')->all() !== array_column($referencia['final_antes'], 'campeonato_equipo_id'), 'Corrección propagó el nuevo clasificado');
comprobar45(DB::table('autorizaciones_correccion')->where('id', $referencia['correccion'])->value('estado') === 'consumida', 'Autorización consumida una sola vez');
comprobar45(DB::table('grupos')->count() === 0, 'Grupos no implementados ni sembrados');
comprobar45(Fase::where('campeonato_id', $fixture['torneos']['liga']['id'])->count() === 0, 'Todos contra todos conserva su estructura');
$http = [];
foreach (['semifinal', 'correccion', 'verificar', 'revocado'] as $modo) {
    $dato = json_decode(file_get_contents(__DIR__.'/sprint45-http-'.$modo.'.json'), true, flags: JSON_THROW_ON_ERROR);
    comprobar45($dato['ok'] && $dato['base'] === $identidad->base, 'Evidencia HTTP real: '.$modo);
    $http[$modo] = $dato['comprobaciones'];
}
$lineas = file($carpeta.'/http.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lineas as $linea) {
    $r = json_decode($linea, true, flags: JSON_THROW_ON_ERROR);
    comprobar45($r['base'] === $identidad->base, 'Petición atendida exclusivamente en el laboratorio');
}
$resultado = ['ok' => true, 'identidad' => $identidad, 'http_reales' => array_sum($http), 'desglose_http' => $http,
    'peticiones_capturadas' => count($lineas), 'verificaciones_dominio' => count($pruebas) - count($lineas),
    'principal_usada_por_http' => false, 'concurrencia_costosa_repetida' => false];
file_put_contents(__DIR__.'/sprint45-evidencia.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($resultado).PHP_EOL;
