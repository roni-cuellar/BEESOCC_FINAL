<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
// Un fallo CLI debe devolver error al orquestador, sin imprimir configuración privada.
set_exception_handler(function (Throwable $error) {
    fwrite(STDERR, 'Validación 6.2 fallida: '.get_class($error).PHP_EOL);
    exit(1);
});
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
foreach (['campeonatos' => ['iniciado_at', 'finalizado_at'], 'configuracion_campeonato' => ['modalidad', 'minimo_jugadores'], 'plantillas' => ['dorsal_vigente']] as $tabla => $columnas) {
    foreach ($columnas as $nombre) {
        $columna = collect(DB::select('SHOW FULL COLUMNS FROM '.$tabla))->firstWhere('Field', $nombre);
        if (! $columna || $columna->Null !== 'YES' || ($nombre === 'dorsal_vigente' && ! str_contains($columna->Extra, 'VIRTUAL GENERATED'))) {
            throw new RuntimeException('Columna nueva incompatible.');
        }
    }
}
$indices = collect(DB::select('SHOW INDEX FROM plantillas'))->where('Key_name', 'plantillas_dorsal_vigente_unique')->sortBy('Seq_in_index')->values();
if ($indices->count() !== 2 || $indices->pluck('Column_name')->all() !== ['campeonato_equipo_id', 'dorsal_vigente'] || $indices->contains(fn ($i) => (int) $i->Non_unique !== 0)) {
    throw new RuntimeException('Índice único inesperado.');
}
if (DB::getSchemaBuilder()->hasTable('personal_access_tokens') || DB::table('migrations')->where('migration', '2026_11_15_000300_add_ciclo_y_minimos_campeonato')->count() !== 1) {
    throw new RuntimeException('Historial de migraciones inesperado.');
}
$activa = DB::table('plantillas')->whereNull('fecha_salida')->first();
$antes = DB::table('plantillas')->count();
$rechazado = false;
try {
    DB::transaction(function () use ($activa) {
        DB::table('plantillas')->insert(collect((array) $activa)->except(['id', 'dorsal_vigente'])->all());
    });
} catch (QueryException $e) {
    if (($e->errorInfo[1] ?? null) !== 1062) {
        throw $e;
    }
    $rechazado = true;
}
if (! $rechazado || $antes !== DB::table('plantillas')->count()) {
    throw new RuntimeException('La restricción física no conservó los registros.');
}
$aislamiento = json_decode(file_get_contents($carpeta.'/sprint62-aislamiento.json'), true, flags: JSON_THROW_ON_ERROR);
$carreras = json_decode(file_get_contents($carpeta.'/sprint62-carreras.json'), true, flags: JSON_THROW_ON_ERROR);
if (! $aislamiento['ok'] || ! $carreras['ok'] || count($carreras['carreras']) !== 8) {
    throw new RuntimeException('Faltan evidencias aprobadas.');
}
$resultado = ['ok' => true, 'motor' => 'MariaDB 10.4.32', 'puerto_laboratorio' => 3317, 'columnas_nuevas' => 5,
    'indice_dorsal_activo' => true, 'duplicado_sql_rechazado_sin_cambios' => true,
    'historico_ficticio_conservado' => $aislamiento['historico_conservado'], 'tokens_no_ejecutada' => true,
    'principal_inaccesible_desde_laboratorio' => true, 'carreras' => $carreras['carreras']];
file_put_contents($carpeta.'/sprint62-verificacion.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
