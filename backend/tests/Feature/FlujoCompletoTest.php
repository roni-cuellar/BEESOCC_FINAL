<?php

namespace Tests\Feature;

class FlujoCompletoTest extends BackendTestCase
{
    public function test_flujo_completo_desde_campeonato_hasta_resultados(): void
    {
        $campeonato = $this->campeonato('Copa integral');
        $id = $campeonato['id'];
        $this->postJson('/api/campeonatos/'.$id.'/configuracion', [
            'numero_vueltas' => 1, 'orden_desempate' => 'puntos,diferencia_goles,goles_favor',
        ])->assertCreated();

        $plantillas = [];
        $inscripciones = [];
        for ($numero = 1; $numero <= 4; $numero++) {
            $inscripcion = $this->inscripcion($id, 'Equipo '.$numero);
            $inscripciones[$inscripcion['id']] = $inscripcion;
            $plantillas[$inscripcion['id']] = $this->plantilla($inscripcion['id']);
        }

        $partidos = $this->postJson('/api/campeonatos/'.$id.'/generar-calendario')
            ->assertCreated()->assertJsonPath('jornadas', 3)->assertJsonPath('partidos_creados', 6)->json('partidos');
        $partido = $partidos[0];
        $local = collect($partido['participantes'])->firstWhere('rol', 'local');
        $visitante = collect($partido['participantes'])->firstWhere('rol', 'visitante');
        $this->patchJson('/api/partidos/'.$partido['id'], ['estado' => 'en_curso'])->assertOk();

        for ($gol = 0; $gol < 2; $gol++) {
            $this->evento($partido, $local['id'], $plantillas[$local['campeonato_equipo_id']]['id']);
        }
        $this->evento($partido, $visitante['id'], $plantillas[$visitante['campeonato_equipo_id']]['id']);
        $this->getJson('/api/partidos/'.$partido['id'].'/marcador')
            ->assertOk()->assertJsonPath('local.goles', 2)->assertJsonPath('visitante.goles', 1);
        $this->patchJson('/api/partidos/'.$partido['id'], ['estado' => 'finalizado'])->assertOk();

        $this->getJson('/api/campeonatos/'.$id.'/tabla-posiciones')->assertOk()
            ->assertJsonPath('tabla.0.equipo_id', $inscripciones[$local['campeonato_equipo_id']]['equipo_id'])
            ->assertJsonPath('tabla.0.PTS', 3)->assertJsonPath('tabla.0.PJ', 1);
        $this->getJson('/api/campeonatos/'.$id.'/estadisticas/goleadores')->assertOk()
            ->assertJsonPath('resultados.0.jugador_id', $plantillas[$local['campeonato_equipo_id']]['jugador_id'])
            ->assertJsonPath('resultados.0.total', 2);
        $equipos = collect($this->getJson('/api/campeonatos/'.$id.'/estadisticas/equipos')->assertOk()->json('resultados'))->keyBy('equipo_id');
        $this->assertSame(2, $equipos[$inscripciones[$local['campeonato_equipo_id']]['equipo_id']]['goles']);
        $this->assertDatabaseCount('partidos', 6);
        $this->assertDatabaseCount('equipo_partido', 12);
        $this->assertDatabaseCount('eventos_estadisticos', 3);
    }
}
