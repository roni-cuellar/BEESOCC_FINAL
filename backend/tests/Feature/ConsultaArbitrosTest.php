<?php

namespace Tests\Feature;

use App\Models\AsignacionArbitro;
use App\Models\Campeonato;
use App\Models\User;

class ConsultaArbitrosTest extends BackendTestCase
{
    public function test_mis_arbitrajes_incluye_formato_para_navegar_al_cuadro_privado(): void
    {
        $liga = $this->postJson('/api/campeonatos', ['nombre' => 'Copa eliminatoria', 'temporada' => '2026', 'formato' => 'eliminacion_directa'])
            ->assertCreated()->json('campeonato');
        $arbitro = User::factory()->create();
        $this->postJson('/api/campeonatos/'.$liga['id'].'/arbitro', ['usuario_id' => $arbitro->id])->assertCreated();
        app('auth')->forgetGuards();
        $this->actingAs($arbitro, 'web');
        $this->getJson('/api/mis-arbitrajes')->assertOk()
            ->assertJsonPath('asignaciones.0.campeonato.formato', 'eliminacion_directa')
            ->assertJsonMissingPath('asignaciones.0.campeonato.administrador_id');
        $this->getJson('/api/campeonatos/'.$liga['id'])->assertForbidden();
    }

    public function test_busqueda_exacta_privada_solo_devuelve_identidad_minima(): void
    {
        $liga = $this->campeonato();
        $usuario = User::factory()->create(['email' => 'arbitro@example.test']);
        $ruta = '/api/campeonatos/'.$liga['id'].'/arbitro/buscar-usuario';
        $this->postJson($ruta, ['email' => $usuario->email])->assertOk()
            ->assertExactJson(['usuario' => ['id' => $usuario->id, 'name' => $usuario->name]])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson($ruta, ['email' => 'otro@example.test'])->assertOk()->assertJsonPath('usuario', null);
        $this->postJson($ruta, ['email' => auth('web')->user()->email])->assertOk()->assertJsonPath('usuario', null);
        $this->postJson($ruta, ['email' => 'arbitro'])->assertUnprocessable();
        $this->postJson($ruta, [])->assertUnprocessable();
        $this->assertDatabaseCount('asignaciones_arbitro', 0);
    }

    public function test_busqueda_requiere_propietario_y_autenticacion(): void
    {
        $liga = $this->campeonato();
        $ruta = '/api/campeonatos/'.$liga['id'].'/arbitro/buscar-usuario';
        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson($ruta, ['email' => 'otro@example.test'])->assertForbidden();
        Campeonato::findOrFail($liga['id'])->forceFill(['administrador_id' => null])->save();
        $this->postJson($ruta, ['email' => 'otro@example.test'])->assertForbidden();
        auth('web')->logout();
        app('auth')->forgetGuards();
        $this->postJson($ruta, ['email' => 'otro@example.test'])->assertUnauthorized();
        $this->getJson('/api/mis-arbitrajes')->assertUnauthorized();
    }

    public function test_busqueda_limita_intentos(): void
    {
        $liga = $this->campeonato();
        $ruta = '/api/campeonatos/'.$liga['id'].'/arbitro/buscar-usuario';
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($ruta, ['email' => 'otro@example.test'])->assertOk();
        }
        $this->postJson($ruta, ['email' => 'otro@example.test'])->assertStatus(429);
    }

    public function test_mis_arbitrajes_filtra_usuario_vigencia_y_propiedad_sin_conceder_administracion(): void
    {
        $propietario = auth('web')->user();
        $liga = $this->campeonato();
        $otra = $this->campeonato('Otra liga');
        $arbitro = User::factory()->create();
        $segundo = User::factory()->create();
        $ruta = '/api/campeonatos/'.$liga['id'].'/arbitro';
        $this->postJson($ruta, ['usuario_id' => $arbitro->id])->assertCreated();
        $this->postJson('/api/campeonatos/'.$otra['id'].'/arbitro', ['usuario_id' => $segundo->id])->assertCreated();
        app('auth')->forgetGuards();
        $this->actingAs($arbitro, 'web');
        $this->getJson('/api/mis-arbitrajes')->assertOk()->assertJsonCount(1, 'asignaciones')
            ->assertJsonPath('asignaciones.0.campeonato.id', $liga['id'])
            ->assertJsonMissingPath('asignaciones.0.usuario')->assertJsonMissingPath('asignaciones.0.autorizado_por')
            ->assertJsonMissingPath('asignaciones.0.campeonato.administrador_id');
        $this->getJson('/api/campeonatos/'.$liga['id'])->assertForbidden();
        $this->postJson($ruta, ['usuario_id' => $segundo->id])->assertForbidden();
        $asignacion = AsignacionArbitro::where('campeonato_id', $liga['id'])->firstOrFail();
        $asignacion->update(['fecha_inicio' => now()->addDay()]);
        $this->getJson('/api/mis-arbitrajes')->assertOk()->assertJsonCount(0, 'asignaciones');
        $asignacion->update(['fecha_inicio' => now()->subDay()]);
        Campeonato::findOrFail($liga['id'])->forceFill(['administrador_id' => null])->save();
        $this->getJson('/api/mis-arbitrajes')->assertOk()->assertJsonCount(0, 'asignaciones');
        Campeonato::findOrFail($liga['id'])->forceFill(['administrador_id' => $propietario->id])->save();
        app('auth')->forgetGuards();
        $this->actingAs($propietario, 'web');
        $this->deleteJson($ruta, ['motivo_revocacion' => 'Cambio de disponibilidad'])->assertOk();
        app('auth')->forgetGuards();
        $this->actingAs($arbitro, 'web');
        $this->getJson('/api/mis-arbitrajes')->assertOk()->assertJsonCount(0, 'asignaciones');
        $this->assertDatabaseCount('asignaciones_arbitro', 2);
    }
}
