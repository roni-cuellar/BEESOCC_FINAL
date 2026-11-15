<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$pruebas = [];
function verificar42(bool $condicion, string $nombre): void
{
    global $pruebas;
    if (! $condicion) {
        throw new RuntimeException($nombre);
    }
    $pruebas[] = $nombre;
}
$esperadas = [
    'fases' => ['id', 'campeonato_id', 'orden', 'nombre', 'tipo', 'estado', 'clasificados_por_grupo'],
    'grupos' => ['id', 'fase_id', 'codigo', 'orden'],
    'fase_equipos' => ['id', 'fase_id', 'campeonato_equipo_id', 'grupo_id', 'siembra', 'estado'],
    'rondas' => ['id', 'fase_id', 'numero', 'nombre', 'modalidad', 'desempate', 'estado'],
    'llaves' => ['id', 'ronda_id', 'numero', 'estado', 'ganador_fase_equipo_id', 'metodo_resolucion', 'penales_plaza_1', 'penales_plaza_2'],
    'llave_plazas' => ['id', 'llave_id', 'posicion', 'fase_equipo_id', 'origen_tipo', 'origen_grupo_id', 'origen_puesto', 'origen_llave_id'],
];
$base = DB::connection()->getDatabaseName();
foreach ($esperadas as $tabla => $campos) {
    $columnas = DB::select('SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$base, $tabla]);
    verificar42(array_column($columnas, 'COLUMN_NAME') === $campos, 'Columnas completas: '.$tabla);
    verificar42(DB::selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [$base, $tabla])->ENGINE === 'InnoDB', 'InnoDB: '.$tabla);
    $indices = DB::select('SHOW INDEX FROM `'.$tabla.'`');
    verificar42(collect($indices)->contains('Key_name', 'PRIMARY'), 'PK: '.$tabla);
    verificar42(collect($indices)->contains(fn ($i) => $i->Non_unique === 0 && $i->Key_name !== 'PRIMARY'), 'Unicidad de negocio: '.$tabla);
}
$claves = DB::select("SELECT TABLE_NAME,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME IN ('fases','grupos','fase_equipos','rondas','llaves','llave_plazas')", [$base]);
verificar42(count($claves) === 12 && collect($claves)->every(fn ($c) => $c->DELETE_RULE === 'RESTRICT'), 'Doce FK nuevas con RESTRICT');
$partidos = DB::select("SELECT COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='partidos' AND COLUMN_NAME IN ('fase_id','grupo_id','llave_id','numero_serie')", [$base]);
verificar42(count($partidos) === 4 && collect($partidos)->every(fn ($c) => $c->IS_NULLABLE === 'YES'), 'Ubicación histórica nullable');
verificar42(count(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME='partidos' AND REFERENCED_TABLE_NAME IN ('fases','grupos','llaves')", [$base])) === 3, 'Tres FK de ubicación en partidos');
verificar42(count(DB::select("SHOW INDEX FROM partidos WHERE Key_name='partidos_llave_id_numero_serie_unique'")) === 2, 'Índice único llave/serie');
// Ensayos SQL dentro de transacciones de la copia: nunca contra la principal.
$campeonato = DB::table('campeonatos')->first()->id;
DB::beginTransaction();
try {
    $fase = DB::table('fases')->insertGetId(['campeonato_id' => $campeonato, 'orden' => 1, 'nombre' => 'Prueba SQL', 'tipo' => 'eliminacion']);
    $ronda = DB::table('rondas')->insertGetId(['fase_id' => $fase, 'numero' => 1, 'nombre' => 'SQL', 'modalidad' => 'unico', 'desempate' => 'penales']);
    $llave = DB::table('llaves')->insertGetId(['ronda_id' => $ronda, 'numero' => 1]);
    foreach (['posicion' => ['llave_id' => $llave, 'posicion' => 3, 'origen_tipo' => 'bye'],
        'fk' => ['llave_id' => 99999999, 'posicion' => 1, 'origen_tipo' => 'bye']] as $nombre => $valores) {
        try {
            DB::table('llave_plazas')->insert($valores);
            throw new RuntimeException('Restricción no aplicada: '.$nombre);
        } catch (QueryException $error) {
            verificar42(in_array($error->errorInfo[1] ?? null, $nombre === 'posicion' ? [4025] : [1452], true), 'Rechazo SQL: '.$nombre);
        }
    }
    DB::table('llave_plazas')->insert(['llave_id' => $llave, 'posicion' => 1, 'origen_tipo' => 'bye']);
    try {
        DB::table('llave_plazas')->insert(['llave_id' => $llave, 'posicion' => 1, 'origen_tipo' => 'bye']);
        throw new RuntimeException('Aceptó posición duplicada.');
    } catch (QueryException $error) {
        verificar42(($error->errorInfo[1] ?? null) === 1062, 'Rechazo de plaza duplicada');
    }
} finally {
    DB::rollBack();
}
$prefijoEvidencia = getenv('BEESOCC_SPRINT_EVIDENCIA') === '43' ? 'sprint43' : 'sprint42';
file_put_contents(__DIR__.'/'.$prefijoEvidencia.'-esquema.json', json_encode(['ok' => true, 'base' => $base, 'comprobaciones' => count($pruebas), 'pruebas' => $pruebas], JSON_PRETTY_PRINT));
echo json_encode(['ok' => true, 'comprobaciones' => count($pruebas)]);
