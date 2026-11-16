<?php

namespace Tests\Feature;

use App\Models\AuditoriaCambio;
use App\Models\AutorizacionCorreccion;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\GrupoDesempate;
use App\Models\Partido;
use App\Models\User;
use App\Services\OrdenGruposService;

class GruposEliminacionTest extends BackendTestCase
{
    private function escenarioGrupos(int $cantidad = 4, int $grupos = 2, int $cupo = 1): array
    {
        $c = $this->postJson('/api/campeonatos', ['nombre' => 'Mixto 5.3', 'temporada' => '2026', 'formato' => 'grupos_eliminacion'])->assertCreated()->json('campeonato');
        $ids = $plantillas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $id = $this->inscripcion($c['id'], 'Ficticio '.$i)['id'];
            $ids[] = $id;
            $plantillas[$id] = $this->plantilla($id)['id'];
        }
        $f = $this->postJson('/api/campeonatos/'.$c['id'].'/fases', ['tipo' => 'grupos', 'nombre' => 'Grupos'])->assertCreated()->json('fase');
        $distribucion = array_fill(0, $grupos, []);
        foreach ($ids as $i => $id) {
            $distribucion[$i % $grupos][] = $id;
        }
        $v = $this->postJson('/api/fases/'.$f['id'].'/previsualizar-grupos', ['metodo' => 'manual', 'cantidad_grupos' => $grupos, 'clasificados_por_grupo' => $cupo, 'grupos' => $distribucion])->assertOk()->json();
        unset($v['fase_id']);
        $this->postJson('/api/fases/'.$f['id'].'/confirmar-grupos', $v)->assertCreated();
        $this->postJson('/api/fases/'.$f['id'].'/generar-calendario-grupos')->assertCreated();

        return ['campeonato' => $c['id'], 'fase' => $f['id'], 'ids' => $ids, 'plantillas' => $plantillas];
    }

    private function oficializar(array $e, Partido $p, int $gl = 1, int $gv = 0): array
    {
        $this->solicitudArbitral('patch', '/api/partidos/'.$p->id, ['estado' => 'en_curso'])->assertOk();
        $eventos = [];
        foreach (['local' => $gl, 'visitante' => $gv] as $rol => $cantidad) {
            $x = $p->participantes()->where('rol', $rol)->sole();
            for ($i = 0; $i < $cantidad; $i++) {
                $eventos[] = $this->solicitudArbitral('post', '/api/partidos/'.$p->id.'/eventos', ['tipo' => 'gol', 'minuto' => 10 + $i, 'equipo_partido_id' => $x->id, 'plantilla_id' => $e['plantillas'][$x->campeonato_equipo_id]])->assertCreated()->json('evento');
            }
        }
        $this->solicitudArbitral('post', '/api/partidos/'.$p->id.'/finalizar')->assertOk();

        return $eventos;
    }

    private function clasificar(array $e): array
    {
        $v = $this->getJson('/api/fases/'.$e['fase'].'/clasificacion-grupos')->assertOk()->json();

        return $this->postJson('/api/fases/'.$e['fase'].'/clasificar-grupos', ['confirmacion' => $v['confirmacion']])->assertOk()->json();
    }

    private function generarDesempate(Grupo $grupo): array
    {
        $pendiente = $this->getJson('/api/grupos/'.$grupo->id.'/tabla?vista=oficial')->assertOk()->json('pendientes_resolucion.0');
        $datos = ['clave' => $pendiente['clave']];
        $v = $this->postJson('/api/grupos/'.$grupo->id.'/previsualizar-desempate', $datos)->assertOk()->json();

        return $this->postJson('/api/grupos/'.$grupo->id.'/generar-desempate', $datos + ['confirmacion' => $v['confirmacion']])->assertCreated()->json('desempate');
    }

    private function vistaEliminacion(array $e, string $metodo = 'automatico'): array
    {
        $cupo = Fase::findOrFail($e['fase'])->clasificados_por_grupo;
        $n = Grupo::where('fase_id', $e['fase'])->count() * $cupo;
        $rondas = (int) ceil(log($n, 2));

        return $this->postJson('/api/fases/'.$e['fase'].'/previsualizar-eliminacion', ['metodo' => $metodo, 'rondas' => array_fill(0, $rondas, ['modalidad' => 'unico', 'desempate' => 'penales'])])->assertOk()->json();
    }

    private function envio(array $vista): array
    {
        return array_intersect_key($vista, array_flip(['metodo', 'rondas', 'sorteo', 'confirmacion_sorteo', 'cruces', 'confirmacion']));
    }

    private function generarEliminacion(array $e): array
    {
        $vista = $this->vistaEliminacion($e);

        return $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', $this->envio($vista))->assertCreated()->json('fase');
    }

    private function comoArbitro(array $e, callable $accion): mixed
    {
        $admin = auth('web')->user();
        $ref = Fase::findOrFail($e['fase'])->campeonato->asignacionesArbitro()->whereNull('fecha_fin')->sole()->usuario;
        app('auth')->forgetGuards();
        $this->actingAs($ref, 'web');
        try {
            return $accion();
        } finally {
            app('auth')->forgetGuards();
            $this->actingAs($admin, 'web');
        }
    }

    private function corregir(array $e, Partido $p, array $eventos, bool $conflicto = false): void
    {
        $c = $this->comoArbitro($e, fn () => $this->postJson('/api/partidos/'.$p->id.'/correcciones', ['motivo' => 'Corrección ficticia 5.3', 'alcance' => 'eventos'])->assertCreated()->json('correccion'));
        $this->patchJson('/api/correcciones/'.$c['id'].'/autorizar', ['estado' => 'aprobada'])->assertOk();
        $visitante = $p->participantes()->where('rol', 'visitante')->sole();
        $operaciones = [['accion' => 'eliminar', 'evento_id' => $eventos[0]['id']], ['accion' => 'registrar', 'datos' => ['tipo' => 'gol', 'minuto' => 20, 'equipo_partido_id' => $visitante->id, 'plantilla_id' => $e['plantillas'][$visitante->campeonato_equipo_id]]]];
        $r = $this->comoArbitro($e, fn () => $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => $operaciones]));
        if ($conflicto) {
            $r->assertConflict();
            $this->assertSame('aprobada', AutorizacionCorreccion::findOrFail($c['id'])->estado);
            $this->assertDatabaseHas('eventos_estadisticos', ['id' => $eventos[0]['id']]);
        } else {
            $r->assertOk();
        }
    }

    public function test_partido_extra_oficial_penales_y_avance_resuelven_posicion_sin_cambiar_tabla_regular(): void
    {
        $e = $this->escenarioGrupos();
        $p = Partido::firstOrFail();
        $this->oficializar($e, $p, 0, 0);
        $this->oficializar($e, Partido::whereKeyNot($p->id)->sole());
        $grupo = $p->grupo;
        $antes = $this->getJson('/api/grupos/'.$grupo->id.'/tabla?vista=oficial')->assertOk()->json();
        $caso = $this->generarDesempate($grupo);
        $extra = Partido::findOrFail($caso['partidos'][0]['id']);
        $this->assertSame('desempate', $extra->tipo_partido);
        $this->oficializar($e, $extra, 1, 1);
        $this->postJson('/api/llaves/'.$caso['llave_id'].'/avanzar')->assertConflict();
        $datos = ['penales_plaza_1' => 4, 'penales_plaza_2' => 3, 'motivo' => 'Tanda ficticia tras empate oficial'];
        $this->postJson('/api/llaves/'.$caso['llave_id'].'/penales', $datos)->assertForbidden();
        $this->comoArbitro($e, fn () => $this->postJson('/api/llaves/'.$caso['llave_id'].'/penales', $datos)->assertOk());
        $this->postJson('/api/llaves/'.$caso['llave_id'].'/avanzar')->assertOk();
        $despues = $this->getJson('/api/grupos/'.$grupo->id.'/tabla?vista=oficial')->assertOk()->json();
        $this->assertTrue($despues['definitiva']);
        foreach ($despues['tabla'] as $fila) {
            $this->assertSame(1, $fila['PJ']);
            $this->assertSame(1, $fila['PTS']);
            $this->assertSame(0, $fila['GF']);
        }
        $this->assertTrue($this->clasificar($e)['clasificacion_vigente']);
        $goleadores = $this->getJson('/api/campeonatos/'.$e['campeonato'].'/estadisticas/goleadores')->assertOk()->json();
        $this->assertSame(3, collect($goleadores['resultados'])->sum('total'));
        $this->getJson('/api/grupos/'.$grupo->id.'/desempates')->assertOk()->assertJsonCount(1);
    }

    public function test_liguilla_requiere_confirmacion_nueva_si_persiste_el_empate_y_no_genera_ciclos(): void
    {
        $e = $this->escenarioGrupos(3, 1, 2);
        foreach (Partido::all() as $p) {
            $this->oficializar($e, $p, 0, 0);
        }
        $grupo = Grupo::firstOrFail();
        $caso = $this->generarDesempate($grupo);
        $this->assertNull($caso['llave_id']);
        $this->assertCount(3, $caso['partidos']);
        foreach (Partido::where('grupo_desempate_id', $caso['id'])->get() as $p) {
            $this->oficializar($e, $p, 0, 0);
        }
        $tabla = $this->getJson('/api/grupos/'.$grupo->id.'/tabla?vista=oficial')->assertOk()->json();
        $this->assertTrue($tabla['pendientes_resolucion'][0]['generable']);
        $this->assertSame(1, GrupoDesempate::count());
        $this->assertSame(6, Partido::count());
        $this->generarDesempate($grupo);
        $this->assertSame(2, GrupoDesempate::count());
        $this->assertSame(9, Partido::count());
    }

    public function test_liguilla_resuelve_tres_puestos_sin_sumar_sus_goles_a_la_tabla_regular(): void
    {
        $e = $this->escenarioGrupos(3, 1, 2);
        foreach (Partido::all() as $p) {
            $this->oficializar($e, $p, 0, 0);
        }
        $caso = $this->generarDesempate(Grupo::firstOrFail());
        foreach (Partido::where('grupo_desempate_id', $caso['id'])->with('participantes')->get() as $p) {
            $ganaLocal = $p->participantes->firstWhere('rol', 'local')->campeonato_equipo_id < $p->participantes->firstWhere('rol', 'visitante')->campeonato_equipo_id;
            $this->oficializar($e, $p, $ganaLocal ? 1 : 0, $ganaLocal ? 0 : 1);
        }
        $r = $this->clasificar($e);
        $this->assertSame([1, 2, 3], array_column($r['tablas'][0]['tabla'], 'posicion'));
        $this->assertSame([2, 2, 2], array_column($r['tablas'][0]['tabla'], 'PJ'));
        $this->assertSame([0, 0, 0], array_column($r['tablas'][0]['tabla'], 'GF'));
        $this->assertCount(2, $r['candidatos']);
    }

    public function test_minitabla_reaplica_solo_al_subgrupo_y_no_altera_los_totales_originales(): void
    {
        $filas = [];
        foreach ([1, 2, 3, 4] as $id) {
            $filas[] = ['campeonato_equipo_id' => $id, 'PTS' => 99, 'DG' => 7, 'GF' => 9];
        }
        $partidos = [];
        // 1 vence 2; 2 vence 3; 3 vence 1; los tres vencen 4 con márgenes distintos.
        foreach ([[1, 2, 1, 0], [1, 3, 0, 1], [2, 3, 1, 0], [1, 4, 3, 0], [2, 4, 2, 0], [3, 4, 2, 0]] as [$a,$b,$gl,$gv]) {
            $partidos[] = ['local' => $a, 'visitante' => $b, 'goles_local' => $gl, 'goles_visitante' => $gv];
        }
        $bloques = app(OrdenGruposService::class)->directos($filas, $partidos);
        $this->assertSame([[1], [2], [3], [4]], array_map(fn ($b) => array_column($b, 'campeonato_equipo_id'), $bloques));
        $this->assertSame(99, $bloques[0][0]['PTS']);
    }

    public function test_conecta_solo_clasificados_con_procedencia_y_rechaza_doble_generacion(): void
    {
        $e = $this->escenarioGrupos();
        foreach (Partido::all() as $p) {
            $this->oficializar($e, $p);
        }
        $clasificacion = $this->clasificar($e);
        $v = $this->vistaEliminacion($e);
        $this->assertSame(1, Fase::count());
        $datos = $this->envio($v);
        $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', array_replace($datos, ['confirmacion' => str_repeat('0', 64)]))->assertConflict();
        $fase = $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', $datos)->assertCreated()->json('fase');
        $this->assertSame($e['fase'], $fase['fase_origen_id']);
        $this->assertCount(2, $fase['equipos']);
        $this->assertEqualsCanonicalizing(array_column($clasificacion['candidatos'], 'campeonato_equipo_id'), array_column($fase['equipos'], 'campeonato_equipo_id'));
        foreach ($fase['rondas'][0]['llaves'][0]['plazas'] as $plaza) {
            $this->assertSame('grupo_puesto', $plaza['origen_tipo']);
            $this->assertSame(1, $plaza['origen_puesto']);
        }
        $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', $datos)->assertConflict();
        $p = Partido::where('fase_id', $fase['id'])->sole();
        $this->oficializar($e, $p);
        $this->postJson('/api/llaves/'.$p->llave_id.'/avanzar')->assertOk();
        $this->assertSame('finalizada', Fase::findOrFail($fase['id'])->estado);
    }

    public function test_descansos_priorizan_puestos_y_sorteo_firmado_sin_comparar_grupos(): void
    {
        $e = $this->escenarioGrupos(6, 3, 2);
        foreach (Partido::all() as $p) {
            $this->oficializar($e, $p);
        }
        $this->clasificar($e);
        $v = $this->vistaEliminacion($e);
        $this->assertCount(2, $v['descansos']);
        foreach ($v['descansos'] as $origen) {
            $this->assertStringEndsWith(':1', $origen);
        }
        foreach ($v['cruces'] as $par) {
            if ($par[0] && $par[1]) {
                $this->assertNotSame(explode(':', $par[0])[0], explode(':', $par[1])[0]);
            }
        }
        $envio = $this->envio($v);
        $envio['sorteo'] = array_reverse($envio['sorteo']);
        $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', $envio)->assertConflict();
        $fase = $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', $this->envio($v))->assertCreated()->json('fase');
        $this->assertSame(2, collect($fase['rondas'][0]['llaves'])->where('metodo_resolucion', 'bye')->count());
        $this->assertSame(2, collect($fase['rondas'][0]['llaves'])->sum(fn ($l) => count($l['partidos'])));
    }

    public function test_manual_reordena_cruces_pero_no_permite_duplicados_ni_mismo_grupo_evitable(): void
    {
        $e = $this->escenarioGrupos(4, 2, 2);
        foreach (Partido::all() as $p) {
            $this->oficializar($e, $p);
        }
        $this->clasificar($e);
        $v = $this->vistaEliminacion($e, 'manual');
        $datos = $this->envio($v);
        unset($datos['confirmacion']);
        $datos['cruces'] = array_reverse($datos['cruces']);
        $manual = $this->postJson('/api/fases/'.$e['fase'].'/previsualizar-eliminacion', $datos)->assertOk()->json();
        $this->assertSame($datos['cruces'], $manual['cruces']);
        $g = Grupo::where('fase_id', $e['fase'])->pluck('id')->all();
        $datos['cruces'] = [[$g[0].':1', $g[0].':2'], [$g[1].':1', $g[1].':2']];
        $this->postJson('/api/fases/'.$e['fase'].'/previsualizar-eliminacion', $datos)->assertUnprocessable();
        $datos['cruces'] = [$v['cruces'][0], $v['cruces'][0]];
        $this->postJson('/api/fases/'.$e['fase'].'/previsualizar-eliminacion', $datos)->assertUnprocessable();
        $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', $this->envio($manual))->assertCreated();
    }

    public function test_correccion_actualiza_participantes_programados_conservando_identificadores(): void
    {
        $e = $this->escenarioGrupos();
        $p = Partido::firstOrFail();
        $goles = $this->oficializar($e, $p);
        $this->oficializar($e, Partido::whereKeyNot($p->id)->sole());
        $this->clasificar($e);
        $fase = $this->generarEliminacion($e);
        $final = Partido::where('fase_id', $fase['id'])->with('participantes')->sole();
        $ids = $final->participantes->modelKeys();
        $antes = $final->participantes->pluck('campeonato_equipo_id')->all();
        $this->corregir($e, $p, $goles);
        $final->refresh()->load('participantes');
        $this->assertEqualsCanonicalizing($ids, $final->participantes->modelKeys());
        $this->assertNotSame($antes, $final->participantes->pluck('campeonato_equipo_id')->all());
        $this->getJson('/api/fases/'.$e['fase'].'/clasificacion-grupos')->assertOk()->assertJsonPath('clasificacion_vigente', true);
        $this->assertSame(1, AuditoriaCambio::where('accion', 'actualizar_clasificacion_grupos')->count());
    }

    public function test_correccion_hacia_partido_iniciado_es_atomica_y_no_consume_aprobacion(): void
    {
        $e = $this->escenarioGrupos();
        $p = Partido::firstOrFail();
        $goles = $this->oficializar($e, $p);
        $this->oficializar($e, Partido::whereKeyNot($p->id)->sole());
        $this->clasificar($e);
        $fase = $this->generarEliminacion($e);
        $final = Partido::where('fase_id', $fase['id'])->sole();
        $this->solicitudArbitral('patch', '/api/partidos/'.$final->id, ['estado' => 'en_curso'])->assertOk();
        $antes = $final->participantes()->pluck('campeonato_equipo_id')->all();
        $this->corregir($e, $p, $goles, true);
        $this->assertSame($antes, $final->participantes()->pluck('campeonato_equipo_id')->all());
    }

    public function test_permisos_y_resultados_pendientes_bloquean_los_nuevos_endpoints(): void
    {
        $e = $this->escenarioGrupos();
        $grupo = Grupo::firstOrFail();
        $this->postJson('/api/grupos/'.$grupo->id.'/previsualizar-desempate', ['clave' => str_repeat('0', 64)])->assertConflict();
        $this->postJson('/api/fases/'.$e['fase'].'/previsualizar-eliminacion', ['metodo' => 'automatico', 'rondas' => [['modalidad' => 'unico', 'desempate' => 'penales']]])->assertConflict();
        $admin = auth('web')->user();
        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        $this->getJson('/api/grupos/'.$grupo->id.'/desempates')->assertForbidden();
        $this->postJson('/api/grupos/'.$grupo->id.'/generar-desempate', [])->assertForbidden();
        $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', [])->assertForbidden();
        app('auth')->forgetGuards();
        $this->actingAs($admin, 'web');
    }

    public function test_partido_extra_ganado_requiere_avance_admin_y_no_admite_duplicados(): void
    {
        $e = $this->escenarioGrupos();
        $p = Partido::firstOrFail();
        $this->oficializar($e, $p, 0, 0);
        $this->oficializar($e, Partido::whereKeyNot($p->id)->sole());
        $pendiente = $this->getJson('/api/grupos/'.$p->grupo_id.'/tabla?vista=oficial')->assertOk()->json('pendientes_resolucion.0');
        $v = $this->postJson('/api/grupos/'.$p->grupo_id.'/previsualizar-desempate', ['clave' => $pendiente['clave']])->assertOk()->json();
        $this->postJson('/api/grupos/'.$p->grupo_id.'/generar-desempate', ['clave' => $v['clave'], 'confirmacion' => str_repeat('0', 64)])->assertConflict();
        $caso = $this->generarDesempate($p->grupo);
        $this->postJson('/api/grupos/'.$p->grupo_id.'/generar-desempate', ['clave' => $v['clave'], 'confirmacion' => $v['confirmacion']])->assertConflict();
        $this->assertSame(1, GrupoDesempate::count());
        $this->oficializar($e, Partido::findOrFail($caso['partidos'][0]['id']), 2, 0);
        $this->getJson('/api/fases/'.$e['fase'].'/clasificacion-grupos')->assertOk()->assertJsonPath('lista_para_clasificar', false);
        $this->comoArbitro($e, fn () => $this->postJson('/api/llaves/'.$caso['llave_id'].'/avanzar')->assertForbidden());
        $this->postJson('/api/llaves/'.$caso['llave_id'].'/avanzar')->assertOk();
        $this->assertTrue($this->clasificar($e)['clasificacion_vigente']);
    }

    public function test_correccion_de_grupo_no_puede_invalidar_un_desempate_ya_iniciado(): void
    {
        $e = $this->escenarioGrupos();
        $p = Partido::firstOrFail();
        $this->oficializar($e, $p, 0, 0);
        $this->oficializar($e, Partido::whereKeyNot($p->id)->sole());
        $caso = $this->generarDesempate($p->grupo);
        $this->solicitudArbitral('patch', '/api/partidos/'.$caso['partidos'][0]['id'], ['estado' => 'en_curso'])->assertOk();
        $c = $this->comoArbitro($e, fn () => $this->postJson('/api/partidos/'.$p->id.'/correcciones', ['motivo' => 'Revisar gol omitido ficticio', 'alcance' => 'eventos'])->assertCreated()->json('correccion'));
        $this->patchJson('/api/correcciones/'.$c['id'].'/autorizar', ['estado' => 'aprobada'])->assertOk();
        $x = $p->participantes()->where('rol', 'local')->sole();
        $datos = ['operaciones' => [['accion' => 'registrar', 'datos' => ['tipo' => 'gol', 'minuto' => 12, 'equipo_partido_id' => $x->id, 'plantilla_id' => $e['plantillas'][$x->campeonato_equipo_id]]]]];
        $this->comoArbitro($e, fn () => $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', $datos)->assertConflict());
        $this->assertSame('aprobada', AutorizacionCorreccion::findOrFail($c['id'])->estado);
        $this->assertSame(0, $p->participantes()->withCount('eventos')->get()->sum('eventos_count'));
    }

    public function test_cruces_de_grupos_desiguales_no_comparan_puntuaciones_y_firma_caduca(): void
    {
        $e = $this->escenarioGrupos(7, 3, 1);
        $p = Partido::firstOrFail();
        $goles = [];
        foreach (Partido::with('participantes')->get() as $partido) {
            $local = $partido->participantes->firstWhere('rol', 'local')->campeonato_equipo_id;
            $visitante = $partido->participantes->firstWhere('rol', 'visitante')->campeonato_equipo_id;
            $ev = $this->oficializar($e, $partido, $local < $visitante ? 2 : 0, $local < $visitante ? 0 : 2);
            if ($partido->id === $p->id) {
                $goles = $ev;
            }
        }
        $this->clasificar($e);
        $v = $this->vistaEliminacion($e);
        $this->assertCount(3, $v['clasificados']);
        $this->assertCount(1, $v['descansos']);
        // Cambia el marcador, aunque no necesariamente el clasificado: la firma debe caducar.
        $c = $this->comoArbitro($e, fn () => $this->postJson('/api/partidos/'.$p->id.'/correcciones', ['motivo' => 'Eliminar gol duplicado ficticio', 'alcance' => 'eventos'])->assertCreated()->json('correccion'));
        $this->patchJson('/api/correcciones/'.$c['id'].'/autorizar', ['estado' => 'aprobada'])->assertOk();
        $this->comoArbitro($e, fn () => $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => [['accion' => 'eliminar', 'evento_id' => $goles[0]['id']]]])->assertOk());
        $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', $this->envio($v))->assertConflict();
        $this->clasificar($e);
        $fase = $this->generarEliminacion($e);
        $this->assertCount(3, $fase['equipos']);
    }

    public function test_un_solo_grupo_informa_cruce_inevitable_y_conserva_cupos(): void
    {
        $e = $this->escenarioGrupos(3, 1, 2);
        foreach (Partido::with('participantes')->get() as $p) {
            $local = $p->participantes->firstWhere('rol', 'local')->campeonato_equipo_id;
            $visitante = $p->participantes->firstWhere('rol', 'visitante')->campeonato_equipo_id;
            $this->oficializar($e, $p, $local < $visitante ? 1 : 0, $local < $visitante ? 0 : 1);
        }
        $this->clasificar($e);
        $v = $this->vistaEliminacion($e);
        $this->assertSame(1, $v['cruces_mismo_grupo_inevitables']);
        $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', $this->envio($v))->assertCreated();
    }

    public function test_correccion_de_clasificado_con_descanso_se_propaga_hasta_final_programada(): void
    {
        $e = $this->escenarioGrupos(6, 3, 1);
        $eventos = [];
        foreach (Partido::all() as $p) {
            $eventos[$p->grupo_id] = [$p, $this->oficializar($e, $p)];
        }
        $this->clasificar($e);
        $fase = $this->generarEliminacion($e);
        $f = Fase::findOrFail($fase['id']);
        $ronda = $f->rondas()->first();
        $bye = $ronda->llaves()->where('metodo_resolucion', 'bye')->sole();
        $grupoId = $bye->plazas()->where('origen_tipo', 'grupo_puesto')->sole()->origen_grupo_id;
        $otra = $ronda->llaves()->where('estado', 'pendiente')->sole();
        $this->oficializar($e, $otra->partidos()->sole());
        $this->postJson('/api/llaves/'.$otra->id.'/avanzar')->assertOk();
        $final = $f->rondas()->where('numero', 2)->sole()->llaves()->sole()->partidos()->sole();
        $ids = $final->participantes()->pluck('id')->all();
        $antes = $final->participantes()->pluck('campeonato_equipo_id')->all();
        [$origen,$goles] = $eventos[$grupoId];
        $this->corregir($e, $origen, $goles);
        $this->assertEqualsCanonicalizing($ids, $final->participantes()->pluck('id')->all());
        $this->assertNotEqualsCanonicalizing($antes, $final->participantes()->pluck('campeonato_equipo_id')->all());
    }

    public function test_correccion_intercambia_puestos_en_la_misma_llave_sin_borrar_participantes(): void
    {
        $e = $this->escenarioGrupos(2, 1, 2);
        $p = Partido::sole();
        $goles = $this->oficializar($e, $p);
        $this->clasificar($e);
        $fase = $this->generarEliminacion($e);
        $final = Partido::where('fase_id', $fase['id'])->sole();
        $antes = $final->participantes()->orderBy('rol')->get();
        $this->corregir($e, $p, $goles);
        $despues = $final->participantes()->orderBy('rol')->get();
        $this->assertEqualsCanonicalizing($antes->modelKeys(), $despues->modelKeys());
        $this->assertSame(array_reverse($antes->pluck('campeonato_equipo_id')->all()), $despues->pluck('campeonato_equipo_id')->all());
    }

    public function test_desempates_de_bloques_distintos_conservan_su_vigencia_al_resolver_uno(): void
    {
        $e = $this->escenarioGrupos(4, 1, 2);
        $primeros = array_slice($e['ids'], 0, 2);
        foreach (Partido::with('participantes')->get() as $p) {
            $local = in_array($p->participantes->firstWhere('rol', 'local')->campeonato_equipo_id, $primeros, true);
            $visitante = in_array($p->participantes->firstWhere('rol', 'visitante')->campeonato_equipo_id, $primeros, true);
            $this->oficializar($e, $p, $local && ! $visitante ? 1 : 0, $visitante && ! $local ? 1 : 0);
        }
        $grupo = Grupo::sole();
        $pendientes = $this->getJson('/api/grupos/'.$grupo->id.'/tabla?vista=oficial')->assertOk()->json('pendientes_resolucion');
        $this->assertCount(2, $pendientes);
        $casos = [];
        foreach ($pendientes as $pendiente) {
            $datos = ['clave' => $pendiente['clave']];
            $vista = $this->postJson('/api/grupos/'.$grupo->id.'/previsualizar-desempate', $datos)->assertOk()->json();
            $casos[] = $this->postJson('/api/grupos/'.$grupo->id.'/generar-desempate', $datos + ['confirmacion' => $vista['confirmacion']])->assertCreated()->json('desempate');
        }
        foreach ($casos as $caso) {
            $this->oficializar($e, Partido::findOrFail($caso['partidos'][0]['id']));
            $this->postJson('/api/llaves/'.$caso['llave_id'].'/avanzar')->assertOk();
        }
        $this->assertTrue($this->clasificar($e)['clasificacion_vigente']);
    }

    public function test_fallo_de_auditoria_revierte_fase_participantes_y_llaves_nuevas(): void
    {
        $e = $this->escenarioGrupos();
        foreach (Partido::all() as $p) {
            $this->oficializar($e, $p);
        }
        $this->clasificar($e);
        $v = $this->vistaEliminacion($e);
        AuditoriaCambio::creating(function ($a) {
            if ($a->accion === 'generar_eliminacion_grupos') {
                throw new \RuntimeException('Fallo de auditoría simulado');
            }
        });
        try {
            $this->postJson('/api/fases/'.$e['fase'].'/generar-eliminacion', $this->envio($v))->assertStatus(500);
        } finally {
            AuditoriaCambio::flushEventListeners();
        }
        $this->assertSame(1, Fase::count());
        $this->assertSame(2, Partido::count());
        $this->assertDatabaseCount('llaves', 0);
        $this->assertDatabaseCount('fase_equipos', 4);
    }
}
