<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublicacionCampeonatoTest extends BackendTestCase
{
    private function rutasPublicas(array $escenario): array
    {
        $base = '/api/publico/campeonatos/'.$escenario['campeonato']['id'];

        return [
            $base, $base.'/equipos', $base.'/equipos/'.$escenario['local']['id'].'/plantilla',
            $base.'/partidos', $base.'/partidos/'.$escenario['partido']['id'],
            $base.'/partidos/'.$escenario['partido']['id'].'/eventos', $base.'/tabla',
            ...array_map(fn ($tipo) => $base.'/estadisticas/'.$tipo, ['goleadores', 'asistencias', 'amarillas', 'rojas', 'faltas', 'equipos']),
        ];
    }

    private function rutasPrivadas(array $escenario): array
    {
        $liga = '/api/campeonatos/'.$escenario['campeonato']['id'];
        $partido = '/api/partidos/'.$escenario['partido']['id'];

        return [
            $liga, $liga.'/inscripciones-equipos', $liga.'/partidos', $liga.'/configuracion',
            $liga.'/tabla-posiciones', $liga.'/estadisticas/goleadores', $liga.'/estadisticas/equipos',
            '/api/equipos/'.$escenario['local']['equipo_id'],
            '/api/jugadores/'.$escenario['plantillaLocal']['jugador_id'],
            '/api/inscripciones-equipos/'.$escenario['local']['id'],
            '/api/inscripciones-equipos/'.$escenario['local']['id'].'/plantillas',
            '/api/plantillas/'.$escenario['plantillaLocal']['id'],
            $partido, $partido.'/marcador', $partido.'/eventos',
        ];
    }

    private function invitado(): void
    {
        auth('web')->logout();
        app('auth')->forgetGuards();
    }

    private function comprobarCampos(array $datos): void
    {
        foreach ($datos as $campo => $valor) {
            $this->assertNotContains($campo, [
                'administrador_id', 'user_id', 'email', 'password', 'remember_token',
                'fecha_nacimiento', 'foto', 'created_at', 'updated_at', 'fecha_ingreso',
                'fecha_salida', 'campeonato_id', 'campeonato_equipo_id', 'plantilla_id',
                'equipo_partido_id', 'inicio_inscripciones', 'inicio_transferencias',
            ]);
            if (is_array($valor)) {
                $this->comprobarCampos($valor);
            }
        }
    }

    public function test_campeonato_nuevo_privado_y_publicacion_solo_por_endpoint_autorizado(): void
    {
        $liga = $this->campeonato();
        $this->assertFalse($liga['publicado']);
        $datos = ['nombre' => 'Intento', 'temporada' => '2026', 'formato' => 'todos_contra_todos', 'publicado' => true];
        $this->postJson('/api/campeonatos', $datos)->assertUnprocessable()->assertJsonValidationErrors('publicado');
        $this->patchJson('/api/campeonatos/'.$liga['id'], ['publicado' => true])->assertUnprocessable();
        $this->patchJson('/api/campeonatos/'.$liga['id'].'/publicacion', ['publicado' => true])
            ->assertOk()->assertJsonPath('campeonato.publicado', true);
        $this->assertDatabaseHas('campeonatos', ['id' => $liga['id'], 'publicado' => true]);
    }

    public function test_usuario_ajeno_e_invitado_no_publican_ni_ocultan(): void
    {
        $liga = $this->campeonato();
        $this->patchJson('/api/campeonatos/'.$liga['id'].'/publicacion', ['publicado' => true])->assertOk();
        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        foreach ([true, false] as $estado) {
            $this->patchJson('/api/campeonatos/'.$liga['id'].'/publicacion', ['publicado' => $estado])->assertForbidden();
        }
        $this->invitado();
        $this->patchJson('/api/campeonatos/'.$liga['id'].'/publicacion', ['publicado' => false])->assertUnauthorized();
        $this->assertDatabaseHas('campeonatos', ['id' => $liga['id'], 'publicado' => true]);
    }

    public function test_legado_sin_dueno_no_se_publica_ni_aparece_aunque_tenga_bandera_incoherente(): void
    {
        $liga = $this->campeonato();
        DB::table('campeonatos')->where('id', $liga['id'])->update(['administrador_id' => null]);
        $this->patchJson('/api/campeonatos/'.$liga['id'].'/publicacion', ['publicado' => true])->assertForbidden();
        DB::table('campeonatos')->where('id', $liga['id'])->update(['publicado' => true]);
        $this->invitado();
        $this->getJson('/api/publico/campeonatos')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/publico/campeonatos/'.$liga['id'])->assertNotFound();
    }

    public function test_campeonato_privado_no_admite_consultas_publicas_por_id(): void
    {
        $escenario = $this->escenario();
        $this->invitado();
        foreach ($this->rutasPublicas($escenario) as $ruta) {
            $this->getJson($ruta)->assertNotFound();
        }
        $this->getJson('/api/publico/campeonatos')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/publico/campeonatos/no-es-id')->assertNotFound();
    }

    public function test_publicacion_permite_deporte_sin_campos_privados_y_ocultar_revoca_acceso(): void
    {
        $usuario = auth('web')->user();
        $escenario = $this->escenario();
        $this->evento($escenario['partido'], $this->participante($escenario['partido'], 'local'), $escenario['plantillaLocal']['id']);
        $ruta = '/api/campeonatos/'.$escenario['campeonato']['id'].'/publicacion';
        $this->patchJson($ruta, ['publicado' => true])->assertOk();
        $this->invitado();
        foreach ($this->rutasPublicas($escenario) as $publica) {
            $respuesta = $this->getJson($publica)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->comprobarCampos($respuesta->json());
        }
        $this->getJson('/api/publico/campeonatos')->assertOk()->assertJsonCount(1);
        $base = '/api/publico/campeonatos/'.$escenario['campeonato']['id'];
        $this->getJson($base.'/equipos/'.$escenario['local']['id'].'/plantilla')->assertJsonPath('0.jugador.nombre', 'Carlos');
        $this->getJson($base.'/estadisticas/goleadores')->assertJsonPath('resultados.0.total', 1);
        app('auth')->forgetGuards();
        $this->actingAs($usuario, 'web');
        $this->patchJson($ruta, ['publicado' => false])->assertOk()->assertJsonPath('campeonato.publicado', false);
        foreach ($this->rutasPublicas($escenario) as $publica) {
            $this->getJson($publica)->assertNotFound();
        }
        $this->getJson('/api/campeonatos/'.$escenario['campeonato']['id'])->assertOk();
    }

    public function test_ids_de_otro_campeonato_no_entran_en_un_campeonato_publicado(): void
    {
        $publico = $this->escenario();
        $privado = $this->escenario();
        $this->patchJson('/api/campeonatos/'.$publico['campeonato']['id'].'/publicacion', ['publicado' => true])->assertOk();
        $this->invitado();
        $base = '/api/publico/campeonatos/'.$publico['campeonato']['id'];
        $this->getJson($base.'/equipos/'.$privado['local']['id'].'/plantilla')->assertNotFound();
        $this->getJson($base.'/partidos/'.$privado['partido']['id'])->assertNotFound();
        $this->getJson($base.'/partidos/'.$privado['partido']['id'].'/eventos')->assertNotFound();
        $this->getJson($base.'/estadisticas/metodo-invalido')->assertNotFound();
    }

    public function test_admin_consulta_sus_recursos_y_ajeno_no_accede_a_get_privados(): void
    {
        $escenario = $this->escenario();
        $evento = $this->evento($escenario['partido'], $this->participante($escenario['partido'], 'local'), $escenario['plantillaLocal']['id']);
        $rutas = [...$this->rutasPrivadas($escenario), '/api/eventos/'.$evento['id']];
        foreach ($rutas as $ruta) {
            $this->getJson($ruta)->assertOk();
        }
        $this->patchJson('/api/campeonatos/'.$escenario['campeonato']['id'].'/publicacion', ['publicado' => true])->assertOk();
        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        foreach ($rutas as $ruta) {
            $this->getJson($ruta)->assertForbidden();
        }
        $this->getJson('/api/equipos')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/jugadores')->assertOk()->assertJsonCount(0);
        $this->invitado();
        foreach ($rutas as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
    }

    public function test_visitante_no_puede_escribir_por_rutas_publicas(): void
    {
        $liga = $this->campeonato();
        $this->invitado();
        foreach (['postJson', 'patchJson', 'deleteJson'] as $metodo) {
            $this->{$metodo}('/api/publico/campeonatos/'.$liga['id'], [])->assertStatus(405);
        }
        $this->getJson('/api/publico/campeonatos/'.$liga['id'])->assertNotFound();
    }

    public function test_relacion_incoherente_no_filtra_catalogo_privado_en_respuesta_publica(): void
    {
        $escenario = $this->escenario();
        $this->patchJson('/api/campeonatos/'.$escenario['campeonato']['id'].'/publicacion', ['publicado' => true])->assertOk();
        $otro = User::factory()->create();
        DB::table('equipos')->where('id', $escenario['local']['equipo_id'])->update(['administrador_id' => $otro->id, 'nombre' => 'Dato privado ajeno']);
        $this->invitado();
        $base = '/api/publico/campeonatos/'.$escenario['campeonato']['id'];
        foreach (['/equipos', '/partidos', '/tabla', '/estadisticas/equipos'] as $ruta) {
            $respuesta = $this->getJson($base.$ruta)->assertNotFound();
            $this->assertStringNotContainsString('Dato privado ajeno', $respuesta->getContent());
        }
    }

    public function test_migracion_deja_campeonato_anterior_privado_sin_cambiar_sus_valores(): void
    {
        config(['database.connections.publicacion_legado' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $anterior = DB::getDefaultConnection();
        try {
            DB::setDefaultConnection('publicacion_legado');
            (require database_path('migrations/2026_10_25_000000_create_campeonatos_table.php'))->up();
            $datos = ['nombre' => 'Legado', 'temporada' => '2026', 'formato' => 'todos_contra_todos', 'estado' => 'planificacion'];
            $id = DB::table('campeonatos')->insertGetId($datos);
            (require database_path('migrations/2026_11_12_235200_add_publicado_to_campeonatos_table.php'))->up();
            $fila = (array) DB::table('campeonatos')->find($id);
            $this->assertSame($datos, array_intersect_key($fila, $datos));
            $this->assertEquals(0, $fila['publicado']);
            $this->assertSame(1, DB::table('campeonatos')->count());
        } finally {
            DB::setDefaultConnection($anterior);
            DB::purge('publicacion_legado');
        }
    }

    public function test_publicacion_valida_booleano_sin_modificar_campos_del_campeonato(): void
    {
        $liga = $this->campeonato();
        $ruta = '/api/campeonatos/'.$liga['id'].'/publicacion';
        $this->patchJson($ruta, [])->assertUnprocessable()->assertJsonValidationErrors('publicado');
        $this->patchJson($ruta, ['publicado' => 'publicar'])->assertUnprocessable()->assertJsonValidationErrors('publicado');
        $this->patchJson($ruta, ['publicado' => true, 'nombre' => 'No cambiar', 'administrador_id' => 999])->assertOk();
        $this->assertDatabaseHas('campeonatos', ['id' => $liga['id'], 'nombre' => $liga['nombre'], 'administrador_id' => $liga['administrador_id'], 'publicado' => true]);
    }
}
