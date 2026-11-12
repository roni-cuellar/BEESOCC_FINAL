<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CampeonatoPropiedadTest extends BackendTestCase
{
    private function cambiarUsuario(User $usuario): void
    {
        app('auth')->forgetGuards();
        $this->actingAs($usuario, 'web');
    }

    private function datos(): array
    {
        return ['nombre' => 'Copa SENA', 'temporada' => '2026', 'formato' => 'todos_contra_todos'];
    }

    public function test_creacion_asigna_propietario_y_relaciones(): void
    {
        $usuario = auth('web')->user();
        $campeonato = $this->postJson('/api/campeonatos', $this->datos())->assertCreated()->json('campeonato');
        $this->assertSame($usuario->id, $campeonato['administrador_id']);
        $this->assertTrue(Campeonato::findOrFail($campeonato['id'])->administrador->is($usuario));
        $this->assertSame([$campeonato['id']], $usuario->campeonatos()->pluck('id')->all());
    }

    public function test_cada_usuario_solo_lista_los_propios(): void
    {
        $primero = auth('web')->user();
        $propio = $this->campeonato('Propio');
        $segundo = User::factory()->create();
        $this->cambiarUsuario($segundo);
        $ajeno = $this->campeonato('Segundo');
        $this->getJson('/api/campeonatos')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $ajeno['id']);
        $this->cambiarUsuario($primero);
        $this->getJson('/api/campeonatos')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $propio['id']);
    }

    public function test_usuario_ajeno_no_consulta_modifica_ni_elimina(): void
    {
        $campeonato = $this->campeonato();
        $this->cambiarUsuario(User::factory()->create());
        $ruta = '/api/campeonatos/'.$campeonato['id'];
        $this->getJson($ruta)->assertForbidden();
        $this->patchJson($ruta, ['nombre' => 'Intruso'])->assertForbidden();
        $this->putJson($ruta, $this->datos())->assertForbidden();
        $this->deleteJson($ruta)->assertForbidden();
        $this->assertDatabaseHas('campeonatos', ['id' => $campeonato['id'], 'nombre' => $campeonato['nombre'], 'administrador_id' => $campeonato['administrador_id']]);
    }

    public function test_no_se_puede_falsificar_o_cambiar_administrador_id(): void
    {
        $otro = User::factory()->create();
        $this->postJson('/api/campeonatos', $this->datos() + ['administrador_id' => $otro->id])
            ->assertUnprocessable()->assertJsonValidationErrors('administrador_id');
        $this->assertDatabaseCount('campeonatos', 0);
        $campeonato = $this->campeonato();
        $this->patchJson('/api/campeonatos/'.$campeonato['id'], ['administrador_id' => $otro->id])
            ->assertUnprocessable()->assertJsonValidationErrors('administrador_id');
        $this->patchJson('/api/campeonatos/'.$campeonato['id'], ['administrador_id' => null, 'nombre' => 'Permitido'])
            ->assertOk()->assertJsonPath('campeonato.administrador_id', $campeonato['administrador_id']);
        $this->assertDatabaseHas('campeonatos', ['id' => $campeonato['id'], 'administrador_id' => $campeonato['administrador_id']]);
    }

    public function test_campeonato_sin_propietario_no_se_puede_reclamar(): void
    {
        $antiguo = Campeonato::create($this->datos());
        foreach ([auth('web')->user(), User::factory()->create()] as $usuario) {
            $this->cambiarUsuario($usuario);
            $this->getJson('/api/campeonatos')->assertOk()->assertJsonCount(0);
            $ruta = '/api/campeonatos/'.$antiguo->id;
            $this->getJson($ruta)->assertForbidden();
            $this->patchJson($ruta, ['administrador_id' => $usuario->id])->assertForbidden();
            $this->deleteJson($ruta)->assertForbidden();
        }
        $this->assertDatabaseHas('campeonatos', ['id' => $antiguo->id, 'administrador_id' => null]);
    }

    public function test_invitado_no_accede_al_crud_privado(): void
    {
        $campeonato = $this->campeonato();
        auth('web')->logout();
        app('auth')->forgetGuards();
        $ruta = '/api/campeonatos/'.$campeonato['id'];
        $this->getJson('/api/campeonatos')->assertUnauthorized();
        $this->postJson('/api/campeonatos', $this->datos())->assertUnauthorized();
        $this->getJson($ruta)->assertUnauthorized();
        $this->patchJson($ruta, ['nombre' => 'Intruso'])->assertUnauthorized();
        $this->deleteJson($ruta)->assertUnauthorized();
    }

    public function test_consultas_deportivas_heredadas_conservan_su_acceso_actual(): void
    {
        $campeonato = $this->campeonato();
        auth('web')->logout();
        app('auth')->forgetGuards();
        $this->getJson('/api/campeonatos/'.$campeonato['id'].'/tabla-posiciones')->assertOk();
        $this->getJson('/api/campeonatos/'.$campeonato['id'].'/estadisticas/goleadores')->assertOk();
        $this->getJson('/api/campeonatos/'.$campeonato['id'].'/partidos')->assertOk();
    }

    public function test_migracion_conserva_un_campeonato_preexistente(): void
    {
        // Simula el esquema anterior en otra conexión exclusivamente en memoria.
        config(['database.connections.legado' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $anterior = DB::getDefaultConnection();
        try {
            DB::setDefaultConnection('legado');
            (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
            (require database_path('migrations/2026_10_25_000000_create_campeonatos_table.php'))->up();
            $datos = $this->datos() + ['estado' => 'planificacion', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
            $id = DB::table('campeonatos')->insertGetId($datos);
            (require database_path('migrations/2026_11_12_235000_add_administrador_id_to_campeonatos_table.php'))->up();
            $fila = (array) DB::table('campeonatos')->find($id);
            $this->assertSame($datos, array_intersect_key($fila, $datos));
            $this->assertNull($fila['administrador_id']);
            $this->assertSame(1, DB::table('campeonatos')->count());
        } finally {
            DB::setDefaultConnection($anterior);
            DB::purge('legado');
        }
    }

    public function test_la_llave_foranea_impide_propietarios_inexistentes(): void
    {
        $campeonato = $this->campeonato();
        $this->expectException(QueryException::class);
        DB::table('campeonatos')->where('id', $campeonato['id'])->update(['administrador_id' => 999999]);
    }

    public function test_user_id_tampoco_permite_elegir_o_cambiar_propietario(): void
    {
        $otro = User::factory()->create();
        $this->postJson('/api/campeonatos', $this->datos() + ['user_id' => $otro->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $campeonato = $this->campeonato();
        $this->patchJson('/api/campeonatos/'.$campeonato['id'], ['user_id' => $otro->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertDatabaseHas('campeonatos', ['id' => $campeonato['id'], 'administrador_id' => $campeonato['administrador_id']]);
    }

    public function test_seleccionar_un_perfil_no_concede_propiedad_ajena(): void
    {
        $campeonato = $this->campeonato();
        $this->cambiarUsuario(User::factory()->create());
        $this->patchJson('/api/campeonatos/'.$campeonato['id'], ['perfil' => 'administrador', 'nombre' => 'Intruso'])
            ->assertForbidden();
        $this->assertDatabaseHas('campeonatos', ['id' => $campeonato['id'], 'nombre' => $campeonato['nombre']]);
    }
}
