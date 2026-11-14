<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AutorizacionDeportivaTest extends BackendTestCase
{
    private function escenarioConEvento(): array
    {
        $escenario = $this->escenario();
        $escenario['evento'] = $this->evento($escenario['partido'], $this->participante($escenario['partido'], 'local'), $escenario['plantillaLocal']['id']);

        return $escenario;
    }

    private function escrituras(array $escenario): array
    {
        $campeonato = $escenario['campeonato']['id'];
        $inscripcion = $escenario['local']['id'];
        $plantilla = $escenario['plantillaLocal']['id'];
        $partido = $escenario['partido']['id'];
        $equipo = $escenario['local']['equipo_id'];
        $jugador = $escenario['plantillaLocal']['jugador_id'];
        $evento = $escenario['evento']['id'];

        return [
            ['patch', "/api/equipos/$equipo"],
            ['put', "/api/equipos/$equipo"],
            ['delete', "/api/equipos/$equipo"],
            ['patch', "/api/jugadores/$jugador"],
            ['put', "/api/jugadores/$jugador"],
            ['delete', "/api/jugadores/$jugador"],
            ['post', "/api/campeonatos/$campeonato/configuracion"],
            ['patch', "/api/campeonatos/$campeonato/configuracion"],
            ['post', "/api/campeonatos/$campeonato/inscripciones-equipos"],
            ['patch', "/api/inscripciones-equipos/$inscripcion"],
            ['delete', "/api/inscripciones-equipos/$inscripcion"],
            ['post', "/api/inscripciones-equipos/$inscripcion/plantillas"],
            ['patch', "/api/plantillas/$plantilla"],
            ['delete', "/api/plantillas/$plantilla"],
            ['post', "/api/campeonatos/$campeonato/partidos"],
            ['post', "/api/campeonatos/$campeonato/generar-calendario"],
            ['patch', "/api/partidos/$partido"],
            ['put', "/api/partidos/$partido"],
            ['delete', "/api/partidos/$partido"],
            ['post', "/api/partidos/$partido/eventos"],
            ['patch', "/api/eventos/$evento"],
            ['delete', "/api/eventos/$evento"],
        ];
    }

    private function comprobarDenegacion(array $rutas, int $estado): void
    {
        $antes = [];
        foreach (['campeonatos', 'equipos', 'jugadores', 'configuracion_campeonato', 'campeonato_equipos', 'plantillas', 'partidos', 'equipo_partido', 'eventos_estadisticos'] as $tabla) {
            $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        }
        foreach ($rutas as [$metodo, $ruta]) {
            $this->{$metodo.'Json'}($ruta, [])->assertStatus($estado);
        }
        foreach ($antes as $tabla => $filas) {
            $this->assertSame($filas, DB::table($tabla)->orderBy('id')->get()->toJson(), $tabla);
        }
    }

    public function test_usuario_ajeno_no_escribe_por_id_directo_o_anidado(): void
    {
        $escenario = $this->escenarioConEvento();
        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        $this->comprobarDenegacion($this->escrituras($escenario), 403);
    }

    public function test_campeonato_sin_propietario_no_admite_escrituras(): void
    {
        $escenario = $this->escenarioConEvento();
        // administrador_id está fuera de fillable: la preparación usa una consulta explícita.
        DB::table('campeonatos')->where('id', $escenario['campeonato']['id'])->update(['administrador_id' => null]);
        $this->comprobarDenegacion($this->escrituras($escenario), 403);
    }

    public function test_invitado_no_escribe_recursos_deportivos(): void
    {
        $escenario = $this->escenarioConEvento();
        auth('web')->logout();
        app('auth')->forgetGuards();
        $rutas = $this->escrituras($escenario);
        $rutas[] = ['post', '/api/equipos'];
        $rutas[] = ['patch', '/api/equipos/'.$escenario['local']['equipo_id']];
        $rutas[] = ['delete', '/api/equipos/'.$escenario['local']['equipo_id']];
        $rutas[] = ['post', '/api/jugadores'];
        $rutas[] = ['patch', '/api/jugadores/'.$escenario['plantillaLocal']['jugador_id']];
        $rutas[] = ['delete', '/api/jugadores/'.$escenario['plantillaLocal']['jugador_id']];
        $rutas[] = ['post', '/api/partidos/'.$escenario['partido']['id'].'/eventos'];
        $this->comprobarDenegacion($rutas, 401);
    }

    public function test_inscripciones_de_otra_liga_del_mismo_usuario_no_entran_en_un_partido(): void
    {
        $escenario = $this->escenario(false);
        $otro = $this->campeonato('Otra liga propia');
        $inscripcion = $this->inscripcion($otro['id']);
        $datos = ['jornada' => 2, 'local_id' => $escenario['local']['id'], 'visitante_id' => $inscripcion['id']];
        $this->postJson('/api/campeonatos/'.$escenario['campeonato']['id'].'/partidos', $datos)->assertUnprocessable();
        $this->patchJson('/api/partidos/'.$escenario['partido']['id'], $datos)->assertUnprocessable();
        $this->assertDatabaseCount('partidos', 1);
    }

    public function test_catalogos_nuevos_reciben_el_propietario_y_se_pueden_administrar_sin_inscripciones(): void
    {
        $usuario = auth('web')->user();
        $equipo = $this->equipo();
        $jugador = $this->jugador();
        $this->assertSame($usuario->id, $equipo['administrador_id']);
        $this->assertSame($usuario->id, $jugador['administrador_id']);
        $this->assertTrue(Equipo::findOrFail($equipo['id'])->administrador->is($usuario));
        $this->assertTrue(Jugador::findOrFail($jugador['id'])->administrador->is($usuario));
        $this->assertSame(1, $usuario->equipos()->count());
        $this->assertSame(1, $usuario->jugadores()->count());
        $this->patchJson('/api/equipos/'.$equipo['id'], ['nombre' => 'Propio actualizado'])->assertOk();
        $this->patchJson('/api/jugadores/'.$jugador['id'], ['nombre' => 'Propio actualizado'])->assertOk();
        $this->deleteJson('/api/equipos/'.$equipo['id'])->assertOk();
        $this->deleteJson('/api/jugadores/'.$jugador['id'])->assertOk();
    }

    public function test_no_se_pueden_inscribir_catalogos_de_otro_propietario_ni_aunque_estan_sin_vinculos(): void
    {
        $usuario = auth('web')->user();
        $escenario = $this->escenario(false);
        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        $equipo = $this->equipo('Ajeno');
        $jugador = $this->jugador('Ajeno');
        app('auth')->forgetGuards();
        $this->actingAs($usuario, 'web');
        $this->postJson('/api/campeonatos/'.$escenario['campeonato']['id'].'/inscripciones-equipos', ['equipo_id' => $equipo['id']])->assertForbidden();
        $this->postJson('/api/inscripciones-equipos/'.$escenario['local']['id'].'/plantillas', [
            'jugador_id' => $jugador['id'], 'dorsal' => 11, 'posicion' => 'Defensa', 'fecha_ingreso' => '2026-01-01',
        ])->assertForbidden();
        $this->patchJson('/api/equipos/'.$equipo['id'], ['nombre' => 'Intruso'])->assertForbidden();
        $this->deleteJson('/api/equipos/'.$equipo['id'])->assertForbidden();
        $this->patchJson('/api/jugadores/'.$jugador['id'], ['nombre' => 'Intruso'])->assertForbidden();
        $this->deleteJson('/api/jugadores/'.$jugador['id'])->assertForbidden();
        $this->assertDatabaseCount('campeonato_equipos', 2);
        $this->assertDatabaseCount('plantillas', 2);
    }

    public function test_catalogos_legados_sin_dueno_no_se_pueden_editar_borrar_o_reclamar(): void
    {
        $escenario = $this->escenario(false);
        $equipo = Equipo::create(['nombre' => 'Legado', 'ciudad' => 'Bogotá']);
        $jugador = Jugador::create(['nombre' => 'Legado', 'apellido' => 'SENA', 'fecha_nacimiento' => '2000-01-01']);
        foreach (['equipos' => $equipo, 'jugadores' => $jugador] as $tabla => $recurso) {
            $this->patchJson('/api/'.$tabla.'/'.$recurso->id, ['nombre' => 'Intruso', 'administrador_id' => auth('web')->id()])->assertForbidden();
            $this->deleteJson('/api/'.$tabla.'/'.$recurso->id)->assertForbidden();
            $this->assertDatabaseHas($tabla, ['id' => $recurso->id, 'nombre' => 'Legado', 'administrador_id' => null]);
        }
        $this->postJson('/api/campeonatos/'.$escenario['campeonato']['id'].'/inscripciones-equipos', ['equipo_id' => $equipo->id])->assertForbidden();
        $this->postJson('/api/inscripciones-equipos/'.$escenario['local']['id'].'/plantillas', [
            'jugador_id' => $jugador->id, 'dorsal' => 11, 'posicion' => 'Defensa', 'fecha_ingreso' => '2026-01-01',
        ])->assertForbidden();
    }

    public function test_el_cliente_no_puede_elegir_ni_cambiar_dueno_de_catalogos(): void
    {
        $otro = User::factory()->create();
        $equipo = $this->equipo();
        $jugador = $this->jugador();
        foreach (['administrador_id', 'user_id'] as $campo) {
            $this->postJson('/api/equipos', ['nombre' => 'Falso', 'ciudad' => 'Bogotá', $campo => $otro->id])->assertUnprocessable()->assertJsonValidationErrors($campo);
            $this->postJson('/api/jugadores', ['nombre' => 'Falso', 'apellido' => 'SENA', 'fecha_nacimiento' => '2000-01-01', $campo => $otro->id])->assertUnprocessable()->assertJsonValidationErrors($campo);
            $this->patchJson('/api/equipos/'.$equipo['id'], [$campo => $otro->id])->assertUnprocessable()->assertJsonValidationErrors($campo);
            $this->patchJson('/api/jugadores/'.$jugador['id'], [$campo => $otro->id])->assertUnprocessable()->assertJsonValidationErrors($campo);
        }
        $this->assertDatabaseCount('equipos', 1);
        $this->assertDatabaseCount('jugadores', 1);
    }

    public function test_plantilla_de_otra_liga_no_se_puede_usar_en_un_evento(): void
    {
        $primero = $this->escenario();
        $segundo = $this->escenario();
        $this->solicitudArbitral('post', '/api/partidos/'.$primero['partido']['id'].'/eventos', [
            'equipo_partido_id' => $this->participante($primero['partido'], 'local'),
            'plantilla_id' => $segundo['plantillaLocal']['id'], 'tipo' => 'gol', 'minuto' => 10,
        ])->assertUnprocessable();
        $this->solicitudArbitral('post', '/api/partidos/'.$primero['partido']['id'].'/eventos', [
            'equipo_partido_id' => $this->participante($segundo['partido'], 'local'),
            'plantilla_id' => $primero['plantillaLocal']['id'], 'tipo' => 'gol', 'minuto' => 10,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('eventos_estadisticos', 0);
    }

    public function test_relaciones_heredadas_incoherentes_no_dan_permiso_para_escribir(): void
    {
        $escenario = $this->escenarioConEvento();
        $otro = User::factory()->create();
        DB::table('jugadores')->where('id', $escenario['plantillaLocal']['jugador_id'])->update(['administrador_id' => $otro->id]);
        $this->patchJson('/api/plantillas/'.$escenario['plantillaLocal']['id'], ['posicion' => 'Defensa'])->assertForbidden();
        $this->patchJson('/api/eventos/'.$escenario['evento']['id'], ['minuto' => 20])->assertForbidden();
        $this->deleteJson('/api/eventos/'.$escenario['evento']['id'])->assertForbidden();
        DB::table('equipos')->where('id', $escenario['local']['equipo_id'])->update(['administrador_id' => null]);
        $this->patchJson('/api/inscripciones-equipos/'.$escenario['local']['id'], ['estado' => 'retirado'])->assertForbidden();
        $this->patchJson('/api/partidos/'.$escenario['partido']['id'], ['lugar' => 'Otra sede'])->assertForbidden();
    }

    public function test_migracion_de_catalogos_conserva_datos_y_deja_legado_sin_dueno(): void
    {
        config(['database.connections.catalogos_legados' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $anterior = DB::getDefaultConnection();
        try {
            DB::setDefaultConnection('catalogos_legados');
            foreach (['0001_01_01_000000_create_users_table', '2026_10_25_000001_create_equipos_table', '2026_10_25_000002_create_jugadores_table'] as $migracion) {
                (require database_path('migrations/'.$migracion.'.php'))->up();
            }
            $equipo = ['nombre' => 'Equipo legado', 'ciudad' => 'Bogotá', 'escudo' => null];
            $jugador = ['nombre' => 'Jugador', 'apellido' => 'Legado', 'fecha_nacimiento' => '2000-01-01', 'foto' => null];
            DB::table('equipos')->insert($equipo);
            DB::table('jugadores')->insert($jugador);
            (require database_path('migrations/2026_11_12_235100_add_administrador_id_to_equipos_and_jugadores.php'))->up();
            foreach (['equipos' => $equipo, 'jugadores' => $jugador] as $tabla => $datos) {
                $fila = (array) DB::table($tabla)->first();
                $this->assertSame($datos, array_intersect_key($fila, $datos));
                $this->assertNull($fila['administrador_id']);
                $this->assertSame(1, DB::table($tabla)->count());
            }
        } finally {
            DB::setDefaultConnection($anterior);
            DB::purge('catalogos_legados');
        }
    }

    public function test_todas_las_rutas_de_escritura_deportiva_exigen_sanctum(): void
    {
        $cantidad = 0;
        foreach (app('router')->getRoutes() as $ruta) {
            if (! preg_match('#^api/(campeonatos|equipos|jugadores|inscripciones-equipos|plantillas|partidos|eventos)(/|$)#', $ruta->uri())
                || ! array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $ruta->methods())) {
                continue;
            }
            $this->assertContains('auth:sanctum', $ruta->gatherMiddleware(), $ruta->uri());
            $cantidad++;
        }
        $this->assertGreaterThan(20, $cantidad);
    }

    public function test_consultas_administrativas_exigen_sesion(): void
    {
        $escenario = $this->escenarioConEvento();
        auth('web')->logout();
        app('auth')->forgetGuards();
        foreach ([
            '/api/equipos', '/api/equipos/'.$escenario['local']['equipo_id'],
            '/api/jugadores', '/api/jugadores/'.$escenario['plantillaLocal']['jugador_id'],
            '/api/inscripciones-equipos/'.$escenario['local']['id'],
            '/api/plantillas/'.$escenario['plantillaLocal']['id'],
            '/api/partidos/'.$escenario['partido']['id'],
            '/api/eventos/'.$escenario['evento']['id'],
            '/api/campeonatos/'.$escenario['campeonato']['id'].'/configuracion',
        ] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
        $this->getJson('/api/publico/campeonatos')->assertOk()->assertJsonCount(0);
    }

    public function test_equipo_y_jugador_se_reutilizan_entre_ligas_solo_del_mismo_propietario(): void
    {
        $primero = $this->campeonato('Liga uno');
        $segundo = $this->campeonato('Liga dos');
        $inscripcionUno = $this->inscripcion($primero['id']);
        $jugador = $this->jugador();
        $this->plantilla($inscripcionUno['id'], $jugador['id']);
        $inscripcionDos = $this->postJson('/api/campeonatos/'.$segundo['id'].'/inscripciones-equipos', [
            'equipo_id' => $inscripcionUno['equipo_id'],
        ])->assertCreated()->json('inscripcion');
        $this->plantilla($inscripcionDos['id'], $jugador['id']);
        $this->assertDatabaseCount('equipos', 1);
        $this->assertDatabaseCount('jugadores', 1);
        $this->assertDatabaseCount('plantillas', 2);

        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        $ajeno = $this->campeonato('Otra cuenta');
        $propia = $this->inscripcion($ajeno['id']);
        $this->postJson('/api/campeonatos/'.$ajeno['id'].'/inscripciones-equipos', [
            'equipo_id' => $inscripcionUno['equipo_id'],
        ])->assertForbidden();
        $this->postJson('/api/inscripciones-equipos/'.$propia['id'].'/plantillas', [
            'jugador_id' => $jugador['id'], 'dorsal' => 10,
            'posicion' => 'Defensa', 'fecha_ingreso' => '2026-01-01',
        ])->assertForbidden();
        $this->assertDatabaseCount('plantillas', 2);
    }
}
