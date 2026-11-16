<?php

namespace Tests\Feature;

use App\Models\AsignacionArbitro;
use App\Models\Campeonato;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AsignacionArbitroTest extends BackendTestCase
{
    private function ruta(int $id): string
    {
        return '/api/campeonatos/'.$id.'/arbitro';
    }

    public function test_propietario_asigna_usuario_registrado_con_relaciones_y_campos_del_servidor(): void
    {
        $actor = auth('web')->user();
        $liga = $this->campeonato();
        $arbitro = User::factory()->create();
        $datos = $this->postJson($this->ruta($liga['id']), ['usuario_id' => $arbitro->id])->assertCreated()
            ->assertJsonPath('asignacion.autorizado_por', $actor->id)
            ->assertJsonPath('asignacion.fecha_fin', null)->assertJsonMissingPath('asignacion.campeonato_activo_id')
            ->json('asignacion');
        $asignacion = AsignacionArbitro::findOrFail($datos['id']);
        $this->assertTrue($asignacion->usuario->is($arbitro));
        $this->assertTrue($asignacion->autorizador->is($actor));
        $this->assertSame($liga['id'], $asignacion->campeonato->id);
        $this->assertSame(1, $arbitro->asignacionesArbitro()->count());
        $this->assertSame(1, $actor->autorizacionesArbitro()->count());
        $this->assertNotNull($asignacion->fecha_inicio);
        $this->assertTrue($arbitro->esArbitroDe($asignacion->campeonato));
    }

    public function test_no_permite_autoasignacion(): void
    {
        $liga = $this->campeonato();
        $this->postJson($this->ruta($liga['id']), ['usuario_id' => auth('web')->id()])
            ->assertUnprocessable()->assertJsonValidationErrors('usuario_id');
        $this->assertDatabaseCount('asignaciones_arbitro', 0);
    }

    public function test_valida_usuario_registrado_y_no_acepta_autoria_fechas_o_campeonato_del_cliente(): void
    {
        $liga = $this->campeonato();
        $ruta = $this->ruta($liga['id']);
        $this->postJson($ruta, [])->assertUnprocessable()->assertJsonValidationErrors('usuario_id');
        $this->postJson($ruta, ['usuario_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('usuario_id');
        $arbitro = User::factory()->create();
        foreach (['campeonato_id' => $liga['id'], 'autorizado_por' => $arbitro->id, 'fecha_inicio' => '2030-01-01',
            'fecha_fin' => '2030-02-01', 'campeonato_activo_id' => $liga['id']] as $campo => $valor) {
            $this->postJson($ruta, ['usuario_id' => $arbitro->id, $campo => $valor])
                ->assertUnprocessable()->assertJsonValidationErrors($campo);
        }
        $this->assertDatabaseCount('asignaciones_arbitro', 0);
    }

    public function test_rechaza_segunda_asignacion_activa_incluso_si_es_el_mismo_usuario(): void
    {
        $liga = $this->campeonato();
        $arbitro = User::factory()->create();
        $this->postJson($this->ruta($liga['id']), ['usuario_id' => $arbitro->id])->assertCreated();
        foreach ([$arbitro, User::factory()->create()] as $usuario) {
            $this->postJson($this->ruta($liga['id']), ['usuario_id' => $usuario->id])->assertConflict();
        }
        $this->assertDatabaseCount('asignaciones_arbitro', 1);
    }

    public function test_reemplazo_revoca_anterior_y_conserva_autoria_e_historial(): void
    {
        $liga = $this->campeonato();
        $primero = User::factory()->create();
        $segundo = User::factory()->create();
        $anterior = $this->postJson($this->ruta($liga['id']), ['usuario_id' => $primero->id])->assertCreated()->json('asignacion');
        $nueva = $this->patchJson($this->ruta($liga['id']), ['usuario_id' => $segundo->id, 'motivo_revocacion' => 'Cambio de disponibilidad.'])
            ->assertOk()->assertJsonPath('asignacion.usuario_id', $segundo->id)->json('asignacion');
        $this->assertNotSame($anterior['id'], $nueva['id']);
        $vieja = AsignacionArbitro::findOrFail($anterior['id']);
        $this->assertNotNull($vieja->fecha_fin);
        $this->assertSame($anterior['autorizado_por'], $vieja->autorizado_por);
        $this->assertSame('Cambio de disponibilidad.', $vieja->motivo_revocacion);
        $campeonato = Campeonato::findOrFail($liga['id']);
        $this->assertFalse($primero->esArbitroDe($campeonato));
        $this->assertTrue($segundo->esArbitroDe($campeonato));
        $this->assertSame(1, $campeonato->asignacionesArbitro()->whereNull('fecha_fin')->count());
        $this->getJson($this->ruta($liga['id']).'/historial')->assertOk()->assertJsonCount(2, 'asignaciones')
            ->assertJsonMissingPath('asignaciones.0.usuario.email')->assertJsonMissingPath('asignaciones.0.usuario.password');
        $this->getJson($this->ruta($liga['id']))->assertOk()->assertJsonPath('asignacion.id', $nueva['id']);
    }

    public function test_fallo_al_crear_reemplazo_restaura_anterior_sin_revocarlo(): void
    {
        $liga = $this->campeonato();
        $arbitro = User::factory()->create();
        $anterior = $this->postJson($this->ruta($liga['id']), ['usuario_id' => $arbitro->id])->assertCreated()->json('asignacion');
        $this->patchJson($this->ruta($liga['id']), ['usuario_id' => auth('web')->id(), 'motivo_revocacion' => 'Intento inválido.'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('asignaciones_arbitro', ['id' => $anterior['id'], 'fecha_fin' => null, 'motivo_revocacion' => null]);
        $this->assertDatabaseCount('asignaciones_arbitro', 1);
    }

    public function test_revocacion_no_borra_y_permite_volver_a_asignar(): void
    {
        $liga = $this->campeonato();
        $ruta = $this->ruta($liga['id']);
        $arbitro = User::factory()->create();
        $this->getJson($ruta)->assertOk()->assertJsonPath('asignacion', null);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson($ruta, ['usuario_id' => $arbitro->id])->assertCreated();
            $this->deleteJson($ruta, ['motivo_revocacion' => 'Fin de disponibilidad.'])->assertOk();
            $this->assertFalse($arbitro->esArbitroDe(Campeonato::findOrFail($liga['id'])));
        }
        $this->assertDatabaseCount('asignaciones_arbitro', 2);
        $this->deleteJson($ruta, ['motivo_revocacion' => 'Duplicado.'])->assertConflict();
        $this->getJson($ruta)->assertOk()->assertJsonPath('asignacion', null);
    }

    public function test_reemplazo_sin_vigente_o_por_mismo_arbitro_es_conflicto_y_motivo_es_obligatorio(): void
    {
        $liga = $this->campeonato();
        $ruta = $this->ruta($liga['id']);
        $arbitro = User::factory()->create();
        $this->patchJson($ruta, ['usuario_id' => $arbitro->id, 'motivo_revocacion' => 'Cambio.'])->assertConflict();
        $this->postJson($ruta, ['usuario_id' => $arbitro->id])->assertCreated();
        $this->patchJson($ruta, ['usuario_id' => $arbitro->id])->assertUnprocessable()->assertJsonValidationErrors('motivo_revocacion');
        $this->patchJson($ruta, ['usuario_id' => $arbitro->id, 'motivo_revocacion' => 'Repetido.'])->assertConflict();
        $this->deleteJson($ruta, [])->assertUnprocessable();
        $this->assertSame(1, AsignacionArbitro::whereNull('fecha_fin')->count());
    }

    public function test_usuario_arbitra_varias_ligas_de_distintos_propietarios_sin_permiso_en_otras(): void
    {
        $arbitro = User::factory()->create();
        $primera = $this->campeonato();
        $otra = $this->campeonato();
        $this->postJson($this->ruta($primera['id']), ['usuario_id' => $arbitro->id])->assertCreated();
        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        $segunda = $this->campeonato();
        $this->postJson($this->ruta($segunda['id']), ['usuario_id' => $arbitro->id])->assertCreated();
        $this->assertTrue($arbitro->esArbitroDe(Campeonato::findOrFail($primera['id'])));
        $this->assertTrue($arbitro->esArbitroDe(Campeonato::findOrFail($segunda['id'])));
        $this->assertFalse($arbitro->esArbitroDe(Campeonato::findOrFail($otra['id'])));
    }

    public function test_ajeno_incluido_arbitro_asignado_no_puede_gestionar_ni_ver_historial(): void
    {
        $liga = $this->campeonato();
        $arbitro = User::factory()->create();
        $this->postJson($this->ruta($liga['id']), ['usuario_id' => $arbitro->id])->assertCreated();
        foreach ([$arbitro, User::factory()->create()] as $usuario) {
            app('auth')->forgetGuards();
            $this->actingAs($usuario, 'web');
            $ruta = $this->ruta($liga['id']);
            $this->getJson($ruta)->assertForbidden();
            $this->getJson($ruta.'/historial')->assertForbidden();
            $this->postJson($ruta, ['usuario_id' => $usuario->id, 'perfil' => 'administrador'])->assertForbidden();
            $this->patchJson($ruta, ['usuario_id' => $usuario->id, 'motivo_revocacion' => 'Sin permiso.'])->assertForbidden();
            $this->deleteJson($ruta, ['motivo_revocacion' => 'Sin permiso.'])->assertForbidden();
        }
        $this->assertSame(1, AsignacionArbitro::whereNull('fecha_fin')->count());
    }

    public function test_invitado_y_campeonato_sin_dueno_quedan_bloqueados(): void
    {
        $liga = $this->campeonato();
        $ruta = $this->ruta($liga['id']);
        DB::table('campeonatos')->where('id', $liga['id'])->update(['administrador_id' => null]);
        foreach (['getJson', 'postJson', 'patchJson', 'deleteJson'] as $metodo) {
            $this->{$metodo}($ruta, [])->assertForbidden();
        }
        $this->getJson($ruta.'/historial')->assertForbidden();
        auth('web')->logout();
        app('auth')->forgetGuards();
        foreach (['getJson', 'postJson', 'patchJson', 'deleteJson'] as $metodo) {
            $this->{$metodo}($ruta, [])->assertUnauthorized();
        }
        $this->getJson($ruta.'/historial')->assertUnauthorized();
        $this->assertDatabaseCount('asignaciones_arbitro', 0);
    }

    public function test_indice_unico_impide_dos_activos_incluso_sin_pasar_por_el_servicio(): void
    {
        $liga = $this->campeonato();
        $arbitro = User::factory()->create();
        $this->postJson($this->ruta($liga['id']), ['usuario_id' => $arbitro->id])->assertCreated();
        try {
            DB::table('asignaciones_arbitro')->insert([
                'campeonato_id' => $liga['id'], 'usuario_id' => $arbitro->id,
                'autorizado_por' => auth('web')->id(), 'fecha_inicio' => now(), 'fecha_fin' => null,
            ]);
            $this->fail('El índice debe rechazar la segunda asignación activa.');
        } catch (QueryException $e) {
            $this->assertSame('23000', (string) $e->getCode());
        }
        $this->assertDatabaseCount('asignaciones_arbitro', 1);
    }

    public function test_historial_arbitral_impide_borrar_campeonato(): void
    {
        $liga = $this->campeonato();
        $this->postJson($this->ruta($liga['id']), ['usuario_id' => User::factory()->create()->id])->assertCreated();
        $this->deleteJson($this->ruta($liga['id']), ['motivo_revocacion' => 'Fin.'])->assertOk();
        $this->deleteJson('/api/campeonatos/'.$liga['id'])->assertConflict();
        $this->assertDatabaseCount('asignaciones_arbitro', 1);
        $this->assertDatabaseHas('campeonatos', ['id' => $liga['id']]);
    }

    public function test_asignacion_habilita_acta_pero_no_organizacion(): void
    {
        $escenario = $this->escenario(false);
        $arbitro = User::factory()->create();
        $this->postJson($this->ruta($escenario['campeonato']['id']), ['usuario_id' => $arbitro->id])->assertCreated();
        Campeonato::findOrFail($escenario['campeonato']['id'])->update(['estado' => 'en_curso']);
        app('auth')->forgetGuards();
        $this->actingAs($arbitro, 'web');
        $this->patchJson('/api/partidos/'.$escenario['partido']['id'], ['estado' => 'en_curso'])->assertOk();
        $this->patchJson('/api/partidos/'.$escenario['partido']['id'], ['lugar' => 'Otra sede'])->assertForbidden();
        $this->postJson('/api/partidos/'.$escenario['partido']['id'].'/eventos', [])->assertUnprocessable();
    }

    public function test_las_tres_claves_foraneas_rechazan_referencias_inexistentes(): void
    {
        $liga = $this->campeonato();
        $datos = ['campeonato_id' => $liga['id'], 'usuario_id' => User::factory()->create()->id,
            'autorizado_por' => auth('web')->id(), 'fecha_inicio' => now()];
        foreach (['campeonato_id', 'usuario_id', 'autorizado_por'] as $campo) {
            try {
                DB::table('asignaciones_arbitro')->insert(array_replace($datos, [$campo => 999999]));
                $this->fail('La clave foránea debe rechazar '.$campo);
            } catch (QueryException $e) {
                $this->assertSame('23000', (string) $e->getCode());
            }
        }
        $this->assertDatabaseCount('asignaciones_arbitro', 0);
    }
}
