<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
$carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
$resultados = [];
function verificarCopia(bool $valor, string $nombre): void
{
    global $resultados;
    if (! $valor) {
        throw new RuntimeException($nombre);
    }
    $resultados[] = $nombre;
}
function huellaTabla(string $tabla, array $columnas): array
{
    $filas = DB::table($tabla)->select($columnas)->get()->map(fn ($f) => json_encode($f, JSON_THROW_ON_ERROR))->all();
    sort($filas, SORT_STRING);

    return ['filas' => count($filas), 'sha256' => hash('sha256', implode("\n", $filas))];
}
$base = DB::selectOne('SELECT DATABASE() AS nombre')->nombre;
$configuracion = config('database.connections.mysql');
verificarCopia((int) DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->n === 0, 'Destino nuevo y vacío');
$permisos = DB::select('SHOW GRANTS');
foreach ($permisos as $permiso) {
    $texto = array_values((array) $permiso)[0];
    verificarCopia(! preg_match('/GRANT (?!USAGE).* ON \*\.\*/i', $texto) && ! str_contains($texto, 'WITH GRANT OPTION'), 'Sin privilegios globales ni delegación');
}
try {
    DB::select('SELECT id FROM beesocc_v2.users LIMIT 1');
    throw new RuntimeException('La cuenta de ejecución puede acceder a la principal.');
} catch (QueryException $e) {
    verificarCopia(in_array($e->errorInfo[1] ?? null, [1044, 1142], true), 'Acceso a la principal denegado');
}
$respaldo = 'C:/Users/admin/Documents/BACKUPS_BEESOCC/beesocc_arbitros_20261112_202542_7a7f92.sql';
$sql = file_get_contents($respaldo);
$shaRespaldo = hash('sha256', $sql);
verificarCopia(hash('sha256', $sql) === '5eaa69cbec6432296e86d0482368766ef5f9c507ec6ecda8dc04e53292b43347', 'Respaldo del 12 de noviembre identificado por SHA-256');
verificarCopia(! preg_match('/^\s*(USE\s|CREATE\s+DATABASE|DROP\s+DATABASE|GRANT\s)/im', $sql)
    && ! preg_match('/`beesocc_v2`\s*\./i', $sql), 'Restauración sin selección de otra base');
// El destino ya está vacío; no se ejecutan ni siquiera los DROP del respaldo.
$sql = preg_replace('/^DROP TABLE IF EXISTS `[^`]+`;\s*$/m', '', $sql);
$conexion = new mysqli($configuracion['host'], $configuracion['username'], $configuracion['password'], $base, (int) $configuracion['port']);
verificarCopia($conexion->query('SELECT DATABASE()')->fetch_row()[0] === $base, 'Identidad del restaurador confirmada');
$conexion->multi_query($sql);
do {
    if ($r = $conexion->store_result()) {
        $r->free();
    }
    if (! $conexion->more_results()) {
        break;
    }
    $conexion->next_result();
} while (true);
$conexion->close();
$tablasRespaldo = array_map(fn ($r) => $r->TABLE_NAME, DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'));
$originales = [];
foreach ($tablasRespaldo as $tabla) {
    $columnas = array_map(fn ($c) => $c->Field, DB::select('SHOW COLUMNS FROM `'.$tabla.'`'));
    $originales[$tabla] = ['columnas' => $columnas, 'huella' => huellaTabla($tabla, $columnas)];
}
$migracion = 'database/migrations/2026_11_14_235900_add_oficializacion_and_auditoria.php';
$anteriores = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => basename($p) !== basename($migracion)));
verificarCopia(Artisan::call('migrate', ['--path' => $anteriores, '--realpath' => true, '--force' => true]) === 0, 'Esquema previo 3.1 preparado sobre la copia');
foreach ($originales as $tabla => $datos) {
    if ($tabla !== 'migrations') {
        verificarCopia(huellaTabla($tabla, $datos['columnas']) === $datos['huella'], 'Datos del respaldo conservados: '.$tabla);
    }
}
$antes = [];
foreach (DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME') as $fila) {
    $tabla = $fila->TABLE_NAME;
    $columnas = DB::select('SHOW COLUMNS FROM `'.$tabla.'`');
    $nombres = array_map(fn ($c) => $c->Field, $columnas);
    $antes[$tabla] = ['columnas' => $columnas, 'nombres' => $nombres, 'huella' => huellaTabla($tabla, $nombres),
        'indices' => DB::select('SHOW INDEX FROM `'.$tabla.'`')];
}
$filasMigraciones = DB::table('migrations')->get()->all();
verificarCopia(Artisan::call('migrate', ['--path' => $migracion, '--force' => true]) === 0, 'Migración 3.1 aplicada únicamente en la copia');
foreach ($antes as $tabla => $datos) {
    if ($tabla !== 'migrations') {
        verificarCopia(huellaTabla($tabla, $datos['nombres']) === $datos['huella'], 'Filas previas a 3.1 intactas: '.$tabla);
    }
    $actuales = DB::select('SHOW COLUMNS FROM `'.$tabla.'`');
    foreach ($datos['columnas'] as $i => $columna) {
        verificarCopia((array) $actuales[$i] === (array) $columna, 'Columna antigua conservada: '.$tabla.'.'.$columna->Field);
    }
    $indicesActuales = DB::select('SHOW INDEX FROM `'.$tabla.'`');
    foreach ($datos['indices'] as $indice) {
        verificarCopia(collect($indicesActuales)->contains(fn ($i) => $i->Key_name === $indice->Key_name
            && $i->Seq_in_index === $indice->Seq_in_index && $i->Column_name === $indice->Column_name
            && $i->Non_unique === $indice->Non_unique && $i->Index_type === $indice->Index_type), 'Índice anterior conservado: '.$tabla.'.'.$indice->Key_name);
    }
}
foreach ($filasMigraciones as $fila) {
    verificarCopia((array) DB::table('migrations')->find($fila->id) === (array) $fila, 'Registro de migración anterior conservado');
}
verificarCopia(DB::table('migrations')->count() === count($filasMigraciones) + 1, 'Único registro de migración nuevo');
verificarCopia(DB::table('partidos')->whereNotNull('oficializado_at')->count() === 0, 'Sin oficialización retroactiva');
verificarCopia(DB::table('partidos')->where('tipo_resultado', '!=', 'deportivo')->count() === 0, 'Tipo deportivo compatible con los datos existentes');
$esperadas = [
    'auditoria_cambios' => ['id', 'campeonato_id', 'usuario_id', 'entidad_tipo', 'entidad_id', 'accion', 'motivo', 'anterior_json', 'posterior_json', 'fecha'],
    'autorizaciones_correccion' => ['id', 'partido_id', 'solicitado_por_id', 'autorizado_por_id', 'motivo', 'estado', 'alcance', 'fecha_resolucion', 'fecha_consumo'],
];
foreach ($esperadas as $tabla => $columnas) {
    verificarCopia(array_map(fn ($c) => $c->Field, DB::select('SHOW COLUMNS FROM `'.$tabla.'`')) === $columnas, 'Todas las columnas nuevas: '.$tabla);
    $indices = [];
    foreach (DB::select('SHOW INDEX FROM `'.$tabla.'`') as $i) {
        $indices[$i->Key_name][$i->Seq_in_index] = $i->Column_name;
    }
    verificarCopia(array_values($indices['PRIMARY']) === ['id'], 'Clave primaria: '.$tabla);
    if ($tabla === 'auditoria_cambios') {
        verificarCopia(collect($indices)->contains(fn ($i) => array_values($i) === ['campeonato_id', 'entidad_tipo', 'entidad_id']), 'Índice compuesto de búsqueda de auditoría');
    }
    foreach (array_filter($columnas, fn ($c) => str_ends_with($c, '_id') && $c !== 'entidad_id') as $columna) {
        verificarCopia(collect($indices)->contains(fn ($i) => array_values($i)[0] === $columna), 'Índice de FK: '.$tabla.'.'.$columna);
    }
}
$claves = DB::select("SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME IN ('auditoria_cambios','autorizaciones_correccion') ORDER BY k.TABLE_NAME,k.COLUMN_NAME");
$destinos = ['auditoria_cambios.campeonato_id' => 'campeonatos', 'auditoria_cambios.usuario_id' => 'users',
    'autorizaciones_correccion.partido_id' => 'partidos', 'autorizaciones_correccion.solicitado_por_id' => 'users', 'autorizaciones_correccion.autorizado_por_id' => 'users'];
verificarCopia(count($claves) === 5, 'Cinco claves foráneas nuevas');
foreach ($claves as $c) {
    verificarCopia($destinos[$c->TABLE_NAME.'.'.$c->COLUMN_NAME] === $c->REFERENCED_TABLE_NAME && $c->REFERENCED_COLUMN_NAME === 'id' && $c->DELETE_RULE === 'RESTRICT', 'Destino y RESTRICT de '.$c->COLUMN_NAME);
}
verificarCopia(Artisan::call('migrate', ['--path' => $migracion, '--force' => true]) === 0 && DB::table('migrations')->count() === count($filasMigraciones) + 1, 'Reejecutar migrate no duplica tablas ni registros');

// Los ensayos funcionales utilizan exclusivamente recursos ficticios nuevos.
$usuarios = [];
foreach (['admin', 'arbitro', 'ajeno'] as $rol) {
    $usuarios[$rol] = User::create(['name' => 'Ensayo 3.2 '.$rol, 'email' => 's32-'.$rol.'@example.test', 'password' => 'PruebaArbitral32!2026']);
}
$admin = $usuarios['admin'];
$liga = $admin->campeonatos()->create(['nombre' => 'Copa ficticia 3.2', 'temporada' => '2026', 'formato' => 'todos_contra_todos']);
$inscripciones = [];
$plantillas = [];
foreach (['Local 3.2', 'Visitante 3.2'] as $i => $nombre) {
    $equipo = $admin->equipos()->create(['nombre' => $nombre, 'ciudad' => 'Ciudad ficticia']);
    $inscripcion = $liga->inscripcionesEquipos()->create(['equipo_id' => $equipo->id, 'estado' => 'activo']);
    $jugador = $admin->jugadores()->create(['nombre' => 'Jugador '.$i, 'apellido' => 'Ficticio', 'fecha_nacimiento' => '2000-01-01']);
    $plantillas[] = $inscripcion->plantillas()->create(['jugador_id' => $jugador->id, 'dorsal' => 10, 'posicion' => 'Delantero', 'fecha_ingreso' => '2026-01-01'])->id;
    $inscripciones[] = $inscripcion->id;
}
$partido = $liga->partidos()->create(['jornada' => 1, 'estado' => 'programado', 'fecha_hora' => now()]);
$participantes = $partido->participantes()->createMany([
    ['campeonato_equipo_id' => $inscripciones[0], 'rol' => 'local'], ['campeonato_equipo_id' => $inscripciones[1], 'rol' => 'visitante'],
]);
$fixture = ['campeonato' => $liga->id, 'partido' => $partido->id, 'local' => $participantes[0]->id, 'visitante' => $participantes[1]->id,
    'plantilla_local' => $plantillas[0], 'plantilla_visitante' => $plantillas[1], 'arbitro_id' => $usuarios['arbitro']->id];
file_put_contents($carpeta.'/sprint32-fixture.json', json_encode($fixture));
$evidencia = ['base' => $base, 'respaldo' => basename($respaldo), 'sha256_respaldo' => $shaRespaldo,
    'tipo' => 'Copia de respaldo del 12/11, no copia del estado actual de producción', 'aprobadas' => count($resultados),
    'verificaciones' => $resultados, 'huellas_tablas_anteriores' => array_map(fn ($d) => $d['huella'], $antes)];
file_put_contents($carpeta.'/sprint32-copia.json', json_encode($evidencia, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['base' => $base, 'verificaciones_aprobadas' => count($resultados), 'tablas_preservadas' => count($antes), 'fixture' => $fixture], JSON_PRETTY_PRINT);
