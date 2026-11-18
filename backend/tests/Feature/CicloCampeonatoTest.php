<?php

namespace Tests\Feature;

use App\Models\AuditoriaCambio;
use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use App\Models\Plantilla;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CicloCampeonatoTest extends BackendTestCase
{
    private function preparar(string $formato = 'todos_contra_todos'): array
    {
        $admin = auth('web')->user();
        $c = $this->postJson('/api/campeonatos', ['nombre' => 'Ciclo ficticio', 'temporada' => '2026', 'formato' => $formato])->assertCreated()->json('campeonato');
        $ruta = '/api/campeonatos/'.$c['id'];
        $this->patchJson($ruta.'/requisitos-inicio', ['modalidad' => 'Modalidad del reglamento ficticio', 'minimo_jugadores' => 1])->assertOk();
        $ids = $plantillas = [];
        for ($i = 0; $i < ($formato === 'todos_contra_todos' ? 2 : 4); $i++) {
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
            $datos = ['siembra' => $ids, 'rondas' => array_fill(0, 2, ['modalidad' => 'unico', 'desempate' => 'penales'])];
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

    public function test_ciclo_real_de_los_tres_formatos_y_cierre_inmutable(): void
    {
        foreach (['todos_contra_todos', 'eliminacion_directa', 'grupos_eliminacion'] as $formato) {
            $e = $this->preparar($formato);
            $this->postJson($e['ruta'].'/iniciar')->assertOk()->assertJsonPath('campeonato.estado', 'en_curso');
            $this->postJson($e['ruta'].'/finalizar')->assertConflict();
            foreach (Partido::where('campeonato_id', $e['c']['id'])->get() as $p) {
                $this->resultado($e, $p);
            }
            if ($formato === 'grupos_eliminacion') {
                $f = Fase::where('campeonato_id', $e['c']['id'])->where('tipo', 'grupos')->sole();
                $v = $this->getJson('/api/fases/'.$f->id.'/clasificacion-grupos')->assertOk()->json();
                $this->postJson('/api/fases/'.$f->id.'/clasificar-grupos', ['confirmacion' => $v['confirmacion']])->assertOk();
                $v = $this->postJson('/api/fases/'.$f->id.'/previsualizar-eliminacion', ['metodo' => 'automatico', 'rondas' => [['modalidad' => 'unico', 'desempate' => 'penales']]])->assertOk()->json();
                $f = $this->postJson('/api/fases/'.$f->id.'/generar-eliminacion', array_intersect_key($v, array_flip(['metodo', 'rondas', 'sorteo', 'confirmacion_sorteo', 'cruces', 'confirmacion'])))->assertCreated()->json('fase');
                $this->resultado($e, Partido::where('fase_id', $f['id'])->sole());
            }
            if ($formato !== 'todos_contra_todos') {
                $f = Fase::where('campeonato_id', $e['c']['id'])->where('tipo', 'eliminacion')->sole();
                foreach ($f->rondas as $ronda) {
                    foreach ($ronda->llaves as $llave) {
                        if ($formato === 'eliminacion_directa' && $ronda->numero > 1) {
                            $final = Partido::where('llave_id', $llave->id)->sole();
                            $this->resultado($e, $final);
                            $campeon = $final->participantes()->where('rol', 'local')->sole()->campeonato_equipo_id;
                        }
                        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertOk();
                    }
                }
            }
            $cierre = $this->postJson($e['ruta'].'/finalizar')->assertOk()->assertJsonPath('campeonato.estado', 'finalizado')->assertJsonStructure(['campeon_campeonato_equipo_id']);
            if ($formato === 'eliminacion_directa') {
                $cierre->assertJsonPath('campeon_campeonato_equipo_id', $campeon);
            }
            $this->postJson($e['ruta'].'/iniciar')->assertConflict();
            $this->postJson($e['ruta'].'/finalizar')->assertConflict();
            $this->patchJson($e['ruta'].'/requisitos-inicio', ['modalidad' => 'Cambio', 'minimo_jugadores' => 2])->assertConflict();
            $this->patchJson('/api/plantillas/'.$e['plantillas'][$e['ids'][0]], ['dorsal' => 45])->assertConflict();
            $this->getJson($e['ruta'].'/auditoria-ciclo')->assertOk();
            $this->assertSame(1, AuditoriaCambio::where('campeonato_id', $e['c']['id'])->where('accion', 'finalizar_campeonato')->count());
        }
    }

    public function test_inicio_exige_minimos_arbitro_y_bloquea_actas_previas_y_plantillas_posteriores(): void
    {
        $e = $this->preparar();
        $p = Partido::where('campeonato_id', $e['c']['id'])->sole();
        $this->entrar($e['arbitro']);
        $this->patchJson('/api/partidos/'.$p->id, ['estado' => 'en_curso'])->assertConflict();
        $this->postJson($e['ruta'].'/iniciar')->assertForbidden();
        $this->entrar($e['admin']);
        $this->patchJson($e['ruta'].'/requisitos-inicio', ['modalidad' => 'Ficticia', 'minimo_jugadores' => 2])->assertOk();
        $this->postJson($e['ruta'].'/iniciar')->assertConflict();
        $this->assertDatabaseHas('campeonatos', ['id' => $e['c']['id'], 'estado' => 'planificacion', 'iniciado_at' => null]);
        foreach ($e['ids'] as $id) {
            $this->plantilla($id, null, 11);
        }
        $this->deleteJson($e['ruta'].'/arbitro', ['motivo_revocacion' => 'Prueba ficticia'])->assertOk();
        $this->postJson($e['ruta'].'/iniciar')->assertConflict();
        $this->postJson($e['ruta'].'/arbitro', ['usuario_id' => $e['arbitro']->id])->assertCreated();
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        $this->patchJson('/api/plantillas/'.$e['plantillas'][$e['ids'][0]], ['fecha_salida' => today()->toDateString()])->assertConflict();
        $jugador = $this->jugador()['id'];
        $this->postJson('/api/inscripciones-equipos/'.$e['ids'][0].'/plantillas', ['jugador_id' => $jugador, 'dorsal' => 30, 'posicion' => 'Prueba', 'fecha_ingreso' => today()->toDateString()])->assertConflict();
        $this->deleteJson('/api/plantillas/'.$e['plantillas'][$e['ids'][0]])->assertConflict();
        $this->patchJson('/api/inscripciones-equipos/'.$e['ids'][0], ['estado' => 'retirado'])->assertConflict();
        $this->patchJson($e['ruta'].'/configuracion', ['orden_desempate' => 'puntos,goles_favor,diferencia_goles'])->assertConflict();
    }

    public function test_no_hay_minimo_implicito_ni_inicio_sin_estructura_y_permisos(): void
    {
        $e = $this->preparar();
        $c = Campeonato::findOrFail($e['c']['id']);
        $c->configuracion->update(['minimo_jugadores' => null]);
        $this->postJson($e['ruta'].'/iniciar')->assertConflict();
        $this->patchJson($e['ruta'].'/requisitos-inicio', ['modalidad' => 'Ficticia', 'minimo_jugadores' => 0])->assertUnprocessable();
        $this->entrar(User::factory()->create());
        $this->postJson($e['ruta'].'/iniciar')->assertForbidden();
        $this->postJson($e['ruta'].'/finalizar')->assertForbidden();
        $this->getJson($e['ruta'].'/auditoria-ciclo')->assertForbidden();
        $this->entrar($e['admin']);
        $this->postJson($e['ruta'].'/iniciar', ['estado' => 'en_curso'])->assertUnprocessable();
        $this->postJson($e['ruta'].'/finalizar')->assertConflict();
    }

    public function test_empate_absoluto_no_se_resuelve_por_nombre_y_no_deja_cierre_parcial(): void
    {
        $e = $this->preparar();
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        $this->resultado($e, Partido::where('campeonato_id', $e['c']['id'])->sole(), false);
        $this->postJson($e['ruta'].'/finalizar')->assertConflict();
        $this->assertDatabaseHas('campeonatos', ['id' => $e['c']['id'], 'estado' => 'en_curso', 'finalizado_at' => null]);
        $this->assertSame(0, AuditoriaCambio::where('campeonato_id', $e['c']['id'])->where('accion', 'finalizar_campeonato')->count());
    }

    public function test_indice_fisico_dorsal_activo_y_reutilizacion_historica(): void
    {
        $e = $this->preparar();
        $anterior = Plantilla::findOrFail($e['plantillas'][$e['ids'][0]]);
        $datos = $anterior->only(['campeonato_equipo_id', 'jugador_id', 'dorsal', 'posicion', 'fecha_ingreso']);
        try {
            Plantilla::create($datos);
            $this->fail('El índice debe rechazar el duplicado incluso sin controlador.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('UNIQUE', $error->getMessage());
        }
        $this->patchJson('/api/plantillas/'.$anterior->id, ['fecha_salida' => today()->toDateString()])->assertOk();
        $this->plantilla($e['ids'][0], null, $anterior->dorsal);
        $this->assertNotNull($anterior->refresh()->fecha_salida);
        // DP-07: ambos serían elegibles hoy; el inicio se bloquea en lugar de elegir uno.
        $this->postJson($e['ruta'].'/iniciar')->assertConflict();
    }

    public function test_inicio_no_confunde_inscripcion_futura_con_jugador_elegible(): void
    {
        $e = $this->preparar();
        Plantilla::whereKey($e['plantillas'][$e['ids'][0]])->update(['fecha_ingreso' => today()->addDay()->toDateString()]);
        $this->postJson($e['ruta'].'/iniciar')->assertConflict();
        $this->assertDatabaseHas('campeonatos', ['id' => $e['c']['id'], 'estado' => 'planificacion', 'iniciado_at' => null]);
        $this->assertSame(0, AuditoriaCambio::where('campeonato_id', $e['c']['id'])->where('accion', 'iniciar_campeonato')->count());
    }

    public function test_fallo_de_auditoria_revierte_inicio_y_no_deja_fecha_parcial(): void
    {
        $e = $this->preparar();
        // Falla controlada solo durante esta operación; se conserva el listener inmutable.
        $evento = 'eloquent.creating: '.AuditoriaCambio::class;
        $dispatcher = AuditoriaCambio::getEventDispatcher();
        $temporal = clone $dispatcher;
        $temporal->listen($evento, function ($a) {
            if ($a->accion === 'iniciar_campeonato') {
                throw new \RuntimeException('Fallo ficticio de auditoría');
            }
        });
        AuditoriaCambio::setEventDispatcher($temporal);
        try {
            $this->postJson($e['ruta'].'/iniciar')->assertStatus(500);
        } finally {
            AuditoriaCambio::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseHas('campeonatos', ['id' => $e['c']['id'], 'estado' => 'planificacion', 'iniciado_at' => null]);
        $this->assertSame(0, AuditoriaCambio::where('campeonato_id', $e['c']['id'])->where('accion', 'iniciar_campeonato')->count());
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
    }

    public function test_no_se_cambia_la_fecha_de_un_partido_con_eventos(): void
    {
        $e = $this->preparar();
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        $p = Partido::where('campeonato_id', $e['c']['id'])->sole();
        $this->entrar($e['arbitro']);
        $this->patchJson('/api/partidos/'.$p->id, ['estado' => 'en_curso'])->assertOk();
        $local = $p->participantes()->where('rol', 'local')->sole();
        $this->postJson('/api/partidos/'.$p->id.'/eventos', ['tipo' => 'gol', 'minuto' => 1, 'equipo_partido_id' => $local->id, 'plantilla_id' => $e['plantillas'][$local->campeonato_equipo_id]])->assertCreated();
        $this->entrar($e['admin']);
        $antes = $p->refresh()->getRawOriginal('fecha_hora');
        $auditorias = AuditoriaCambio::count();
        $this->patchJson('/api/partidos/'.$p->id, ['fecha_hora' => '2027-01-01 12:00:00'])->assertConflict();
        $this->assertSame($antes, $p->refresh()->getRawOriginal('fecha_hora'));
        $this->assertSame($auditorias, AuditoriaCambio::count());
    }

    public function test_resultado_no_oficial_y_correccion_pendiente_impiden_cierre_sin_consumirla(): void
    {
        $e = $this->preparar();
        $this->postJson($e['ruta'].'/iniciar')->assertOk();
        $p = Partido::where('campeonato_id', $e['c']['id'])->sole();
        $this->resultado($e, $p);
        $fecha = $p->refresh()->oficializado_at;
        DB::table('partidos')->where('id', $p->id)->update(['oficializado_at' => null]);
        $this->postJson($e['ruta'].'/finalizar')->assertConflict();
        DB::table('partidos')->where('id', $p->id)->update(['oficializado_at' => $fecha]);
        $this->entrar($e['arbitro']);
        $correccion = $this->postJson('/api/partidos/'.$p->id.'/correcciones', ['motivo' => 'Revisión ficticia pendiente', 'alcance' => 'eventos'])->assertCreated()->json('correccion');
        $this->entrar($e['admin']);
        $this->postJson($e['ruta'].'/finalizar')->assertConflict();
        $this->patchJson('/api/correcciones/'.$correccion['id'].'/autorizar', ['estado' => 'aprobada'])->assertOk();
        $auditorias = AuditoriaCambio::count();
        $this->postJson($e['ruta'].'/finalizar')->assertConflict();
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $correccion['id'], 'estado' => 'aprobada', 'fecha_consumo' => null]);
        $this->assertSame($auditorias, AuditoriaCambio::count());
        $this->assertDatabaseHas('campeonatos', ['id' => $e['c']['id'], 'estado' => 'en_curso', 'finalizado_at' => null]);
    }
}
