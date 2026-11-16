<?php

namespace Tests\Feature;

use App\Models\AuditoriaCambio;
use App\Models\CampeonatoEquipo;
use App\Models\EventoEstadistico;
use App\Models\Fase;
use App\Models\Partido;
use App\Models\User;

class ResolucionAdministrativaTest extends BackendTestCase
{
    private function preparar(string $formato = 'todos_contra_todos', string $modalidad = 'unico', int $cantidad = 2): array
    {
        $admin = auth('web')->user();
        $c = $this->postJson('/api/campeonatos', ['nombre' => 'Ciclo ficticio', 'temporada' => '2026', 'formato' => $formato])->assertCreated()->json('campeonato');
        $ruta = '/api/campeonatos/'.$c['id'];
        $this->patchJson($ruta.'/requisitos-inicio', ['modalidad' => 'Modalidad del reglamento ficticio', 'minimo_jugadores' => 1])->assertOk();
        $ids = $plantillas = [];
        for ($i = 0; $i < ($formato === 'grupos_eliminacion' ? 4 : $cantidad); $i++) {
            $id = $this->inscripcion($c['id'], 'Ficticio '.$i)['id'];
            $ids[] = $id;
            $plantillas[$id] = $this->plantilla($id)['id'];
        }
        $arbitro = User::factory()->create();
        $this->postJson($ruta.'/arbitro', ['usuario_id' => $arbitro->id])->assertCreated();
        if ($formato === 'todos_contra_todos') {
            $this->postJson($ruta.'/generar-calendario')->assertCreated();
        } elseif ($formato === 'eliminacion_directa') {
            $f = $this->postJson($ruta.'/fases', ['tipo' => 'eliminacion', 'nombre' => 'Final'])->assertCreated()->json('fase');
            $datos = ['siembra' => $ids, 'rondas' => array_fill(0, (int) ceil(log(count($ids), 2)), ['modalidad' => $modalidad, 'desempate' => 'penales'])];
            $vista = $this->postJson('/api/fases/'.$f['id'].'/previsualizar-llaves', $datos)->assertOk()->json();
            $this->postJson('/api/fases/'.$f['id'].'/generar-llaves', $datos + ['confirmacion' => $vista['confirmacion']])->assertCreated();
        } else {
            $f = $this->postJson($ruta.'/fases', ['tipo' => 'grupos', 'nombre' => 'Grupos'])->assertCreated()->json('fase');
            $vista = $this->postJson('/api/fases/'.$f['id'].'/previsualizar-grupos', ['metodo' => 'manual', 'cantidad_grupos' => 2, 'clasificados_por_grupo' => 1, 'grupos' => [array_slice($ids, 0, 2), array_slice($ids, 2)]])->assertOk()->json();
            unset($vista['fase_id']);
            $this->postJson('/api/fases/'.$f['id'].'/confirmar-grupos', $vista)->assertCreated();
            $this->postJson('/api/fases/'.$f['id'].'/generar-calendario-grupos')->assertCreated();
        }

        return compact('c', 'ruta', 'ids', 'plantillas', 'admin', 'arbitro');
    }

    private function entrar(User $u): void
    {
        app('auth')->forgetGuards();
        $this->actingAs($u, 'web');
    }

    private function resultado(array $e, Partido $p, bool $gol = true): void
    {
        $this->entrar($e['arbitro']);
        $this->patchJson('/api/partidos/'.$p->id, ['estado' => 'en_curso'])->assertOk();
        if ($gol) {
            $local = $p->participantes()->where('rol', 'local')->sole();
            $this->postJson('/api/partidos/'.$p->id.'/eventos', ['tipo' => 'gol', 'minuto' => 12, 'equipo_partido_id' => $local->id, 'plantilla_id' => $e['plantillas'][$local->campeonato_equipo_id]])->assertCreated();
        }
        $this->postJson('/api/partidos/'.$p->id.'/finalizar')->assertOk();
        $this->entrar($e['admin']);
    }

    private function administrativo(Partido $p, ?int $beneficiado = null, array $extra = [])
    {
        $ids = $p->participantes()->pluck('campeonato_equipo_id')->all();
        $beneficiado ??= $ids[0];

        return $this->postJson('/api/partidos/'.$p->id.'/resultado-administrativo', $extra + ['beneficiado_id' => $beneficiado, 'sancionado_id' => collect($ids)->first(fn ($id) => $id !== $beneficiado), 'motivo' => 'Decisión ficticia motivada']);
    }

    private function sancion(int $id, string $tipo = 'descalificacion')
    {
        return $this->postJson('/api/inscripciones-equipos/'.$id.'/decision-administrativa', ['tipo' => $tipo, 'motivo' => 'Decisión ficticia motivada']);
    }

    private function autorizar(array $e, Partido $p): int
    {
        $id = $this->postJson('/api/partidos/'.$p->id.'/correcciones', ['motivo' => 'Rectificación ficticia motivada', 'alcance' => 'resultado'])->assertCreated()->json('correccion.id');
        $this->patchJson('/api/correcciones/'.$id.'/autorizar', ['estado' => 'aprobada'])->assertOk();

        return $id;
    }

    public function test_tres_formatos_oficializan_administrativos_sin_eventos_y_finalizan(): void
    {
        foreach (['todos_contra_todos', 'eliminacion_directa', 'grupos_eliminacion'] as $formato) {
            $e = $this->preparar($formato);
            $this->postJson($e['ruta'].'/iniciar')->assertOk();
            foreach (Partido::where('campeonato_id', $e['c']['id'])->get() as $p) {
                $this->administrativo($p)->assertOk();
                $this->getJson('/api/partidos/'.$p->id.'/marcador')->assertOk()->assertJsonPath('local.goles', 3)->assertJsonPath('visitante.goles', 0)->assertJsonPath('marcador_deportivo.local', 0);
            }
            if ($formato === 'grupos_eliminacion') {
                $f = Fase::where('campeonato_id', $e['c']['id'])->where('tipo', 'grupos')->sole();
                $v = $this->getJson('/api/fases/'.$f->id.'/clasificacion-grupos')->assertOk()->json();
                $this->postJson('/api/fases/'.$f->id.'/clasificar-grupos', ['confirmacion' => $v['confirmacion']])->assertOk();
                $v = $this->postJson('/api/fases/'.$f->id.'/previsualizar-eliminacion', ['metodo' => 'automatico', 'rondas' => [['modalidad' => 'unico', 'desempate' => 'penales']]])->assertOk()->json();
                $f = $this->postJson('/api/fases/'.$f->id.'/generar-eliminacion', array_intersect_key($v, array_flip(['metodo', 'rondas', 'sorteo', 'confirmacion_sorteo', 'cruces', 'confirmacion'])))->assertCreated()->json('fase');
                $this->administrativo(Partido::where('fase_id', $f['id'])->sole())->assertOk();
            }
            if ($formato !== 'todos_contra_todos') {
                $f = Fase::where('campeonato_id', $e['c']['id'])->where('tipo', 'eliminacion')->sole();
                $this->postJson('/api/llaves/'.$f->rondas->first()->llaves->first()->id.'/avanzar')->assertOk();
            }
            $this->getJson($e['ruta'].'/estadisticas/goleadores')->assertOk()->assertJsonCount(0, 'resultados');
            $this->postJson($e['ruta'].'/finalizar')->assertOk();
        }
        $this->assertDatabaseCount('eventos_estadisticos', 0);
    }

    public function test_permisos_motivo_participantes_duplicados_y_rectificacion_consumible(): void
    {
        $e = $this->preparar();
        $p = Partido::where('campeonato_id', $e['c']['id'])->sole();
        $this->administrativo($p)->assertConflict();
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        $this->entrar($e['arbitro']);
        $this->administrativo($p)->assertForbidden();
        $this->sancion($e['ids'][0])->assertForbidden();
        $this->entrar(User::factory()->create());
        $this->administrativo($p)->assertForbidden();
        $this->entrar($e['admin']);
        $this->administrativo($p, null, ['motivo' => ' '])->assertUnprocessable();
        $this->administrativo($p, 99999)->assertUnprocessable();
        $this->resultado($e, $p);
        $this->administrativo($p)->assertConflict();
        $ids = EventoEstadistico::pluck('id')->all();
        $autorizacion = $this->autorizar($e, $p);
        $this->administrativo($p, $e['ids'][1], ['autorizacion_id' => $autorizacion])->assertOk();
        $this->assertSame($ids, EventoEstadistico::pluck('id')->all());
        $this->getJson('/api/partidos/'.$p->id.'/marcador')->assertOk()->assertJsonPath('local.goles', 0)->assertJsonPath('visitante.goles', 3)->assertJsonPath('marcador_deportivo.local', 1);
        $this->getJson($e['ruta'].'/estadisticas/goleadores')->assertOk()->assertJsonPath('resultados.0.total', 1);
        $equipo = collect($this->getJson($e['ruta'].'/estadisticas/equipos')->assertOk()->json('resultados'))->keyBy('equipo_id');
        $this->assertSame(3, $equipo[CampeonatoEquipo::find($e['ids'][1])->equipo_id]['goles']);
        $this->administrativo($p, $e['ids'][0], ['autorizacion_id' => $autorizacion])->assertConflict();
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $autorizacion, 'estado' => 'consumida']);
        $this->assertSame(1, AuditoriaCambio::where('accion', 'resultado_administrativo')->count());
    }

    public function test_descalificacion_ida_vuelta_supera_global_sin_reescribir_la_ida(): void
    {
        $e = $this->preparar('eliminacion_directa', 'ida_vuelta');
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        [$ida, $vuelta] = Partido::where('campeonato_id', $e['c']['id'])->orderBy('numero_serie')->get()->all();
        $local = $ida->participantes()->where('rol', 'local')->sole();
        $this->entrar($e['arbitro']);
        $this->patchJson('/api/partidos/'.$ida->id, ['estado' => 'en_curso'])->assertOk();
        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/api/partidos/'.$ida->id.'/eventos', ['tipo' => 'gol', 'minuto' => $i, 'equipo_partido_id' => $local->id, 'plantilla_id' => $e['plantillas'][$local->campeonato_equipo_id]])->assertCreated();
        }
        $this->postJson('/api/partidos/'.$ida->id.'/finalizar')->assertOk();
        $this->entrar($e['admin']);
        $antes = $ida->refresh()->toArray();
        $this->sancion($e['ids'][0])->assertOk()->assertJsonPath('inscripcion.estado', 'descalificado');
        $this->administrativo($vuelta, $e['ids'][1])->assertOk();
        $this->postJson('/api/llaves/'.$ida->llave_id.'/avanzar')->assertOk()->assertJsonPath('llave.metodo_resolucion', 'administrativo');
        $this->postJson($e['ruta'].'/finalizar')->assertOk()->assertJsonPath('campeon_campeonato_equipo_id', $e['ids'][1]);
        $this->assertSame($antes, $ida->refresh()->toArray());
        $this->assertDatabaseCount('eventos_estadisticos', 4);
    }

    public function test_doble_descalificacion_bloquea_sin_asignar_ganador(): void
    {
        $e = $this->preparar('eliminacion_directa');
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        $p = Partido::where('campeonato_id', $e['c']['id'])->sole();
        $this->sancion($e['ids'][0])->assertOk();
        $this->sancion($e['ids'][1])->assertOk();
        $this->sancion($e['ids'][1])->assertConflict();
        $this->administrativo($p)->assertConflict();
        $this->postJson('/api/llaves/'.$p->llave_id.'/avanzar')->assertConflict();
        $this->postJson($e['ruta'].'/finalizar')->assertConflict();
        $this->assertDatabaseHas('llaves', ['id' => $p->llave_id, 'estado' => 'pendiente', 'ganador_fase_equipo_id' => null]);
        $this->assertDatabaseCount('eventos_estadisticos', 0);
        $this->getJson($e['ruta'].'/auditoria-ciclo')->assertOk()->assertJsonFragment(['accion' => 'bloquear_doble_inactividad']);
    }

    public function test_rectificacion_incompatible_con_final_iniciada_no_consume_autorizacion(): void
    {
        $e = $this->preparar('eliminacion_directa', 'unico', 4);
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        $semis = Partido::where('campeonato_id', $e['c']['id'])->get();
        foreach ($semis as $p) {
            $this->administrativo($p)->assertOk();
            $this->postJson('/api/llaves/'.$p->llave_id.'/avanzar')->assertOk();
        }
        $final = Partido::where('campeonato_id', $e['c']['id'])->where('jornada', 2)->sole();
        $p = $semis[0]->refresh();
        $participantesAntes = $final->participantes()->orderBy('id')->get(['id', 'campeonato_equipo_id']);
        $valida = $this->autorizar($e, $p);
        $this->administrativo($p, $p->administrativo_sancionado_id, ['autorizacion_id' => $valida])->assertOk();
        $participantesDespues = $final->participantes()->orderBy('id')->get(['id', 'campeonato_equipo_id']);
        $this->assertSame($participantesAntes->pluck('id')->all(), $participantesDespues->pluck('id')->all());
        $this->assertNotSame($participantesAntes->pluck('campeonato_equipo_id')->all(), $participantesDespues->pluck('campeonato_equipo_id')->all());
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $valida, 'estado' => 'consumida']);
        $this->entrar($e['arbitro']);
        $this->patchJson('/api/partidos/'.$final->id, ['estado' => 'en_curso'])->assertOk();
        $this->entrar($e['admin']);
        $p = $semis[0]->refresh();
        $autorizacion = $this->autorizar($e, $p);
        $antes = $p->toArray();
        $audit = AuditoriaCambio::count();
        $this->administrativo($p, $p->administrativo_sancionado_id, ['autorizacion_id' => $autorizacion])->assertConflict();
        $this->assertSame($antes, $p->refresh()->toArray());
        $this->assertSame($audit, AuditoriaCambio::count());
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $autorizacion, 'estado' => 'aprobada', 'fecha_consumo' => null]);
    }

    public function test_retiro_en_grupos_no_reasigna_automaticamente_un_cupo_invalido(): void
    {
        $e = $this->preparar('grupos_eliminacion');
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        foreach (Partido::where('campeonato_id', $e['c']['id'])->get() as $p) {
            $this->administrativo($p)->assertOk();
        }
        $this->sancion($e['ids'][0], 'retiro')->assertOk()->assertJsonPath('inscripcion.estado', 'retirado');
        $f = Fase::where('campeonato_id', $e['c']['id'])->sole();
        $this->getJson('/api/fases/'.$f->id.'/clasificacion-grupos')->assertOk()->assertJsonPath('lista_para_clasificar', false);
    }

    public function test_retiro_de_no_clasificado_conserva_resultados_y_exige_nueva_confirmacion(): void
    {
        $e = $this->preparar('grupos_eliminacion');
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        foreach (Partido::where('campeonato_id', $e['c']['id'])->get() as $p) {
            $this->administrativo($p)->assertOk();
        }
        $f = Fase::where('campeonato_id', $e['c']['id'])->sole();
        $ruta = '/api/fases/'.$f->id.'/clasificacion-grupos';
        $v = $this->getJson($ruta)->assertOk()->json();
        $this->postJson('/api/fases/'.$f->id.'/clasificar-grupos', ['confirmacion' => $v['confirmacion']])->assertOk();
        $this->sancion($e['ids'][0])->assertConflict();
        $this->sancion($e['ids'][1], 'retiro')->assertOk();
        $nuevo = $this->getJson($ruta)->assertOk()->assertJsonPath('lista_para_clasificar', true)->assertJsonPath('clasificacion_vigente', false)->json();
        $this->assertSame($v['candidatos'], $nuevo['candidatos']);
        $this->postJson('/api/fases/'.$f->id.'/clasificar-grupos', ['confirmacion' => $v['confirmacion']])->assertConflict();
        $this->postJson('/api/fases/'.$f->id.'/clasificar-grupos', ['confirmacion' => $nuevo['confirmacion']])->assertOk();
    }

    public function test_fallo_de_auditoria_revierte_resolucion_sin_sobrescribir_el_acta(): void
    {
        $e = $this->preparar();
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        $p = Partido::where('campeonato_id', $e['c']['id'])->sole();
        $antes = $p->toArray();
        $dispatcher = AuditoriaCambio::getEventDispatcher();
        $temporal = clone $dispatcher;
        $temporal->listen('eloquent.creating: '.AuditoriaCambio::class, function ($a) {
            if ($a->accion === 'resultado_administrativo') {
                throw new \RuntimeException('Fallo ficticio de auditoría');
            }
        });
        AuditoriaCambio::setEventDispatcher($temporal);
        try {
            $this->administrativo($p)->assertStatus(500);
        } finally {
            AuditoriaCambio::setEventDispatcher($dispatcher);
        }
        $this->assertSame($antes, $p->refresh()->toArray());
        $this->assertSame(0, AuditoriaCambio::where('accion', 'resultado_administrativo')->count());
    }

    public function test_no_deja_inutilizable_una_autorizacion_de_eventos_aprobada(): void
    {
        $e = $this->preparar();
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        $p = Partido::where('campeonato_id', $e['c']['id'])->sole();
        $this->resultado($e, $p);
        $this->entrar($e['arbitro']);
        $eventos = $this->postJson('/api/partidos/'.$p->id.'/correcciones', ['alcance' => 'eventos', 'motivo' => 'Revisar minuto ficticio'])->assertCreated()->json('correccion.id');
        $this->entrar($e['admin']);
        $this->patchJson('/api/correcciones/'.$eventos.'/autorizar', ['estado' => 'aprobada'])->assertOk();
        $resultado = $this->autorizar($e, $p);
        $this->administrativo($p, $e['ids'][1], ['autorizacion_id' => $resultado])->assertConflict();
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $resultado, 'estado' => 'aprobada', 'fecha_consumo' => null]);
        $this->entrar($e['arbitro']);
        $evento = EventoEstadistico::sole();
        $this->postJson('/api/correcciones/'.$eventos.'/aplicar', ['operaciones' => [['accion' => 'corregir_minuto', 'evento_id' => $evento->id, 'minuto' => 20]]])->assertOk();
        $this->entrar($e['admin']);
        $this->administrativo($p, $e['ids'][1], ['autorizacion_id' => $resultado])->assertOk();
        $this->assertDatabaseHas('eventos_estadisticos', ['id' => $evento->id, 'minuto' => 20]);
    }

    public function test_descalificacion_en_eliminacion_de_grupos_conserva_la_clasificacion_historica(): void
    {
        foreach ([false, true] as $conDesempate) {
            $e = $this->preparar('grupos_eliminacion');
            $this->postJson($e['ruta'].'/iniciar')->assertOk();
            $grupos = Fase::where('campeonato_id', $e['c']['id'])->where('tipo', 'grupos')->sole();
            foreach ($grupos->partidos()->get() as $p) {
                if ($conDesempate) {
                    $this->resultado($e, $p, false);
                } else {
                    $this->administrativo($p)->assertOk();
                }
            }
            if ($conDesempate) {
                foreach ($grupos->grupos as $grupo) {
                    $clave = $this->getJson('/api/grupos/'.$grupo->id.'/tabla?vista=oficial')->assertOk()->json('pendientes_resolucion.0.clave');
                    $v = $this->postJson('/api/grupos/'.$grupo->id.'/previsualizar-desempate', ['clave' => $clave])->assertOk()->json();
                    $caso = $this->postJson('/api/grupos/'.$grupo->id.'/generar-desempate', ['clave' => $clave, 'confirmacion' => $v['confirmacion']])->assertCreated()->json('desempate');
                    $p = Partido::where('grupo_desempate_id', $caso['id'])->sole();
                    $this->administrativo($p)->assertOk();
                    $this->postJson('/api/llaves/'.$p->llave_id.'/avanzar')->assertOk();
                }
            }
            $ruta = '/api/fases/'.$grupos->id.'/clasificacion-grupos';
            $v = $this->getJson($ruta)->assertOk()->json();
            $this->postJson('/api/fases/'.$grupos->id.'/clasificar-grupos', ['confirmacion' => $v['confirmacion']])->assertOk();
            $v = $this->postJson('/api/fases/'.$grupos->id.'/previsualizar-eliminacion', ['metodo' => 'automatico', 'rondas' => [['modalidad' => 'unico', 'desempate' => 'penales']]])->assertOk()->json();
            $f = $this->postJson('/api/fases/'.$grupos->id.'/generar-eliminacion', array_intersect_key($v, array_flip(['metodo', 'rondas', 'sorteo', 'confirmacion_sorteo', 'cruces', 'confirmacion'])))->assertCreated()->json('fase');
            $antes = $this->getJson($ruta)->assertOk()->json();
            $final = Partido::where('fase_id', $f['id'])->sole();
            $ids = $final->participantes()->pluck('campeonato_equipo_id')->all();
            $this->sancion($ids[0])->assertOk();
            $despues = $this->getJson($ruta)->assertOk()->assertJsonPath('clasificacion_vigente', true)->json();
            $this->assertSame($antes['huella'], $despues['huella']);
            $this->assertSame($antes['candidatos'], $despues['candidatos']);
            $this->administrativo($final, $ids[1])->assertOk();
            $this->postJson('/api/llaves/'.$final->llave_id.'/avanzar')->assertOk();
            $this->postJson($e['ruta'].'/finalizar')->assertOk()->assertJsonPath('campeon_campeonato_equipo_id', $ids[1]);
        }
    }
}
