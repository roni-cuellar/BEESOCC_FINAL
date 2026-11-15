<?php

use App\Models\Fase;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
DB::statement('SET SESSION TRANSACTION READ ONLY');
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$carrera = json_decode(file_get_contents($carpeta.'/sprint52-concurrencia.json'), true, flags: JSON_THROW_ON_ERROR);
$fases = Fase::where('tipo', 'grupos')->get();
$verificaciones = [];
foreach ($fases as $fase) {
    $grupos = $fase->grupos()->pluck('id')->all();
    $miembros = $fase->equipos()->get();
    if ($miembros->pluck('campeonato_equipo_id')->unique()->count() !== $miembros->count()
        || $miembros->contains(fn ($m) => ! in_array($m->grupo_id, $grupos, true))) {
        throw new RuntimeException('Hay pertenencias duplicadas o parciales.');
    }
    $pares = [];
    $jornadas = [];
    foreach ($fase->partidos()->with('participantes')->get() as $partido) {
        $ids = $partido->participantes->pluck('campeonato_equipo_id')->all();
        sort($ids);
        $clave = $partido->grupo_id.':'.implode(':', $ids);
        if (count($ids) !== 2 || count(array_unique($ids)) !== 2 || isset($pares[$clave]) || $partido->llave_id) {
            throw new RuntimeException('Hay partidos parciales, repetidos o cruces eliminatorios.');
        }
        $pares[$clave] = true;
        foreach ($ids as $id) {
            $claveJornada = $partido->jornada.':'.$id;
            if (isset($jornadas[$claveJornada]) || ! $miembros->contains(fn ($m) => $m->campeonato_equipo_id === $id && $m->grupo_id === $partido->grupo_id)) {
                throw new RuntimeException('Hay equipos repetidos en jornada o partidos entre grupos.');
            }
            $jornadas[$claveJornada] = true;
        }
    }
    $auditoria = DB::table('auditoria_cambios')->where('entidad_tipo', 'fase')->where('entidad_id', $fase->id);
    foreach (['confirmar_grupos', 'generar_calendario_grupos'] as $accion) {
        if ((clone $auditoria)->where('accion', $accion)->count() > 1) {
            throw new RuntimeException('Hay una generación duplicada en auditoría.');
        }
    }
    if ($fase->rondas()->exists()) {
        throw new RuntimeException('Se crearon cruces de grupos fuera del alcance.');
    }
    $verificaciones[] = ['equipos' => $miembros->count(), 'grupos' => count($grupos), 'partidos' => count($pares)];
}
if (! $carrera['ok'] || count($carrera['carreras']) !== 3) {
    throw new RuntimeException('Faltan carreras aprobadas.');
}
foreach ($carrera['carreras'] as $prueba) {
    if (! $prueba['ok'] || $prueba['solicitudes_http_en_lock_wait'] !== 2 || count(array_unique($prueba['conexiones'])) !== 2) {
        throw new RuntimeException('Falta acreditación de dos conexiones concurrentes.');
    }
}
$http = [];
foreach (['grupos', 'compatibilidad', 'correcciones', 'carreras'] as $modo) {
    $resultado = json_decode(file_get_contents($carpeta.'/sprint52-http-'.$modo.'.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! $resultado['ok']) {
        throw new RuntimeException('Queda un escenario HTTP sin aprobar.');
    }
    $http[$modo] = $resultado['comprobaciones'];
}
$resultado = ['ok' => true, 'http_real' => $http, 'total_http_real' => array_sum($http),
    'carreras_innodb' => 3, 'conexiones_distintas_por_carrera' => 2,
    'sin_duplicados_ni_partidos_parciales' => true, 'sin_cruces_de_grupos' => true, 'fases_revisadas' => count($verificaciones)];
file_put_contents($carpeta.'/sprint52-verificacion.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($resultado).PHP_EOL;
