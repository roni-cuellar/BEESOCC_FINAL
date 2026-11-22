<?php

namespace Tests\Feature;

use App\Models\AuditoriaCambio;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;

class ResultadoOficialTest extends BackendTestCase
{
    private function entrar(User $usuario): void
    {
        app('auth')->forgetGuards();
        $this->actingAs($usuario, 'web');
    }

    private function oficial(bool $conGol = true, bool $conAsistencia = false): array
    {
        $e = $this->escenario();
        $e['admin'] = auth('web')->user();
        $e['arbitro'] = Partido::findOrFail($e['partido']['id'])->campeonato->asignacionesArbitro()->whereNull('fecha_fin')->firstOrFail()->usuario;
        $e['gol'] = $conGol ? $this->evento($e['partido'], $this->participante($e['partido'], 'local'), $e['plantillaLocal']['id']) : null;
        if ($conAsistencia) {
            $asistente = $this->plantilla($e['local']['id'], null, 11);
            $e['asistencia'] = $this->evento($e['partido'], $this->participante($e['partido'], 'local'), $asistente['id'], 'asistencia', $e['gol']['id']);
        }
        $this->entrar($e['arbitro']);
        $this->postJson('/api/partidos/'.$e['partido']['id'].'/finalizar')->assertOk();
        $e['oficializado_at'] = Partido::findOrFail($e['partido']['id'])->oficializado_at->toISOString();

        return $e;
    }

    private function solicitar(array $e): array
    {
        $this->entrar($e['arbitro']);

        return $this->postJson('/api/partidos/'.$e['partido']['id'].'/correcciones', [
            'motivo' => 'Corregir el acta según revisión arbitral.', 'alcance' => 'eventos',
        ])->assertCreated()->json('correccion');
    }

    private function aprobar(array $e): array
    {
        $c = $this->solicitar($e);
        $this->entrar($e['admin']);
        $this->patchJson('/api/correcciones/'.$c['id'].'/autorizar', ['estado' => 'aprobada'])->assertOk();
        $this->entrar($e['arbitro']);

        return $c;
    }

    private function agregar(array $e): array
    {
        return ['accion' => 'registrar', 'datos' => ['tipo' => 'autogol', 'minuto' => 20,
            'equipo_partido_id' => $this->participante($e['partido'], 'local'), 'plantilla_id' => $e['plantillaVisitante']['id']]];
    }

    public function test_finalizar_oficializa_sin_duplicar_marcador_y_con_actor_fecha_y_antes_despues(): void
    {
        $e = $this->oficial();
        $p = Partido::findOrFail($e['partido']['id']);
        $this->assertSame('deportivo', $p->tipo_resultado);
        $this->assertNotNull($p->oficializado_at);
        $a = AuditoriaCambio::where('accion', 'oficializar')->sole();
        $this->assertSame($e['arbitro']->id, $a->usuario_id);
        $this->assertSame('en_curso', $a->anterior_json['estado']);
        $this->assertNull($a->anterior_json['oficializado_at']);
        $this->assertSame('finalizado', $a->posterior_json['estado']);
        $this->assertCount(1, $a->posterior_json['eventos']);
        $this->assertNotEmpty($a->motivo);
        $this->assertNotNull($a->fecha);
        $this->getJson('/api/partidos/'.$p->id.'/marcador')->assertOk()->assertJsonPath('local.goles', 1);
        $this->postJson('/api/partidos/'.$p->id.'/finalizar')->assertConflict();
        $this->patchJson('/api/partidos/'.$p->id, ['estado' => 'en_curso'])->assertConflict();
        $this->assertSame(1, AuditoriaCambio::where('accion', 'oficializar')->count());
    }

    public function test_resultado_cero_cero_oficial_no_se_puede_borrar_aunque_no_haya_eventos(): void
    {
        $e = $this->oficial(false);
        $this->entrar($e['admin']);
        $this->deleteJson('/api/partidos/'.$e['partido']['id'])->assertConflict();
        $this->assertDatabaseHas('partidos', ['id' => $e['partido']['id'], 'estado' => 'finalizado']);
    }

    public function test_no_oficializa_partido_programado_o_eventos_incoherentes(): void
    {
        $e = $this->escenario();
        $gol = $this->evento($e['partido'], $this->participante($e['partido'], 'local'), $e['plantillaLocal']['id']);
        DB::table('eventos_estadisticos')->where('id', $gol['id'])->update(['plantilla_id' => $e['plantillaVisitante']['id']]);
        $this->solicitudArbitral('post', '/api/partidos/'.$e['partido']['id'].'/finalizar')->assertUnprocessable();
        $this->assertDatabaseHas('partidos', ['id' => $e['partido']['id'], 'estado' => 'en_curso', 'oficializado_at' => null]);
        $this->assertSame(0, AuditoriaCambio::where('accion', 'oficializar')->count());
        $programado = $this->escenario(false);
        $this->solicitudArbitral('post', '/api/partidos/'.$programado['partido']['id'].'/finalizar')->assertConflict();
    }

    public function test_solicitud_no_abre_el_partido_y_solo_el_propietario_puede_resolver(): void
    {
        $e = $this->oficial();
        $c = $this->solicitar($e);
        $ruta = '/api/correcciones/'.$c['id'].'/autorizar';
        $this->patchJson($ruta, ['estado' => 'aprobada'])->assertForbidden();
        $this->entrar(User::factory()->create());
        $this->patchJson($ruta, ['estado' => 'aprobada'])->assertForbidden();
        $this->entrar($e['admin']);
        $this->patchJson($ruta, ['estado' => 'aprobada'])->assertOk()
            ->assertJsonPath('correccion.autorizado_por_id', $e['admin']->id);
        $this->patchJson($ruta, ['estado' => 'aprobada'])->assertConflict();
        $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => [$this->agregar($e)]])->assertForbidden();
        $this->assertDatabaseCount('eventos_estadisticos', 1);
        $this->assertDatabaseHas('partidos', ['id' => $e['partido']['id'], 'estado' => 'finalizado']);
    }

    public function test_sin_aprobacion_o_rechazada_no_hay_correccion(): void
    {
        $e = $this->oficial();
        $c = $this->solicitar($e);
        $aplicar = '/api/correcciones/'.$c['id'].'/aplicar';
        $this->postJson($aplicar, ['operaciones' => [$this->agregar($e)]])->assertConflict();
        $this->entrar($e['admin']);
        $this->patchJson('/api/correcciones/'.$c['id'].'/autorizar', ['estado' => 'rechazada'])->assertOk();
        $this->entrar($e['arbitro']);
        $this->postJson($aplicar, ['operaciones' => [$this->agregar($e)]])->assertConflict();
        $this->assertDatabaseCount('eventos_estadisticos', 1);
        $this->assertSame(0, AuditoriaCambio::where('accion', 'aplicar_correccion')->count());
    }

    public function test_aprobacion_se_consume_una_vez_actualiza_estadisticas_y_conserva_auditoria_privada(): void
    {
        $e = $this->oficial();
        $c = $this->aprobar($e);
        $datos = ['operaciones' => [$this->agregar($e), ['accion' => 'corregir_minuto', 'evento_id' => $e['gol']['id'], 'minuto' => 18]]];
        $ruta = '/api/correcciones/'.$c['id'].'/aplicar';
        $this->postJson($ruta, $datos)->assertOk()->assertJsonPath('correccion.estado', 'consumida');
        $this->postJson($ruta, $datos)->assertConflict();
        $p = Partido::findOrFail($e['partido']['id']);
        $this->assertSame($e['oficializado_at'], $p->oficializado_at->toISOString());
        $this->assertSame('finalizado', $p->estado);
        $a = AuditoriaCambio::where('accion', 'aplicar_correccion')->sole();
        $this->assertSame($e['arbitro']->id, $a->usuario_id);
        $this->assertCount(1, $a->anterior_json['eventos']);
        $this->assertCount(2, $a->posterior_json['eventos']);
        $this->assertSame('aprobada', $a->anterior_json['correccion']['estado']);
        $this->assertSame('consumida', $a->posterior_json['correccion']['estado']);
        $this->assertNotNull($a->posterior_json['correccion']['fecha_consumo']);
        $this->getJson('/api/partidos/'.$p->id.'/marcador')->assertOk()->assertJsonPath('local.goles', 2);
        $this->entrar($e['admin']);
        $base = '/api/campeonatos/'.$e['campeonato']['id'];
        $this->getJson($base.'/tabla-posiciones')->assertOk()->assertJsonPath('tabla.0.GF', 2)->assertJsonPath('tabla.0.PTS', 3);
        $this->getJson($base.'/estadisticas/goleadores')->assertOk()->assertJsonPath('resultados.0.total', 1);
        $this->getJson('/api/partidos/'.$p->id.'/auditoria')->assertOk();
        $this->patchJson($base.'/publicacion', ['publicado' => true])->assertOk();
        auth('web')->logout();
        app('auth')->forgetGuards();
        $this->getJson('/api/publico/campeonatos/'.$e['campeonato']['id'].'/partidos/'.$p->id)
            ->assertOk()->assertJsonMissingPath('anterior_json')->assertJsonMissingPath('correcciones');
        $this->getJson('/api/partidos/'.$p->id.'/auditoria')->assertUnauthorized();
    }

    public function test_eliminar_evento_oficial_conserva_sus_valores_en_la_bitacora(): void
    {
        $e = $this->oficial();
        $c = $this->aprobar($e);
        $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => [
            ['accion' => 'eliminar', 'evento_id' => $e['gol']['id']],
        ]])->assertOk();
        $this->assertDatabaseMissing('eventos_estadisticos', ['id' => $e['gol']['id']]);
        $a = AuditoriaCambio::where('accion', 'aplicar_correccion')->sole();
        $this->assertSame($e['gol']['id'], $a->anterior_json['eventos'][0]['id']);
        $this->assertSame('gol', $a->anterior_json['eventos'][0]['tipo']);
        $this->assertSame([], $a->posterior_json['eventos']);
        $this->getJson('/api/partidos/'.$e['partido']['id'].'/marcador')->assertOk()->assertJsonPath('local.goles', 0);
    }

    public function test_fallo_en_segunda_operacion_revierte_eventos_y_conserva_aprobacion(): void
    {
        $e = $this->oficial();
        $c = $this->aprobar($e);
        $cantidad = AuditoriaCambio::count();
        $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => [
            $this->agregar($e), ['accion' => 'eliminar', 'evento_id' => 999999],
        ]])->assertNotFound();
        $this->assertDatabaseCount('eventos_estadisticos', 1);
        $this->assertSame($cantidad, AuditoriaCambio::count());
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $c['id'], 'estado' => 'aprobada', 'fecha_consumo' => null]);
    }

    public function test_fallo_de_auditoria_no_confirma_eventos_ni_consumo(): void
    {
        $e = $this->oficial();
        $c = $this->aprobar($e);
        Event::listen('eloquent.creating: '.AuditoriaCambio::class, function ($a) {
            if ($a->accion === 'aplicar_correccion') {
                throw new RuntimeException('Fallo de bitácora simulado.');
            }
        });
        try {
            $this->withoutExceptionHandling()->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => [$this->agregar($e)]]);
            $this->fail('Debió fallar la auditoría.');
        } catch (RuntimeException $excepcion) {
            $this->assertSame('Fallo de bitácora simulado.', $excepcion->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.AuditoriaCambio::class);
        }
        $this->assertDatabaseCount('eventos_estadisticos', 1);
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $c['id'], 'estado' => 'aprobada', 'fecha_consumo' => null]);
    }

    public function test_revocado_y_sustituto_no_pueden_consumir_autorizacion_de_otro_solicitante(): void
    {
        $e = $this->oficial();
        $c = $this->aprobar($e);
        $nuevo = User::factory()->create();
        $this->entrar($e['admin']);
        $this->patchJson('/api/campeonatos/'.$e['campeonato']['id'].'/arbitro', ['usuario_id' => $nuevo->id, 'motivo_revocacion' => 'Reemplazo de prueba'])->assertOk();
        foreach ([$e['arbitro'], $nuevo] as $actor) {
            $this->entrar($actor);
            $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => [$this->agregar($e)]])->assertForbidden();
        }
        $this->assertDatabaseCount('eventos_estadisticos', 1);
    }

    public function test_no_acepta_datos_del_servidor_ni_rectificacion_administrativa_por_arbitro(): void
    {
        $e = $this->oficial();
        $ruta = '/api/partidos/'.$e['partido']['id'].'/correcciones';
        foreach (['solicitado_por_id' => $e['admin']->id, 'autorizado_por_id' => $e['admin']->id, 'estado' => 'aprobada'] as $campo => $valor) {
            $this->postJson($ruta, ['motivo' => 'Revisión', 'alcance' => 'eventos', $campo => $valor])->assertUnprocessable();
        }
        $this->postJson($ruta, ['motivo' => '', 'alcance' => 'eventos'])->assertUnprocessable();
        $this->postJson($ruta, ['motivo' => 'Revisión', 'alcance' => 'resultado'])->assertForbidden();
        $this->assertDatabaseCount('autorizaciones_correccion', 0);
    }

    public function test_historial_finalizado_sin_oficializacion_no_se_convierte_silenciosamente(): void
    {
        $e = $this->oficial();
        DB::table('partidos')->where('id', $e['partido']['id'])->update(['oficializado_at' => null]);
        $this->postJson('/api/partidos/'.$e['partido']['id'].'/correcciones', ['motivo' => 'Revisión', 'alcance' => 'eventos'])->assertConflict();
        $this->assertDatabaseCount('autorizaciones_correccion', 0);
        $this->assertDatabaseHas('partidos', ['id' => $e['partido']['id'], 'oficializado_at' => null]);
    }

    public function test_bitacora_inmutable_y_lectura_privada(): void
    {
        $e = $this->oficial();
        $a = AuditoriaCambio::where('accion', 'oficializar')->sole();
        foreach (['update', 'delete'] as $metodo) {
            try {
                $metodo === 'update' ? $a->update(['motivo' => 'Ocultar cambio']) : $a->delete();
                $this->fail('La bitácora no puede modificarse.');
            } catch (LogicException $excepcion) {
                $this->assertSame('La auditoría es inmutable.', $excepcion->getMessage());
            }
        }
        $this->entrar(User::factory()->create());
        $this->getJson('/api/partidos/'.$e['partido']['id'].'/auditoria')->assertForbidden();
        $this->getJson('/api/partidos/'.$e['partido']['id'].'/correcciones')->assertForbidden();
    }

    public function test_autorizacion_de_un_partido_no_corrige_eventos_de_otro_del_mismo_campeonato(): void
    {
        $e = $this->oficial();
        $this->entrar($e['admin']);
        $otro = $this->partido($e['campeonato']['id'], $e['local']['id'], $e['visitante']['id'], 2);
        $this->solicitudArbitral('patch', '/api/partidos/'.$otro['id'], ['estado' => 'en_curso'])->assertOk();
        $ajeno = $this->evento($otro, $this->participante($otro, 'local'), $e['plantillaLocal']['id']);
        $c = $this->aprobar($e);
        $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', ['operaciones' => [
            ['accion' => 'corregir_minuto', 'evento_id' => $ajeno['id'], 'minuto' => 20],
        ]])->assertUnprocessable();
        $this->assertDatabaseHas('eventos_estadisticos', ['id' => $ajeno['id'], 'minuto' => 15]);
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $c['id'], 'estado' => 'aprobada']);
    }

    public function test_endpoints_de_expediente_requieren_sesion_y_no_admiten_suplantar_oficializacion(): void
    {
        $e = $this->oficial();
        $c = $this->solicitar($e);
        $this->postJson('/api/partidos/'.$e['partido']['id'].'/correcciones', ['motivo' => 'Revisión', 'alcance' => 'eventos', 'fecha_consumo' => now()->toISOString()])->assertUnprocessable();
        auth('web')->logout();
        app('auth')->forgetGuards();
        $this->postJson('/api/partidos/'.$e['partido']['id'].'/finalizar')->assertUnauthorized();
        $this->postJson('/api/partidos/'.$e['partido']['id'].'/correcciones', [])->assertUnauthorized();
        $this->getJson('/api/partidos/'.$e['partido']['id'].'/correcciones')->assertUnauthorized();
        $this->patchJson('/api/correcciones/'.$c['id'].'/autorizar', ['estado' => 'aprobada'])->assertUnauthorized();
        $this->postJson('/api/correcciones/'.$c['id'].'/aplicar', [])->assertUnauthorized();
    }

    public function test_la_migracion_conserva_un_partido_legacy_sin_inferir_oficializacion(): void
    {
        config(['database.connections.legacy_oficial' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $original = DB::getDefaultConnection();
        $nueva = database_path('migrations/2026_11_14_235900_add_oficializacion_and_auditoria.php');
        try {
            DB::setDefaultConnection('legacy_oficial');
            app()->forgetInstance('db.schema');
            Schema::clearResolvedInstance('db.schema');
            foreach (glob(database_path('migrations/*.php')) as $archivo) {
                if (basename($archivo) < basename($nueva)) {
                    (require $archivo)->up();
                }
            }
            $liga = DB::table('campeonatos')->insertGetId(['nombre' => 'Histórico', 'temporada' => '2025', 'formato' => 'todos_contra_todos']);
            $id = DB::table('partidos')->insertGetId(['campeonato_id' => $liga, 'jornada' => 1, 'estado' => 'finalizado', 'fecha_hora' => '2025-01-01 10:00:42']);
            $antes = (array) DB::table('partidos')->find($id);
            (require $nueva)->up();
            $despues = (array) DB::table('partidos')->find($id);
            $this->assertSame($antes, array_intersect_key($despues, $antes));
            $this->assertNull($despues['oficializado_at']);
            $this->assertSame('deportivo', $despues['tipo_resultado']);
            $this->assertSame(0, DB::table('auditoria_cambios')->count());
        } finally {
            DB::setDefaultConnection($original);
            app()->forgetInstance('db.schema');
            Schema::clearResolvedInstance('db.schema');
            DB::purge('legacy_oficial');
        }
    }

    public function test_nuevas_claves_foraneas_preservan_historial_con_restrict(): void
    {
        foreach (['auditoria_cambios' => 2, 'autorizaciones_correccion' => 3] as $tabla => $cantidad) {
            $claves = DB::select('PRAGMA foreign_key_list('.$tabla.')');
            $this->assertCount($cantidad, $claves);
            foreach ($claves as $clave) {
                $this->assertSame('RESTRICT', $clave->on_delete);
            }
        }
    }

    public function test_datos_oficiales_no_se_pueden_suplantar_desde_las_rutas_existentes(): void
    {
        $e = $this->escenario(false);
        $ruta = '/api/partidos/'.$e['partido']['id'];
        foreach (['oficializado_at' => '2000-01-01', 'tipo_resultado' => 'administrativo', 'goles_admin_local' => 3] as $campo => $valor) {
            $this->patchJson($ruta, [$campo => $valor])->assertUnprocessable();
        }
        $this->solicitudArbitral('patch', $ruta, ['estado' => 'en_curso'])->assertOk();
        $this->solicitudArbitral('post', $ruta.'/finalizar', ['oficializado_at' => '2000-01-01'])->assertUnprocessable();
        $this->postJson($ruta.'/finalizar')->assertForbidden();
        $this->assertDatabaseHas('partidos', ['id' => $e['partido']['id'], 'estado' => 'en_curso', 'tipo_resultado' => 'deportivo', 'oficializado_at' => null]);
    }

    public function test_correccion_respeta_dependencia_de_asistencia_y_conserva_los_eventos_retirados(): void
    {
        $e = $this->oficial(true, true);
        $c = $this->aprobar($e);
        $ruta = '/api/correcciones/'.$c['id'].'/aplicar';
        $this->postJson($ruta, ['operaciones' => [['accion' => 'eliminar', 'evento_id' => $e['gol']['id']]]])->assertConflict();
        $this->assertDatabaseCount('eventos_estadisticos', 2);
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $c['id'], 'estado' => 'aprobada']);
        $this->postJson($ruta, ['operaciones' => [
            ['accion' => 'eliminar', 'evento_id' => $e['asistencia']['id']],
            ['accion' => 'eliminar', 'evento_id' => $e['gol']['id']],
        ]])->assertOk();
        $this->assertDatabaseCount('eventos_estadisticos', 0);
        $a = AuditoriaCambio::where('accion', 'aplicar_correccion')->sole();
        $this->assertCount(2, $a->anterior_json['eventos']);
        $this->assertSame([], $a->posterior_json['eventos']);
    }
}
