<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$pruebas = [];
function detalle42(bool $condicion, string $nombre): void
{
    global $pruebas;
    if (! $condicion) {
        throw new RuntimeException($nombre);
    }
    $pruebas[] = $nombre;
}
$base = DB::connection()->getDatabaseName();
$tipos = [
    'fases' => ['id' => 'bigint', 'campeonato_id' => 'bigint', 'orden' => 'smallint', 'nombre' => 'varchar', 'tipo' => 'varchar', 'estado' => 'varchar', 'clasificados_por_grupo' => 'tinyint'],
    'grupos' => ['id' => 'bigint', 'fase_id' => 'bigint', 'codigo' => 'varchar', 'orden' => 'smallint'],
    'fase_equipos' => ['id' => 'bigint', 'fase_id' => 'bigint', 'campeonato_equipo_id' => 'bigint', 'grupo_id' => 'bigint', 'siembra' => 'smallint', 'estado' => 'varchar'],
    'rondas' => ['id' => 'bigint', 'fase_id' => 'bigint', 'numero' => 'smallint', 'nombre' => 'varchar', 'modalidad' => 'enum', 'desempate' => 'enum', 'estado' => 'varchar'],
    'llaves' => ['id' => 'bigint', 'ronda_id' => 'bigint', 'numero' => 'smallint', 'estado' => 'varchar', 'ganador_fase_equipo_id' => 'bigint', 'metodo_resolucion' => 'varchar', 'penales_plaza_1' => 'tinyint', 'penales_plaza_2' => 'tinyint'],
    'llave_plazas' => ['id' => 'bigint', 'llave_id' => 'bigint', 'posicion' => 'tinyint', 'fase_equipo_id' => 'bigint', 'origen_tipo' => 'enum', 'origen_grupo_id' => 'bigint', 'origen_puesto' => 'tinyint', 'origen_llave_id' => 'bigint'],
];
$nulos = ['fases.clasificados_por_grupo', 'fase_equipos.grupo_id', 'fase_equipos.siembra', 'llaves.ganador_fase_equipo_id', 'llaves.metodo_resolucion',
    'llaves.penales_plaza_1', 'llaves.penales_plaza_2', 'llave_plazas.fase_equipo_id', 'llave_plazas.origen_grupo_id', 'llave_plazas.origen_puesto', 'llave_plazas.origen_llave_id'];
foreach ($tipos as $tabla => $campos) {
    $columnas = collect(DB::select('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [$base, $tabla]))->keyBy('COLUMN_NAME');
    foreach ($campos as $campo => $tipo) {
        $columna = $columnas[$campo];
        detalle42($columna->DATA_TYPE === $tipo && ($columna->IS_NULLABLE === 'YES') === in_array($tabla.'.'.$campo, $nulos, true)
            && (! in_array($tipo, ['bigint', 'smallint', 'tinyint'], true) || str_contains($columna->COLUMN_TYPE, 'unsigned')), 'Tipo, nulabilidad y signo: '.$tabla.'.'.$campo);
    }
    detalle42(str_contains($columnas['id']->EXTRA, 'auto_increment'), 'Autoincremental: '.$tabla);
}
$unicos = ['fases' => ['campeonato_id', 'orden'], 'grupos' => ['fase_id', 'codigo'], 'fase_equipos' => ['fase_id', 'campeonato_equipo_id'],
    'rondas' => ['fase_id', 'numero'], 'llaves' => ['ronda_id', 'numero'], 'llave_plazas' => ['llave_id', 'posicion'], 'partidos' => ['llave_id', 'numero_serie']];
foreach ($unicos as $tabla => $campos) {
    $indices = collect(DB::select('SHOW INDEX FROM `'.$tabla.'`'))->where('Non_unique', 0)->groupBy('Key_name');
    detalle42($indices->contains(fn ($filas) => $filas->sortBy('Seq_in_index')->pluck('Column_name')->all() === $campos), 'Columnas de único: '.$tabla);
}
$relaciones = ['fases.campeonato_id' => 'campeonatos', 'grupos.fase_id' => 'fases', 'fase_equipos.fase_id' => 'fases', 'fase_equipos.campeonato_equipo_id' => 'campeonato_equipos', 'fase_equipos.grupo_id' => 'grupos',
    'rondas.fase_id' => 'fases', 'llaves.ronda_id' => 'rondas', 'llaves.ganador_fase_equipo_id' => 'fase_equipos', 'llave_plazas.llave_id' => 'llaves',
    'llave_plazas.fase_equipo_id' => 'fase_equipos', 'llave_plazas.origen_grupo_id' => 'grupos', 'llave_plazas.origen_llave_id' => 'llaves', 'partidos.fase_id' => 'fases', 'partidos.grupo_id' => 'grupos', 'partidos.llave_id' => 'llaves'];
$claves = collect(DB::select('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL', [$base]))->keyBy(fn ($c) => $c->TABLE_NAME.'.'.$c->COLUMN_NAME);
foreach ($relaciones as $campo => $tabla) {
    detalle42(isset($claves[$campo]) && $claves[$campo]->REFERENCED_TABLE_NAME === $tabla && $claves[$campo]->REFERENCED_COLUMN_NAME === 'id', 'FK exacta: '.$campo);
}
detalle42(collect(DB::select("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME='partidos' AND REFERENCED_TABLE_NAME IN ('fases','grupos','llaves')", [$base]))->every(fn ($r) => $r->DELETE_RULE === 'RESTRICT'), 'Retención de las tres FK de partidos');
foreach (['fases.nombre' => 80, 'fases.tipo' => 20, 'fases.estado' => 20, 'grupos.codigo' => 12, 'fase_equipos.estado' => 20, 'rondas.nombre' => 60, 'rondas.estado' => 20, 'llaves.estado' => 20, 'llaves.metodo_resolucion' => 25] as $campo => $longitud) {
    [$tabla,$nombre] = explode('.', $campo);
    detalle42((int) DB::selectOne('SELECT CHARACTER_MAXIMUM_LENGTH AS longitud FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [$base, $tabla, $nombre])->longitud === $longitud, 'Longitud: '.$campo);
}
foreach (['rondas.modalidad' => "enum('unico','ida_vuelta')", 'rondas.desempate' => "enum('penales','prorroga_penales')", 'llave_plazas.origen_tipo' => "enum('directo','grupo_puesto','ganador_llave','bye')"] as $campo => $tipo) {
    [$tabla,$nombre] = explode('.', $campo);
    detalle42(DB::selectOne('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [$base, $tabla, $nombre])->COLUMN_TYPE === $tipo, 'Valores enum: '.$campo);
}
detalle42(DB::table('grupos')->count() === 0 && DB::table('fases')->where('tipo', 'grupos')->count() === 0, 'Soporte de grupos sin activación funcional');
// Regresión del laboratorio: la caché debe usar un prefijo propio aunque provenga de una copia.
$entorno = json_decode(file_get_contents(getenv('BEESOCC_INTEGRAL_CONFIG')), true, flags: JSON_THROW_ON_ERROR);
detalle42(config('cache.prefix') === $entorno['CACHE_PREFIX'] && str_starts_with(config('cache.prefix'), 'beesocc_'), 'Prefijo de caché aislado');
DB::beginTransaction();
try {
    $antes = DB::table('cache')->orderBy('key')->get()->toJson();
    Cache::put('sprint42-regresion-prefijo', 'ficticio', 60);
    detalle42(DB::table('cache')->where('key', config('cache.prefix').'sprint42-regresion-prefijo')->exists(), 'Escritura con prefijo propio');
    detalle42(DB::table('cache')->where('key', '!=', config('cache.prefix').'sprint42-regresion-prefijo')->orderBy('key')->get()->toJson() === $antes, 'Caché copiada sin cambios adicionales');
} finally {
    DB::rollBack();
}
$prefijoEvidencia = getenv('BEESOCC_SPRINT_EVIDENCIA') === '43' ? 'sprint43' : 'sprint42';
file_put_contents(__DIR__.'/'.$prefijoEvidencia.'-detalle-esquema.json', json_encode(['ok' => true, 'base' => $base, 'comprobaciones' => count($pruebas), 'pruebas' => $pruebas], JSON_PRETTY_PRINT));
echo json_encode(['ok' => true, 'comprobaciones' => count($pruebas)]);
