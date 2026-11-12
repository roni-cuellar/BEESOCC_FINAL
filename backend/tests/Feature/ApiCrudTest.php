<?php

namespace Tests\Feature;

class ApiCrudTest extends BackendTestCase
{
    public function test_campeonato_se_crea_consulta_actualiza_y_elimina(): void
    {
        $campeonato = $this->campeonato();
        $ruta = '/api/campeonatos/'.$campeonato['id'];
        $this->assertSame('planificacion', $campeonato['estado']);
        $this->getJson($ruta)->assertOk()->assertJsonPath('id', $campeonato['id']);
        $this->patchJson($ruta, ['nombre' => 'Copa actualizada', 'estado' => 'finalizado'])
            ->assertOk()->assertJsonPath('campeonato.nombre', 'Copa actualizada')
            ->assertJsonPath('campeonato.estado', 'planificacion');
        $this->getJson('/api/campeonatos')->assertOk()->assertJsonCount(1);
        $this->deleteJson($ruta)->assertOk();
        $this->assertDatabaseMissing('campeonatos', ['id' => $campeonato['id']]);
        $this->getJson($ruta)->assertNotFound();
    }

    public function test_campeonatos_rechazan_datos_invalidos(): void
    {
        $this->postJson('/api/campeonatos', ['nombre' => '', 'temporada' => '', 'formato' => 'eliminacion'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'temporada', 'formato']);
        $this->assertDatabaseCount('campeonatos', 0);
    }

    public function test_no_se_elimina_campeonato_con_inscripciones_o_partidos(): void
    {
        $escenario = $this->escenario(false);
        $id = $escenario['campeonato']['id'];
        $this->deleteJson('/api/campeonatos/'.$id)->assertConflict();
        $this->assertDatabaseHas('campeonatos', ['id' => $id]);
        $this->assertDatabaseCount('partidos', 1);
        $this->assertDatabaseCount('plantillas', 2);
    }

    public function test_equipo_se_crea_consulta_actualiza_y_elimina(): void
    {
        $equipo = $this->equipo();
        $ruta = '/api/equipos/'.$equipo['id'];
        $this->getJson($ruta)->assertOk()->assertJsonPath('nombre', $equipo['nombre']);
        $this->patchJson($ruta, ['ciudad' => 'Cali', 'escudo' => null])
            ->assertOk()->assertJsonPath('equipo.ciudad', 'Cali')->assertJsonPath('equipo.escudo', null);
        $this->getJson('/api/equipos')->assertOk()->assertJsonCount(1);
        $this->deleteJson($ruta)->assertOk();
        $this->assertDatabaseMissing('equipos', ['id' => $equipo['id']]);
        $this->getJson($ruta)->assertNotFound();
    }

    public function test_jugador_se_crea_consulta_actualiza_y_elimina(): void
    {
        $jugador = $this->jugador();
        $ruta = '/api/jugadores/'.$jugador['id'];
        $this->getJson($ruta)->assertOk()->assertJsonPath('apellido', 'Pérez');
        $this->patchJson($ruta, ['nombre' => 'Andrés', 'foto' => null])
            ->assertOk()->assertJsonPath('jugador.nombre', 'Andrés')->assertJsonPath('jugador.foto', null);
        $this->getJson('/api/jugadores')->assertOk()->assertJsonCount(1);
        $this->deleteJson($ruta)->assertOk();
        $this->assertDatabaseMissing('jugadores', ['id' => $jugador['id']]);
        $this->getJson($ruta)->assertNotFound();
    }

    public function test_equipos_y_jugadores_rechazan_valores_invalidos(): void
    {
        $this->postJson('/api/equipos', ['nombre' => '', 'ciudad' => str_repeat('a', 101)])
            ->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'ciudad']);
        $this->postJson('/api/jugadores', ['nombre' => '', 'apellido' => '', 'fecha_nacimiento' => '2999-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'apellido', 'fecha_nacimiento']);
        $this->assertDatabaseCount('equipos', 0);
        $this->assertDatabaseCount('jugadores', 0);
    }

    public function test_no_se_eliminan_equipos_y_jugadores_inscritos(): void
    {
        $escenario = $this->escenario(false);
        $this->deleteJson('/api/equipos/'.$escenario['local']['equipo_id'])->assertConflict();
        $this->deleteJson('/api/jugadores/'.$escenario['plantillaLocal']['jugador_id'])->assertConflict();
        $this->assertDatabaseCount('equipos', 2);
        $this->assertDatabaseCount('jugadores', 2);
    }

    public function test_configuracion_predeterminada_no_se_guarda_y_patch_sin_configuracion_da_404(): void
    {
        $campeonato = $this->campeonato();
        $ruta = '/api/campeonatos/'.$campeonato['id'].'/configuracion';
        $this->getJson($ruta)->assertOk()->assertJsonPath('guardada', false)->assertJsonPath('configuracion.numero_vueltas', 1);
        $this->assertDatabaseCount('configuracion_campeonato', 0);
        $this->patchJson($ruta, ['numero_vueltas' => 2])->assertNotFound();
    }

    public function test_configuracion_se_crea_consulta_actualiza_y_no_se_duplica(): void
    {
        $campeonato = $this->campeonato();
        $ruta = '/api/campeonatos/'.$campeonato['id'].'/configuracion';
        $datos = ['numero_vueltas' => 1, 'orden_desempate' => 'puntos,diferencia_goles,goles_favor',
            'inicio_inscripciones' => '2026-01-01', 'fin_inscripciones' => '2026-01-10'];
        $this->postJson($ruta, $datos)->assertCreated();
        $this->getJson($ruta)->assertOk()->assertJsonPath('guardada', true);
        $this->postJson($ruta, $datos)->assertConflict();
        $this->patchJson($ruta, ['numero_vueltas' => 2, 'fin_inscripciones' => null])
            ->assertOk()->assertJsonPath('configuracion.numero_vueltas', 2)->assertJsonPath('configuracion.fin_inscripciones', null);
        $this->assertDatabaseCount('configuracion_campeonato', 1);
    }

    public function test_configuracion_valida_vueltas_orden_y_rangos_de_fechas(): void
    {
        $campeonato = $this->campeonato();
        $ruta = '/api/campeonatos/'.$campeonato['id'].'/configuracion';
        $datos = ['numero_vueltas' => 1, 'orden_desempate' => 'puntos,diferencia_goles,goles_favor'];
        $this->postJson($ruta, array_merge($datos, ['numero_vueltas' => 3, 'orden_desempate' => 'puntos']))
            ->assertUnprocessable()->assertJsonValidationErrors(['numero_vueltas', 'orden_desempate']);
        $this->postJson($ruta, array_merge($datos, ['inicio_transferencias' => '2026-02-10', 'fin_transferencias' => '2026-02-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('fin_transferencias');
        $this->postJson($ruta, array_merge($datos, ['inicio_inscripciones' => '2026-01-01', 'fin_inscripciones' => '2026-01-10']))->assertCreated();
        $this->patchJson($ruta, ['inicio_inscripciones' => '2026-01-11'])->assertUnprocessable()->assertJsonValidationErrors('fin_inscripciones');
        $this->patchJson($ruta, ['numero_vueltas' => null, 'orden_desempate' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['numero_vueltas', 'orden_desempate']);
    }

    public function test_partidos_impiden_crear_configuracion_o_cambiar_vueltas(): void
    {
        $escenario = $this->escenario(false);
        $this->postJson('/api/campeonatos/'.$escenario['campeonato']['id'].'/configuracion', [
            'numero_vueltas' => 1, 'orden_desempate' => 'puntos,diferencia_goles,goles_favor',
        ])->assertConflict();

        $campeonato = $this->campeonato('Otra copa');
        $ruta = '/api/campeonatos/'.$campeonato['id'].'/configuracion';
        $this->postJson($ruta, ['numero_vueltas' => 1, 'orden_desempate' => 'puntos,diferencia_goles,goles_favor'])->assertCreated();
        $local = $this->inscripcion($campeonato['id']);
        $visitante = $this->inscripcion($campeonato['id']);
        $this->partido($campeonato['id'], $local['id'], $visitante['id']);
        $this->patchJson($ruta, ['numero_vueltas' => 2])->assertConflict();
        $this->patchJson($ruta, ['orden_desempate' => 'puntos,goles_favor,diferencia_goles'])->assertOk();
        $this->assertDatabaseHas('configuracion_campeonato', ['campeonato_id' => $campeonato['id'], 'numero_vueltas' => 1]);
    }
}
