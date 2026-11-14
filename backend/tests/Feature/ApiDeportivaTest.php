<?php

namespace Tests\Feature;

class ApiDeportivaTest extends BackendTestCase
{
    public function test_inscripciones_no_se_duplican_y_retirar_conserva_historial(): void
    {
        $campeonato = $this->campeonato();
        $inscripcion = $this->inscripcion($campeonato['id']);
        $ruta = '/api/inscripciones-equipos/'.$inscripcion['id'];
        $this->postJson('/api/campeonatos/'.$campeonato['id'].'/inscripciones-equipos', ['equipo_id' => $inscripcion['equipo_id']])->assertConflict();
        $this->getJson($ruta)->assertOk()->assertJsonPath('equipo.id', $inscripcion['equipo_id']);
        $this->plantilla($inscripcion['id']);
        $this->patchJson($ruta, ['estado' => 'retirado'])->assertOk();
        $this->deleteJson($ruta)->assertConflict();
        $this->assertDatabaseHas('campeonato_equipos', ['id' => $inscripcion['id'], 'estado' => 'retirado']);
        $this->assertDatabaseCount('plantillas', 1);
        $otro = $this->campeonato('Otro campeonato');
        $this->postJson('/api/campeonatos/'.$otro['id'].'/inscripciones-equipos', ['equipo_id' => $inscripcion['equipo_id']])->assertCreated();
    }

    public function test_inscripcion_sin_historial_se_elimina_sin_borrar_equipo(): void
    {
        $campeonato = $this->campeonato();
        $inscripcion = $this->inscripcion($campeonato['id']);
        $this->deleteJson('/api/inscripciones-equipos/'.$inscripcion['id'])->assertOk();
        $this->assertDatabaseHas('equipos', ['id' => $inscripcion['equipo_id']]);
        $this->assertDatabaseHas('campeonatos', ['id' => $campeonato['id']]);
        $this->assertDatabaseMissing('campeonato_equipos', ['id' => $inscripcion['id']]);
    }

    public function test_plantillas_impiden_jugador_y_dorsal_activos_repetidos(): void
    {
        $campeonato = $this->campeonato();
        $local = $this->inscripcion($campeonato['id']);
        $visitante = $this->inscripcion($campeonato['id']);
        $plantilla = $this->plantilla($local['id']);
        $datos = ['jugador_id' => $plantilla['jugador_id'], 'dorsal' => 11, 'posicion' => 'Defensa', 'fecha_ingreso' => '2026-01-02'];
        $this->postJson('/api/inscripciones-equipos/'.$visitante['id'].'/plantillas', $datos)->assertConflict();
        $datos['jugador_id'] = $this->jugador('Ana')['id'];
        $datos['dorsal'] = 10;
        $this->postJson('/api/inscripciones-equipos/'.$local['id'].'/plantillas', $datos)->assertConflict();
        $this->assertDatabaseCount('plantillas', 1);
    }

    public function test_salida_conserva_historial_y_permite_otra_inscripcion(): void
    {
        $campeonato = $this->campeonato();
        $local = $this->inscripcion($campeonato['id']);
        $visitante = $this->inscripcion($campeonato['id']);
        $plantilla = $this->plantilla($local['id']);
        $ruta = '/api/plantillas/'.$plantilla['id'];
        $this->patchJson($ruta, ['fecha_salida' => '2025-12-31'])->assertUnprocessable();
        $this->patchJson($ruta, ['fecha_salida' => '2026-01-10'])->assertOk();
        $this->patchJson($ruta, ['fecha_salida' => null])->assertConflict();
        $this->deleteJson($ruta)->assertConflict();
        $nueva = $this->plantilla($visitante['id'], $plantilla['jugador_id']);
        $this->assertNotSame($plantilla['id'], $nueva['id']);
        $this->assertSame('2026-01-10', substr($this->getJson($ruta)->assertOk()->json('fecha_salida'), 0, 10));
        $this->getJson('/api/inscripciones-equipos/'.$local['id'].'/plantillas')->assertOk()->assertJsonCount(1);
    }

    public function test_equipo_retirado_no_admite_jugadores_y_patch_no_cambia_identidad(): void
    {
        $campeonato = $this->campeonato();
        $inscripcion = $this->inscripcion($campeonato['id']);
        $plantilla = $this->plantilla($inscripcion['id']);
        $this->patchJson('/api/plantillas/'.$plantilla['id'], ['jugador_id' => 999])->assertUnprocessable();
        $this->patchJson('/api/inscripciones-equipos/'.$inscripcion['id'], ['estado' => 'retirado'])->assertOk();
        $jugador = $this->jugador();
        $this->postJson('/api/inscripciones-equipos/'.$inscripcion['id'].'/plantillas', [
            'jugador_id' => $jugador['id'], 'dorsal' => 11, 'posicion' => 'Portero', 'fecha_ingreso' => '2026-01-01',
        ])->assertConflict();
    }

    public function test_partido_crea_dos_participantes_y_permite_intercambiar_localia(): void
    {
        $escenario = $this->escenario(false);
        $partido = $escenario['partido'];
        $this->assertCount(2, $partido['participantes']);
        $ruta = '/api/partidos/'.$partido['id'];
        $this->getJson($ruta)->assertOk()->assertJsonCount(2, 'participantes');
        $this->patchJson($ruta, ['local_id' => $escenario['visitante']['id'], 'visitante_id' => $escenario['local']['id']])->assertOk();
        $this->assertDatabaseHas('equipo_partido', ['partido_id' => $partido['id'], 'rol' => 'local', 'campeonato_equipo_id' => $escenario['visitante']['id']]);
        $this->assertDatabaseCount('equipo_partido', 2);
    }

    public function test_partidos_rechazan_equipo_repetido_ajeno_o_retirado(): void
    {
        $escenario = $this->escenario(false);
        $otro = $this->campeonato();
        $ajeno = $this->inscripcion($otro['id']);
        $ruta = '/api/campeonatos/'.$escenario['campeonato']['id'].'/partidos';
        $datos = ['jornada' => 2, 'local_id' => $escenario['local']['id']];
        $this->postJson($ruta, $datos + ['visitante_id' => $escenario['local']['id']])->assertUnprocessable();
        $this->postJson($ruta, $datos + ['visitante_id' => $ajeno['id']])->assertUnprocessable();
        $this->patchJson('/api/inscripciones-equipos/'.$escenario['visitante']['id'], ['estado' => 'retirado'])->assertOk();
        $this->postJson($ruta, $datos + ['visitante_id' => $escenario['visitante']['id']])->assertUnprocessable();
        $this->assertDatabaseCount('partidos', 1);
    }

    public function test_calendario_cuatro_equipos_genera_seis_partidos_sin_duplicados(): void
    {
        $campeonato = $this->campeonato();
        for ($numero = 1; $numero <= 4; $numero++) {
            $this->inscripcion($campeonato['id'], 'Equipo '.$numero);
        }
        $ruta = '/api/campeonatos/'.$campeonato['id'].'/generar-calendario';
        $calendario = $this->postJson($ruta)->assertCreated()->assertJsonPath('jornadas', 3)
            ->assertJsonPath('partidos_creados', 6)->assertJsonPath('vueltas', 1)->json('partidos');
        $parejas = [];
        $porJornada = [];
        foreach ($calendario as $partido) {
            $this->assertCount(2, $partido['participantes']);
            $ids = array_column($partido['participantes'], 'campeonato_equipo_id');
            sort($ids);
            $parejas[] = implode('-', $ids);
            foreach ($ids as $id) {
                $clave = $partido['jornada'].'-'.$id;
                $this->assertArrayNotHasKey($clave, $porJornada);
                $porJornada[$clave] = true;
            }
        }
        $this->assertCount(6, array_unique($parejas));
        $this->postJson($ruta)->assertConflict();
        $this->assertDatabaseCount('partidos', 6);
        $this->assertDatabaseCount('equipo_partido', 12);
    }

    public function test_transiciones_impiden_retrocesos_y_cambios_en_finalizados(): void
    {
        $escenario = $this->escenario(false);
        $ruta = '/api/partidos/'.$escenario['partido']['id'];
        $this->solicitudArbitral('patch', $ruta, ['estado' => 'finalizado'])->assertConflict();
        $this->solicitudArbitral('patch', $ruta, ['estado' => 'en_curso'])->assertOk();
        $this->patchJson($ruta, ['local_id' => $escenario['visitante']['id'], 'visitante_id' => $escenario['local']['id']])->assertConflict();
        $this->solicitudArbitral('patch', $ruta, ['estado' => 'suspendido'])->assertOk();
        $this->solicitudArbitral('patch', $ruta, ['estado' => 'programado'])->assertUnprocessable();
        $this->solicitudArbitral('patch', $ruta, ['estado' => 'en_curso'])->assertOk();
        $this->solicitudArbitral('patch', $ruta, ['estado' => 'finalizado'])->assertOk();
        $this->patchJson($ruta, ['jornada' => 2])->assertConflict();
    }

    public function test_gol_autogol_y_tarjetas_producen_marcador_correcto(): void
    {
        $escenario = $this->escenario();
        $partido = $escenario['partido'];
        $local = $this->participante($partido, 'local');
        $visitante = $this->participante($partido, 'visitante');
        $this->evento($partido, $local, $escenario['plantillaLocal']['id']);
        $this->evento($partido, $local, $escenario['plantillaVisitante']['id'], 'autogol');
        $this->evento($partido, $visitante, $escenario['plantillaVisitante']['id']);
        foreach (['amarilla', 'roja', 'falta'] as $tipo) {
            $this->evento($partido, $local, $escenario['plantillaLocal']['id'], $tipo);
        }
        $this->getJson('/api/partidos/'.$partido['id'].'/marcador')->assertOk()
            ->assertJsonPath('local.goles', 2)->assertJsonPath('visitante.goles', 1);
        $this->deleteJson('/api/partidos/'.$partido['id'])->assertConflict();
        $this->deleteJson('/api/plantillas/'.$escenario['plantillaLocal']['id'])->assertConflict();
    }

    public function test_asistencias_no_se_duplican_ni_permiten_autoasistencia(): void
    {
        $escenario = $this->escenario();
        $partido = $escenario['partido'];
        $local = $this->participante($partido, 'local');
        $gol = $this->evento($partido, $local, $escenario['plantillaLocal']['id']);
        $ruta = '/api/partidos/'.$partido['id'].'/eventos';
        $datos = ['equipo_partido_id' => $local, 'plantilla_id' => $escenario['plantillaLocal']['id'],
            'tipo' => 'asistencia', 'minuto' => 15, 'gol_relacionado_id' => $gol['id']];
        $this->solicitudArbitral('post', $ruta, $datos)->assertUnprocessable();
        $asistente = $this->plantilla($escenario['local']['id'], null, 11);
        $datos['plantilla_id'] = $asistente['id'];
        $asistencia = $this->solicitudArbitral('post', $ruta, $datos)->assertCreated()->json('evento');
        $this->solicitudArbitral('post', $ruta, $datos)->assertConflict();
        $this->getJson('/api/partidos/'.$partido['id'].'/marcador')->assertOk()->assertJsonPath('local.goles', 1);
        $this->solicitudArbitral('delete', '/api/eventos/'.$gol['id'])->assertConflict();
        $this->solicitudArbitral('delete', '/api/eventos/'.$asistencia['id'])->assertOk();
        $this->solicitudArbitral('delete', '/api/eventos/'.$gol['id'])->assertOk();
    }

    public function test_eventos_rechazan_jugador_ajeno_y_partido_no_iniciado(): void
    {
        $escenario = $this->escenario(false);
        $partido = $escenario['partido'];
        $local = $this->participante($partido, 'local');
        $ruta = '/api/partidos/'.$partido['id'].'/eventos';
        $datos = ['equipo_partido_id' => $local, 'plantilla_id' => $escenario['plantillaLocal']['id'], 'tipo' => 'gol', 'minuto' => 10];
        $this->solicitudArbitral('post', $ruta, $datos)->assertConflict();
        $this->solicitudArbitral('patch', '/api/partidos/'.$partido['id'], ['estado' => 'en_curso'])->assertOk();
        $otro = $this->campeonato();
        $ajeno = $this->inscripcion($otro['id']);
        $plantilla = $this->plantilla($ajeno['id']);
        $datos['plantilla_id'] = $plantilla['id'];
        $this->solicitudArbitral('post', $ruta, $datos)->assertUnprocessable();
        $datos['plantilla_id'] = $escenario['plantillaVisitante']['id'];
        $this->solicitudArbitral('post', $ruta, $datos)->assertUnprocessable();
        $this->assertDatabaseCount('eventos_estadisticos', 0);
    }

    public function test_eventos_finalizados_no_se_corrigen_ni_eliminan(): void
    {
        $escenario = $this->escenario();
        $partido = $escenario['partido'];
        $gol = $this->evento($partido, $this->participante($partido, 'local'), $escenario['plantillaLocal']['id']);
        $this->solicitudArbitral('patch', '/api/eventos/'.$gol['id'], ['minuto' => 20])->assertOk();
        $this->solicitudArbitral('patch', '/api/partidos/'.$partido['id'], ['estado' => 'finalizado'])->assertOk();
        $this->solicitudArbitral('patch', '/api/eventos/'.$gol['id'], ['minuto' => 21])->assertConflict();
        $this->solicitudArbitral('delete', '/api/eventos/'.$gol['id'])->assertConflict();
        $this->assertDatabaseHas('eventos_estadisticos', ['id' => $gol['id'], 'minuto' => 20]);
    }
}
