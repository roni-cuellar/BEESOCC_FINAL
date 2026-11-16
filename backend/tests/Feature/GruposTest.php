<?php

namespace Tests\Feature;

use App\Models\AuditoriaCambio;
use App\Models\AutorizacionCorreccion;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\User;

class GruposTest extends BackendTestCase
{
    private function preparar(int $cantidad = 6, int $grupos = 2, int $cupo = 1, bool $confirmar = true): array
    {
        $campeonato = $this->postJson('/api/campeonatos', ['nombre' => 'Grupos de prueba', 'temporada' => '2026', 'formato' => 'grupos_eliminacion'])
            ->assertCreated()->json('campeonato');
        $ids = $plantillas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $inscripcion = $this->inscripcion($campeonato['id'], 'Equipo '.($i + 1));
            $ids[] = $inscripcion['id'];
            $plantillas[$inscripcion['id']] = $this->plantilla($inscripcion['id'])['id'];
        }
        $fase = $this->postJson('/api/campeonatos/'.$campeonato['id'].'/fases', ['nombre' => 'Primera fase', 'tipo' => 'grupos'])->assertCreated()->json('fase');
        $distribucion = array_fill(0, $grupos, []);
        foreach ($ids as $i => $id) {
            $distribucion[$i % $grupos][] = $id;
        }
        $datos = ['metodo' => 'manual', 'cantidad_grupos' => $grupos, 'clasificados_por_grupo' => $cupo, 'grupos' => $distribucion];
        $e = compact('campeonato', 'fase', 'ids', 'plantillas', 'datos');
        if ($confirmar) {
            $this->confirmar($e);
        }

        return $e;
    }

    private function confirmar(array &$e): void
    {
        $e['vista'] = $this->postJson('/api/fases/'.$e['fase']['id'].'/previsualizar-grupos', $e['datos'])->assertOk()->json();
        $e['envio'] = array_intersect_key($e['vista'], array_flip(['metodo', 'cantidad_grupos', 'clasificados_por_grupo', 'grupos', 'confirmacion']));
        $this->postJson('/api/fases/'.$e['fase']['id'].'/confirmar-grupos', $e['envio'])->assertCreated();
    }

    private function calendario(array $e): array
    {
        return $this->postJson('/api/fases/'.$e['fase']['id'].'/generar-calendario-grupos')->assertCreated()->json();
    }

    private function oficializar(array $e, Partido $partido, int $local = 1, int $visitante = 0): array
    {
        $this->solicitudArbitral('patch', '/api/partidos/'.$partido->id, ['estado' => 'en_curso'])->assertOk();
        $datos = $this->getJson('/api/partidos/'.$partido->id)->assertOk()->json();
        $eventos = [];
        foreach (['local' => $local, 'visitante' => $visitante] as $rol => $cantidad) {
            $participante = $partido->participantes()->where('rol', $rol)->firstOrFail();
            for ($i = 0; $i < $cantidad; $i++) {
                $eventos[] = $this->evento($datos, $participante->id, $e['plantillas'][$participante->campeonato_equipo_id]);
            }
        }
        $this->solicitudArbitral('post', '/api/partidos/'.$partido->id.'/finalizar')->assertOk();

        return $eventos;
    }

    private function clasificar(array $e): array
    {
        $vista = $this->getJson('/api/fases/'.$e['fase']['id'].'/clasificacion-grupos')->assertOk()->json();

        return $this->postJson('/api/fases/'.$e['fase']['id'].'/clasificar-grupos', ['confirmacion' => $vista['confirmacion']])->assertOk()->json();
    }

    private function entrar(User $usuario): void
    {
        app('auth')->forgetGuards();
        $this->actingAs($usuario, 'web');
    }

    private function aprobarCorreccion(Partido $partido): array
    {
        $admin = auth('web')->user();
        $arbitro = $partido->campeonato->asignacionesArbitro()->whereNull('fecha_fin')->firstOrFail()->usuario;
        $this->entrar($arbitro);
        $c = $this->postJson('/api/partidos/'.$partido->id.'/correcciones', ['motivo' => 'Revisar un resultado de grupos', 'alcance' => 'eventos'])->assertCreated()->json('correccion');
        $this->entrar($admin);
        $this->patchJson('/api/correcciones/'.$c['id'].'/autorizar', ['estado' => 'aprobada'])->assertOk();
        $this->entrar($arbitro);

        return $c;
    }

    public function test_crea_fase_de_grupos_sin_cambiar_los_formatos_anteriores(): void
    {
        $e = $this->preparar(confirmar: false);
        $this->assertDatabaseCount('grupos', 0);
        $this->assertDatabaseCount('partidos', 0);
        $this->assertSame('grupos', $e['fase']['tipo']);
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/fases', ['nombre' => 'Repetida', 'tipo' => 'grupos'])->assertConflict();
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/fases', ['nombre' => 'Sin cruces aprobados', 'tipo' => 'eliminacion'])->assertUnprocessable();
        $liga = $this->campeonato();
        $this->postJson('/api/campeonatos/'.$liga['id'].'/fases', ['nombre' => 'Ajena', 'tipo' => 'grupos'])->assertUnprocessable();
    }

    public function test_distribucion_manual_conserva_cupos_uniformes_y_relaciones(): void
    {
        $e = $this->preparar();
        $this->assertDatabaseCount('grupos', 2);
        $this->assertDatabaseCount('fase_equipos', 6);
        $this->assertSame(1, (int) Fase::findOrFail($e['fase']['id'])->clasificados_por_grupo);
        foreach (Grupo::all() as $i => $grupo) {
            $this->assertSame($e['datos']['grupos'][$i], $grupo->equipos()->pluck('campeonato_equipo_id')->all());
            $this->assertSame('G'.($i + 1), $grupo->codigo);
        }
        $this->getJson('/api/fases/'.$e['fase']['id'])->assertOk()->assertJsonCount(2, 'grupos');
        $this->getJson('/api/fases/'.$e['fase']['id'].'/auditoria')->assertOk()->assertJsonFragment(['accion' => 'confirmar_grupos']);
    }

    public function test_automatico_equilibra_siete_equipos_en_tres_grupos_sin_repetir_sorteo(): void
    {
        $e = $this->preparar(7, 3, confirmar: false);
        $e['datos']['metodo'] = 'automatico';
        unset($e['datos']['grupos']);
        $this->confirmar($e);
        $this->assertSame([3, 2, 2], array_map('count', $e['vista']['grupos']));
        $todos = array_merge(...$e['vista']['grupos']);
        sort($todos);
        $this->assertSame($e['ids'], $todos);
        foreach (Grupo::all() as $i => $grupo) {
            $this->assertSame($e['vista']['grupos'][$i], $grupo->equipos()->pluck('campeonato_equipo_id')->all());
        }
    }

    public function test_vista_previa_no_escribe_grupos_ni_partidos(): void
    {
        $e = $this->preparar(confirmar: false);
        $antes = AuditoriaCambio::count();
        $this->postJson('/api/fases/'.$e['fase']['id'].'/previsualizar-grupos', $e['datos'])->assertOk()->assertJsonStructure(['confirmacion', 'grupos']);
        $this->assertDatabaseCount('grupos', 0);
        $this->assertDatabaseCount('fase_equipos', 0);
        $this->assertDatabaseCount('partidos', 0);
        $this->assertSame($antes, AuditoriaCambio::count());
    }

    public function test_rechaza_duplicados_omisiones_ids_ajenos_y_grupos_vacios(): void
    {
        $e = $this->preparar(4, confirmar: false);
        $otro = $this->campeonato('Otro');
        $ajena = $this->inscripcion($otro['id'], 'Ajeno');
        foreach ([[$e['ids'][0], $e['ids'][0]], array_slice($e['ids'], 0, 3), [$e['ids'][0], $e['ids'][1], $e['ids'][2], $ajena['id']]] as $ids) {
            $this->postJson('/api/fases/'.$e['fase']['id'].'/previsualizar-grupos', array_replace($e['datos'], ['grupos' => [array_slice($ids, 0, 2), array_slice($ids, 2)]]))->assertUnprocessable();
        }
        $this->postJson('/api/fases/'.$e['fase']['id'].'/previsualizar-grupos', array_replace($e['datos'], ['grupos' => [$e['ids'], []]]))->assertUnprocessable();
        $this->assertDatabaseCount('grupos', 0);
    }

    public function test_no_acepta_inscritos_retirados_o_configuracion_inviable(): void
    {
        $e = $this->preparar(4, confirmar: false);
        foreach ([['cantidad_grupos' => 5], ['clasificados_por_grupo' => 3], ['cantidad_grupos' => 0], ['clasificados_por_grupo' => 256]] as $cambio) {
            $this->postJson('/api/fases/'.$e['fase']['id'].'/previsualizar-grupos', array_replace($e['datos'], $cambio))->assertUnprocessable();
        }
        $this->patchJson('/api/inscripciones-equipos/'.$e['ids'][0], ['estado' => 'retirado'])->assertOk();
        $this->postJson('/api/fases/'.$e['fase']['id'].'/previsualizar-grupos', $e['datos'])->assertUnprocessable();
    }

    public function test_confirmacion_alterada_y_doble_generacion_no_escriben(): void
    {
        $e = $this->preparar(4, confirmar: false);
        $v = $this->postJson('/api/fases/'.$e['fase']['id'].'/previsualizar-grupos', $e['datos'])->assertOk()->json();
        $this->postJson('/api/fases/'.$e['fase']['id'].'/confirmar-grupos', $e['datos'] + ['confirmacion' => str_repeat('0', 64)])->assertConflict();
        $this->assertDatabaseCount('grupos', 0);
        $this->confirmar($e);
        $this->postJson('/api/fases/'.$e['fase']['id'].'/confirmar-grupos', $e['envio'])->assertConflict();
        $this->assertDatabaseCount('grupos', 2);
        $this->assertSame(1, AuditoriaCambio::where('accion', 'confirmar_grupos')->count());
        $this->assertNotEmpty($v['confirmacion']);
    }

    public function test_vista_previa_caduca_si_cambian_los_inscritos(): void
    {
        $e = $this->preparar(4, confirmar: false);
        $v = $this->postJson('/api/fases/'.$e['fase']['id'].'/previsualizar-grupos', $e['datos'])->assertOk()->json();
        $this->inscripcion($e['campeonato']['id'], 'Nuevo');
        $this->postJson('/api/fases/'.$e['fase']['id'].'/confirmar-grupos', $e['datos'] + ['confirmacion' => $v['confirmacion']])->assertUnprocessable();
        $this->assertDatabaseCount('grupos', 0);
    }

    public function test_cantidad_de_grupos_no_esta_limitada_a_dos_o_cuatro(): void
    {
        $e = $this->preparar(5, 5);
        $r = $this->calendario($e);
        $this->assertSame(0, $r['partidos_creados']);
        $this->assertDatabaseCount('grupos', 5);
        $this->postJson('/api/fases/'.$e['fase']['id'].'/generar-calendario-grupos')->assertConflict();
        $this->assertCount(5, $this->clasificar($e)['candidatos']);
    }

    public function test_calendario_una_vuelta_en_grupos_desiguales_sin_cruces_externos(): void
    {
        $e = $this->preparar(7, 3);
        $r = $this->calendario($e);
        $this->assertSame(5, $r['partidos_creados']);
        $this->assertSame(1, $r['vueltas']);
        $this->assertCount(3, $r['descansos']);
        $pares = $jornadas = [];
        foreach (Partido::with('participantes')->get() as $partido) {
            $ids = $partido->participantes->pluck('campeonato_equipo_id')->all();
            $this->assertCount(2, $ids);
            $this->assertSame(2, $partido->grupo->equipos()->whereIn('campeonato_equipo_id', $ids)->count());
            sort($ids);
            $pares[] = implode(':', $ids);
            foreach ($ids as $id) {
                $clave = $partido->jornada.':'.$id;
                $this->assertArrayNotHasKey($clave, $jornadas);
                $jornadas[$clave] = true;
            }
        }
        $this->assertCount(5, array_unique($pares));
        $this->postJson('/api/fases/'.$e['fase']['id'].'/generar-calendario-grupos')->assertConflict();
    }

    public function test_manual_permite_tamanos_distintos_y_no_admite_reasignar_partidos(): void
    {
        $e = $this->preparar(6, confirmar: false);
        $e['datos']['grupos'] = [array_slice($e['ids'], 0, 4), array_slice($e['ids'], 4)];
        $this->confirmar($e);
        $this->assertSame(7, $this->calendario($e)['partidos_creados']);
        $p = Partido::firstOrFail();
        $this->patchJson('/api/partidos/'.$p->id, ['jornada' => 99])->assertConflict();
        $this->patchJson('/api/partidos/'.$p->id, ['local_id' => $e['ids'][4], 'visitante_id' => $e['ids'][5]])->assertConflict();
        $this->deleteJson('/api/partidos/'.$p->id)->assertConflict();
        $this->patchJson('/api/partidos/'.$p->id, ['lugar' => 'Cancha nueva'])->assertOk();
        $this->assertSame(1, $p->fresh()->jornada);
    }

    public function test_distribucion_congelada_no_admite_nuevos_inscritos_ni_cambio_formato(): void
    {
        $e = $this->preparar(4);
        $equipo = $this->equipo('Tardío');
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/inscripciones-equipos', ['equipo_id' => $equipo['id']])->assertConflict();
        $this->patchJson('/api/campeonatos/'.$e['campeonato']['id'], ['formato' => 'todos_contra_todos'])->assertConflict();
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/generar-calendario')->assertUnprocessable();
        $this->assertDatabaseCount('partidos', 0);
    }

    public function test_tabla_calcula_3_1_0_y_metricas_sin_mezclar_grupos(): void
    {
        $e = $this->preparar(4);
        $this->calendario($e);
        $partidos = Partido::all();
        $this->oficializar($e, $partidos[0], 2, 1);
        $this->oficializar($e, $partidos[1], 0, 0);
        $t = $this->getJson('/api/grupos/'.$partidos[0]->grupo_id.'/tabla?vista=oficial')->assertOk()->assertJsonPath('definitiva', true)->json('tabla');
        $this->assertSame([3, 0], array_column($t, 'PTS'));
        $this->assertSame([2, 1], array_column($t, 'GF'));
        $this->assertSame([1, 2], array_column($t, 'GC'));
        $this->assertSame([1, -1], array_column($t, 'DG'));
        $this->assertSame(1, $t[0]['PG']);
        $this->assertSame(1, $t[1]['PP']);
        $t2 = $this->getJson('/api/grupos/'.$partidos[1]->grupo_id.'/tabla?vista=oficial')->assertOk()->assertJsonPath('definitiva', false)->json('tabla');
        $this->assertSame([1, 1], array_column($t2, 'PTS'));
        $this->assertSame([1, 1], array_column($t2, 'PE'));
        $this->assertSame([null, null], array_column($t2, 'posicion'));
    }

    public function test_finalizado_sin_oficializar_es_provisional_y_no_clasifica(): void
    {
        $e = $this->preparar(4);
        $this->calendario($e);
        $p = Partido::firstOrFail();
        $p->update(['estado' => 'finalizado']);
        $this->getJson('/api/grupos/'.$p->grupo_id.'/tabla')->assertOk()->assertJsonPath('vista', 'provisional')->assertJsonPath('partidos_computados', 1)->assertJsonPath('definitiva', false);
        $this->getJson('/api/grupos/'.$p->grupo_id.'/tabla?vista=oficial')->assertOk()->assertJsonPath('partidos_computados', 0)->assertJsonPath('partidos_oficiales', 0);
        $this->postJson('/api/fases/'.$e['fase']['id'].'/clasificar-grupos', ['confirmacion' => str_repeat('0', 64)])->assertConflict();
    }

    public function test_en_curso_no_se_contabiliza_como_resultado_final(): void
    {
        $e = $this->preparar(4);
        $this->calendario($e);
        $p = Partido::firstOrFail();
        $this->solicitudArbitral('patch', '/api/partidos/'.$p->id, ['estado' => 'en_curso'])->assertOk();
        $this->getJson('/api/grupos/'.$p->grupo_id.'/tabla')->assertOk()->assertJsonPath('partidos_computados', 0)->assertJsonPath('definitiva', false);
        $this->assertSame('en_curso', $p->fase->fresh()->estado);
    }

    public function test_empate_absoluto_requiere_partido_extra_sin_asignar_posicion(): void
    {
        $e = $this->preparar(4);
        $this->calendario($e);
        foreach (Partido::all() as $p) {
            $this->oficializar($e, $p, 0, 0);
        }
        $this->getJson('/api/grupos/'.Grupo::firstOrFail()->id.'/tabla?vista=oficial')->assertOk()
            ->assertJsonPath('pendientes_resolucion.0.motivo', 'partido_extra_pendiente')->assertJsonPath('tabla.0.posicion', null);
        $this->getJson('/api/fases/'.$e['fase']['id'].'/clasificacion-grupos')->assertOk()->assertJsonPath('lista_para_clasificar', false)->assertJsonPath('candidatos', []);
        $this->assertDatabaseCount('partidos', 2);
    }

    public function test_empate_multiple_absoluto_requiere_liguilla_adicional(): void
    {
        $e = $this->preparar(3, 1, 2);
        $this->calendario($e);
        foreach (Partido::all() as $p) {
            $this->oficializar($e, $p, 0, 0);
        }
        $this->getJson('/api/grupos/'.Grupo::firstOrFail()->id.'/tabla?vista=oficial')->assertOk()
            ->assertJsonPath('pendientes_resolucion.0.motivo', 'liguilla_adicional_pendiente')->assertJsonPath('tabla.0.posicion', null);
    }

    public function test_enfrentamiento_directo_resuelve_solo_dos_empatados(): void
    {
        $e = $this->preparar(4, 1, 2);
        $this->calendario($e);
        // A y B igualan puntos, DG y GF; A ganó su enfrentamiento directo.
        $marcadores = ['0:1' => [1, 0], '0:2' => [0, 1], '0:3' => [1, 0], '1:2' => [1, 0], '1:3' => [1, 0], '2:3' => [0, 0]];
        foreach (Partido::with('participantes')->get() as $p) {
            $a = array_search($p->participantes->firstWhere('rol', 'local')->campeonato_equipo_id, $e['ids']);
            $b = array_search($p->participantes->firstWhere('rol', 'visitante')->campeonato_equipo_id, $e['ids']);
            $goles = $marcadores[min($a, $b).':'.max($a, $b)];
            $this->oficializar($e, $p, ...($a < $b ? $goles : array_reverse($goles)));
        }
        $t = $this->getJson('/api/grupos/'.Grupo::firstOrFail()->id.'/tabla?vista=oficial')->assertOk()->assertJsonPath('definitiva', true)->json('tabla');
        $this->assertSame($e['ids'], array_column($t, 'campeonato_equipo_id'));
        $this->assertSame([6, 6, 4, 1], array_column($t, 'PTS'));
        $this->assertSame([1, 2, 3, 4], array_column($t, 'posicion'));
    }

    public function test_clasificacion_uniforme_auditada_no_crea_cruces_sin_reglas(): void
    {
        $e = $this->preparar(4);
        $this->calendario($e);
        foreach (Partido::all() as $p) {
            $this->oficializar($e, $p);
        }
        $r = $this->clasificar($e);
        $this->assertTrue($r['clasificacion_vigente']);
        $this->assertCount(2, $r['candidatos']);
        foreach ($r['candidatos'] as $c) {
            $this->assertSame('grupo_puesto', $c['origen_tipo']);
            $this->assertSame(1, $c['origen_puesto']);
            $this->assertDatabaseHas('fase_equipos', ['id' => $c['fase_equipo_id'], 'grupo_id' => $c['origen_grupo_id'], 'campeonato_equipo_id' => $c['campeonato_equipo_id']]);
        }
        $this->assertTrue($r['transicion_eliminacion']['habilitada']);
        $this->assertDatabaseCount('rondas', 0);
        $this->assertDatabaseCount('llaves', 0);
        $this->postJson('/api/fases/'.$e['fase']['id'].'/clasificar-grupos', ['confirmacion' => $r['confirmacion']])->assertConflict();
        $this->assertSame(1, AuditoriaCambio::where('accion', 'clasificar_grupos')->count());
    }

    public function test_calendario_y_clasificacion_bloquean_elegibilidad_invalida(): void
    {
        $e = $this->preparar(4);
        $this->patchJson('/api/inscripciones-equipos/'.$e['ids'][0], ['estado' => 'retirado'])->assertOk();
        $this->postJson('/api/fases/'.$e['fase']['id'].'/generar-calendario-grupos')->assertConflict();
        $this->getJson('/api/fases/'.$e['fase']['id'].'/clasificacion-grupos')->assertOk()->assertJsonPath('lista_para_clasificar', false);
        $this->assertDatabaseCount('partidos', 0);
    }

    public function test_no_clasifica_con_calendario_incompleto_o_cancelado(): void
    {
        $e = $this->preparar(4);
        $this->postJson('/api/fases/'.$e['fase']['id'].'/clasificar-grupos', ['confirmacion' => str_repeat('0', 64)])->assertConflict();
        $this->calendario($e);
        $p = Partido::firstOrFail();
        $this->patchJson('/api/partidos/'.$p->id, ['estado' => 'cancelado'])->assertOk();
        $this->getJson('/api/fases/'.$e['fase']['id'].'/clasificacion-grupos')->assertOk()->assertJsonPath('lista_para_clasificar', false);
    }

    public function test_permisos_de_ajeno_y_anonimo_no_exponen_grupos(): void
    {
        $e = $this->preparar(4);
        $grupo = Grupo::firstOrFail();
        $this->entrar(User::factory()->create());
        $this->getJson('/api/grupos/'.$grupo->id.'/tabla')->assertForbidden();
        $this->getJson('/api/fases/'.$e['fase']['id'].'/clasificacion-grupos')->assertForbidden();
        foreach (['previsualizar-grupos', 'confirmar-grupos', 'generar-calendario-grupos', 'clasificar-grupos'] as $accion) {
            $this->postJson('/api/fases/'.$e['fase']['id'].'/'.$accion, [])->assertForbidden();
        }
        auth('web')->logout();
        app('auth')->forgetGuards();
        $this->getJson('/api/grupos/'.$grupo->id.'/tabla')->assertUnauthorized();
    }

    public function test_arbitro_solo_consulta_y_su_revocacion_rechaza_lectura(): void
    {
        $e = $this->preparar(4);
        $admin = auth('web')->user();
        $arbitro = User::factory()->create();
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/arbitro', ['usuario_id' => $arbitro->id])->assertCreated();
        $this->entrar($arbitro);
        $this->getJson('/api/grupos/'.Grupo::firstOrFail()->id.'/tabla')->assertOk();
        $this->getJson('/api/fases/'.$e['fase']['id'].'/auditoria')->assertOk();
        $this->postJson('/api/fases/'.$e['fase']['id'].'/generar-calendario-grupos')->assertForbidden();
        $this->postJson('/api/fases/'.$e['fase']['id'].'/clasificar-grupos', [])->assertForbidden();
        $this->entrar($admin);
        $this->deleteJson('/api/campeonatos/'.$e['campeonato']['id'].'/arbitro', ['motivo_revocacion' => 'Fin del ensayo'])->assertOk();
        $this->entrar($arbitro);
        $this->getJson('/api/grupos/'.Grupo::firstOrFail()->id.'/tabla')->assertForbidden();
    }

    public function test_correspondencia_de_grupo_e_inscripciones_se_comprueba_al_leer(): void
    {
        $e = $this->preparar(4);
        $this->calendario($e);
        $p = Partido::firstOrFail();
        $p->participantes()->where('rol', 'visitante')->update(['campeonato_equipo_id' => $e['ids'][1]]);
        $this->getJson('/api/grupos/'.$p->grupo_id.'/tabla')->assertConflict();
    }

    public function test_correccion_invalida_clasificacion_y_permite_reclasificar_sin_duplicar(): void
    {
        $e = $this->preparar(4);
        $this->calendario($e);
        $p = Partido::firstOrFail();
        $eventos = $this->oficializar($e, $p);
        $this->oficializar($e, Partido::whereKeyNot($p->id)->firstOrFail());
        $antes = $this->clasificar($e);
        $admin = auth('web')->user();
        $fecha = $p->fresh()->oficializado_at->toISOString();
        $c = $this->aprobarCorreccion($p);
        $visitante = $p->participantes()->where('rol', 'visitante')->firstOrFail();
        $operaciones = [['accion' => 'eliminar', 'evento_id' => $eventos[0]['id']],
            ['accion' => 'registrar', 'datos' => ['tipo' => 'gol', 'minuto' => 20, 'equipo_partido_id' => $visitante->id, 'plantilla_id' => $e['plantillas'][$visitante->campeonato_equipo_id]]]];
        $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => $operaciones])->assertOk();
        $this->entrar($admin);
        $vista = $this->getJson('/api/fases/'.$e['fase']['id'].'/clasificacion-grupos')->assertOk()->assertJsonPath('clasificacion_vigente', false)->json();
        $this->assertNotSame($antes['huella'], $vista['huella']);
        $this->assertSame('en_curso', Fase::findOrFail($e['fase']['id'])->estado);
        $this->assertSame(1, AuditoriaCambio::where('accion', 'invalidar_clasificacion_grupos')->count());
        $this->assertSame($fecha, $p->fresh()->oficializado_at->toISOString());
        $this->postJson('/api/fases/'.$e['fase']['id'].'/clasificar-grupos', ['confirmacion' => $antes['confirmacion']])->assertConflict();
        $this->assertTrue($this->clasificar($e)['clasificacion_vigente']);
        $this->assertSame(2, AuditoriaCambio::where('accion', 'clasificar_grupos')->count());
    }

    public function test_corregir_minuto_no_invalida_una_clasificacion_sin_cambios_deportivos(): void
    {
        $e = $this->preparar(4);
        $this->calendario($e);
        $p = Partido::firstOrFail();
        $eventos = $this->oficializar($e, $p);
        $this->oficializar($e, Partido::whereKeyNot($p->id)->firstOrFail());
        $antes = $this->clasificar($e);
        $c = $this->aprobarCorreccion($p);
        $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => [['accion' => 'corregir_minuto', 'evento_id' => $eventos[0]['id'], 'minuto' => 22]]])->assertOk();
        $this->getJson('/api/fases/'.$e['fase']['id'].'/clasificacion-grupos')->assertOk()->assertJsonPath('huella', $antes['huella'])->assertJsonPath('clasificacion_vigente', true);
        $this->assertSame(0, AuditoriaCambio::where('accion', 'invalidar_clasificacion_grupos')->count());
    }

    public function test_calendario_rechaza_miembro_sin_grupo_o_inactivo_sin_escrituras_parciales(): void
    {
        $e = $this->preparar(4);
        $miembro = Fase::findOrFail($e['fase']['id'])->equipos()->firstOrFail();
        $grupoId = $miembro->grupo_id;
        $miembro->update(['grupo_id' => null]);
        $this->postJson('/api/fases/'.$e['fase']['id'].'/generar-calendario-grupos')->assertConflict();
        $this->assertSame(0, Partido::count());
        $miembro->update(['grupo_id' => $grupoId, 'estado' => 'retirado']);
        $this->postJson('/api/fases/'.$e['fase']['id'].'/generar-calendario-grupos')->assertConflict();
        $this->assertSame(0, Partido::count());
        $this->assertSame(0, AuditoriaCambio::where('accion', 'generar_calendario_grupos')->count());
    }

    public function test_correccion_no_altera_eliminatoria_posterior_y_no_consume_autorizacion(): void
    {
        $e = $this->preparar(4);
        $this->calendario($e);
        $p = Partido::firstOrFail();
        $eventos = $this->oficializar($e, $p);
        $this->oficializar($e, Partido::whereKeyNot($p->id)->firstOrFail());
        $this->clasificar($e);
        // Simula una etapa posterior: la API todavía no genera estos cruces.
        $posterior = Fase::create(['campeonato_id' => $e['campeonato']['id'], 'nombre' => 'Posterior', 'orden' => 2, 'tipo' => 'eliminacion', 'estado' => 'en_curso']);
        $ronda = $posterior->rondas()->create(['numero' => 1, 'nombre' => 'Final', 'modalidad' => 'unico', 'desempate' => 'penales']);
        $llave = $ronda->llaves()->create(['numero' => 1]);
        $final = new Partido(['campeonato_id' => $e['campeonato']['id'], 'jornada' => 1, 'estado' => 'en_curso']);
        $final->fase_id = $posterior->id;
        $final->llave_id = $llave->id;
        $final->save();
        $c = $this->aprobarCorreccion($p);
        $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => [['accion' => 'eliminar', 'evento_id' => $eventos[0]['id']]]])->assertConflict();
        $this->assertDatabaseHas('eventos_estadisticos', ['id' => $eventos[0]['id']]);
        $this->assertSame('aprobada', AutorizacionCorreccion::findOrFail($c['id'])->estado);
        $this->assertSame('en_curso', $final->fresh()->estado);
        $this->assertSame(0, AuditoriaCambio::where('accion', 'invalidar_clasificacion_grupos')->count());
    }
}
