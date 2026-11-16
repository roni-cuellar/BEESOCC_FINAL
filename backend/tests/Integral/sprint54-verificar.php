<?php

use App\Models\Fase;
use App\Models\User;
use App\Services\ClasificacionGruposService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
DB::statement('SET SESSION TRANSACTION READ ONLY');
DB::beginTransaction();
try {
    $carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
    $fixture = json_decode(file_get_contents($carpeta.'/sprint54-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
    Auth::guard('web')->setUser(User::findOrFail($fixture['usuarios']['admin']['id']));
    $columnas = DB::select("SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='fases' AND COLUMN_NAME IN ('fase_origen_id','clasificacion_huella')) OR TABLE_NAME='grupo_desempates' OR (TABLE_NAME='partidos' AND COLUMN_NAME='grupo_desempate_id'))");
    if (count($columnas) !== 9) {
        throw new RuntimeException('Faltan columnas de la migración 000200.');
    }
    $foraneas = DB::select("SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND ((k.TABLE_NAME='fases' AND k.COLUMN_NAME='fase_origen_id') OR k.TABLE_NAME='grupo_desempates' OR (k.TABLE_NAME='partidos' AND k.COLUMN_NAME='grupo_desempate_id'))");
    $esperadas = ['fases.fase_origen_id' => 'fases', 'grupo_desempates.grupo_id' => 'grupos', 'grupo_desempates.llave_id' => 'llaves', 'partidos.grupo_desempate_id' => 'grupo_desempates'];
    foreach ($foraneas as $fk) {
        $clave = $fk->TABLE_NAME.'.'.$fk->COLUMN_NAME;
        if (($esperadas[$clave] ?? null) !== $fk->REFERENCED_TABLE_NAME || $fk->REFERENCED_COLUMN_NAME !== 'id' || $fk->DELETE_RULE !== 'RESTRICT') {
            throw new RuntimeException('Clave foránea inesperada.');
        }
        unset($esperadas[$clave]);
    }
    if ($esperadas) {
        throw new RuntimeException('Faltan claves foráneas.');
    }
    $indices = DB::select("SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND NON_UNIQUE=0 AND TABLE_NAME IN ('fases','grupo_desempates') GROUP BY TABLE_NAME, INDEX_NAME");
    $unicos = array_map(fn ($i) => $i->TABLE_NAME.':'.$i->columnas, $indices);
    foreach (['fases:fase_origen_id', 'grupo_desempates:grupo_id,clave', 'grupo_desempates:llave_id'] as $indice) {
        if (! in_array($indice, $unicos, true)) {
            throw new RuntimeException('Falta un índice único.');
        }
    }
    $ddl = (array) DB::selectOne('SHOW CREATE TABLE grupo_desempates');
    if (! str_contains(strtolower(array_values($ddl)[1]), 'json_valid')) {
        throw new RuntimeException('No existe la validación JSON en MariaDB.');
    }
    if (DB::getSchemaBuilder()->hasTable('personal_access_tokens') || DB::table('migrations')->where('migration', 'like', '%personal_access_tokens%')->exists()) {
        throw new RuntimeException('Se ejecutó la migración excluida de tokens.');
    }
    $cuadros = 0;
    foreach (Fase::whereNotNull('fase_origen_id')->with('rondas.llaves.plazas.participante', 'rondas.llaves.partidos.participantes')->get() as $fase) {
        $oficial = app(ClasificacionGruposService::class)->preparar(Fase::findOrFail($fase->fase_origen_id));
        if (! $oficial['clasificacion_vigente']) {
            throw new RuntimeException('Cuadro con clasificación obsoleta.');
        }
        $porOrigen = [];
        foreach ($oficial['candidatos'] as $c) {
            $porOrigen[$c['origen_grupo_id'].':'.$c['origen_puesto']] = $c['campeonato_equipo_id'];
        }
        $usados = [];
        foreach ($fase->rondas->sortBy('numero')->first()->llaves as $llave) {
            foreach ($llave->plazas as $plaza) {
                if ($plaza->origen_tipo === 'bye') {
                    continue;
                }
                $origen = $plaza->origen_grupo_id.':'.$plaza->origen_puesto;
                if ($plaza->origen_tipo !== 'grupo_puesto' || ($porOrigen[$origen] ?? null) !== $plaza->participante->campeonato_equipo_id || isset($usados[$origen])) {
                    throw new RuntimeException('Procedencia duplicada o clasificado inconsistente.');
                }
                $usados[$origen] = true;
            }
        }
        if (count($usados) !== count($porOrigen)) {
            throw new RuntimeException('Cuadro incompleto.');
        }
        foreach ($fase->rondas as $ronda) {
            foreach ($ronda->llaves as $llave) {
                foreach ($llave->partidos as $p) {
                    $ids = $p->participantes->pluck('campeonato_equipo_id')->all();
                    if ($p->fase_id !== $fase->id || count($ids) !== 2 || count(array_unique($ids)) !== 2) {
                        throw new RuntimeException('Partido parcial o duplicado.');
                    }
                }
            }
        }
        $cuadros++;
    }
    $carreras = json_decode(file_get_contents($carpeta.'/sprint54-concurrencia.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! $carreras['ok'] || count($carreras['carreras']) !== 4) {
        throw new RuntimeException('Falta acreditar las cuatro carreras.');
    }
    foreach ($carreras['carreras'] as $c) {
        if (! $c['ok'] || $c['solicitudes_http_en_lock_wait'] !== 2 || count(array_unique($c['conexiones'])) !== 2) {
            throw new RuntimeException('Barrera incompleta.');
        }
    }
    $casos = json_decode(file_get_contents($carpeta.'/sprint54-casos.json'), true, flags: JSON_THROW_ON_ERROR);
    $rechazado = $casos['correccion']['partidos'][1];
    $evento = $casos['correccion']['eventos'][$rechazado['id']][0];
    $actual = DB::table('eventos_estadisticos')->find($evento['id']);
    foreach (['equipo_partido_id', 'plantilla_id', 'tipo', 'minuto'] as $campo) {
        if (! $actual || (string) $actual->{$campo} !== (string) $evento[$campo]) {
            throw new RuntimeException('La corrección rechazada dejó cambios parciales en el evento.');
        }
    }
    $autorizacion = DB::table('autorizaciones_correccion')->where('partido_id', $rechazado['id'])->sole();
    if ($autorizacion->estado !== 'aprobada' || $autorizacion->fecha_consumo !== null) {
        throw new RuntimeException('Se consumió la autorización rechazada.');
    }
    if (DB::table('grupo_desempates')->count() !== DB::table('auditoria_cambios')->where('accion', 'generar_desempate_grupo')->count()
        || $cuadros !== DB::table('auditoria_cambios')->where('accion', 'generar_eliminacion_grupos')->count()) {
        throw new RuntimeException('Hay generaciones sin auditoría o auditorías duplicadas.');
    }
    $resultado = ['ok' => true, 'columnas_000200' => count($columnas), 'foraneas_restrict' => count($foraneas), 'indices_unicos_verificados' => 3, 'json_valid' => true, 'tokens_omitidos' => true, 'cuadros_verificados' => $cuadros, 'carreras_acreditadas' => 4, 'rollback_eventos_verificado' => true, 'autorizacion_no_consumida' => true, 'auditorias_integras' => true];
    DB::commit();
    file_put_contents($carpeta.'/sprint54-estructura.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($resultado).PHP_EOL;
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}
