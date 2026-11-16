<?php

namespace Tests\Feature;

use App\Models\CampeonatoEquipo;
use App\Models\ConfiguracionCampeonato;
use App\Models\EventoEstadistico;
use App\Models\Partido;
use App\Models\Plantilla;

class ApiResultadosTest extends BackendTestCase
{
    public function test_tabla_acumula_victorias_empates_derrotas_y_todas_las_estadisticas(): void
    {
        $escenario = $this->escenario();
        $primero = $escenario['partido'];
        $this->evento($primero, $this->participante($primero, 'local'), $escenario['plantillaLocal']['id']);
        $this->evento($primero, $this->participante($primero, 'local'), $escenario['plantillaVisitante']['id'], 'autogol');
        $this->evento($primero, $this->participante($primero, 'visitante'), $escenario['plantillaVisitante']['id']);
        $this->solicitudArbitral('patch', '/api/partidos/'.$primero['id'], ['estado' => 'finalizado'])->assertOk();

        $segundo = $this->partido($escenario['campeonato']['id'], $escenario['local']['id'], $escenario['visitante']['id'], 2);
        $this->solicitudArbitral('patch', '/api/partidos/'.$segundo['id'], ['estado' => 'en_curso'])->assertOk();
        $this->solicitudArbitral('patch', '/api/partidos/'.$segundo['id'], ['estado' => 'finalizado'])->assertOk();

        $tercero = $this->partido($escenario['campeonato']['id'], $escenario['local']['id'], $escenario['visitante']['id'], 3);
        $this->solicitudArbitral('patch', '/api/partidos/'.$tercero['id'], ['estado' => 'en_curso'])->assertOk();
        $this->evento($tercero, $this->participante($tercero, 'visitante'), $escenario['plantillaVisitante']['id']);
        $this->solicitudArbitral('patch', '/api/partidos/'.$tercero['id'], ['estado' => 'finalizado'])->assertOk();
        $sinPartidos = $this->inscripcion($escenario['campeonato']['id'], 'Sin partidos');

        $tabla = $this->getJson('/api/campeonatos/'.$escenario['campeonato']['id'].'/tabla-posiciones')
            ->assertOk()->json('tabla');
        $filas = collect($tabla)->keyBy('equipo_id');
        foreach ([$escenario['local']['equipo_id'], $escenario['visitante']['equipo_id']] as $id) {
            foreach (['PJ' => 3, 'PG' => 1, 'PE' => 1, 'PP' => 1, 'GF' => 2, 'GC' => 2, 'DG' => 0, 'PTS' => 4] as $campo => $valor) {
                $this->assertSame($valor, $filas[$id][$campo], $campo);
            }
        }
        $this->assertSame(0, $filas[$sinPartidos['equipo_id']]['PJ']);
        $this->assertSame(0, $filas[$sinPartidos['equipo_id']]['PTS']);
    }

    public function test_tabla_no_cuenta_partidos_sin_finalizar_y_rechaza_inconsistentes(): void
    {
        $escenario = $this->escenario();
        $partido = $escenario['partido'];
        $this->evento($partido, $this->participante($partido, 'local'), $escenario['plantillaLocal']['id']);
        $ruta = '/api/campeonatos/'.$escenario['campeonato']['id'].'/tabla-posiciones';
        foreach (['en_curso', 'suspendido', 'cancelado'] as $estado) {
            if ($estado !== 'en_curso') {
                if ($estado === 'cancelado') {
                    $this->patchJson('/api/partidos/'.$partido['id'], ['estado' => $estado])->assertOk();
                } else {
                    $this->solicitudArbitral('patch', '/api/partidos/'.$partido['id'], ['estado' => $estado])->assertOk();
                }
            }
            foreach ($this->getJson($ruta)->assertOk()->json('tabla') as $fila) {
                $this->assertSame(0, $fila['PJ']);
                $this->assertSame(0, $fila['GF']);
            }
        }

        // Dato inconsistente preparado solo en la base aislada para probar la defensa.
        Partido::create(['campeonato_id' => $escenario['campeonato']['id'], 'jornada' => 9, 'estado' => 'finalizado']);
        $this->getJson($ruta)->assertConflict();
    }

    public function test_tabla_respeta_los_dos_ordenes_de_desempate(): void
    {
        $campeonato = $this->campeonato();
        $configuracion = '/api/campeonatos/'.$campeonato['id'].'/configuracion';
        $this->postJson($configuracion, ['numero_vueltas' => 1, 'orden_desempate' => 'puntos,diferencia_goles,goles_favor'])->assertCreated();
        $inscripciones = [];
        $plantillas = [];
        for ($numero = 0; $numero < 4; $numero++) {
            $inscripciones[] = $this->inscripcion($campeonato['id'], 'Equipo '.$numero);
            $plantillas[] = $this->plantilla($inscripciones[$numero]['id']);
        }
        foreach ([[0, 1, 2, 0], [2, 3, 4, 3]] as [$local, $visitante, $golesLocal, $golesVisitante]) {
            $partido = $this->partido($campeonato['id'], $inscripciones[$local]['id'], $inscripciones[$visitante]['id']);
            $this->solicitudArbitral('patch', '/api/partidos/'.$partido['id'], ['estado' => 'en_curso'])->assertOk();
            foreach ([['local', $local, $golesLocal], ['visitante', $visitante, $golesVisitante]] as [$rol, $indice, $cantidad]) {
                for ($gol = 0; $gol < $cantidad; $gol++) {
                    $this->evento($partido, $this->participante($partido, $rol), $plantillas[$indice]['id']);
                }
            }
            $this->solicitudArbitral('patch', '/api/partidos/'.$partido['id'], ['estado' => 'finalizado'])->assertOk();
        }
        $ruta = '/api/campeonatos/'.$campeonato['id'].'/tabla-posiciones';
        $this->getJson($ruta)->assertOk()->assertJsonPath('tabla.0.equipo_id', $inscripciones[0]['equipo_id']);
        $this->patchJson($configuracion, ['orden_desempate' => 'puntos,goles_favor,diferencia_goles'])->assertConflict();
        // Régimen histórico ficticio; la API impide modificarlo en curso.
        ConfiguracionCampeonato::where('campeonato_id', $campeonato['id'])->update(['orden_desempate' => 'puntos,goles_favor,diferencia_goles']);
        $this->getJson($ruta)->assertOk()->assertJsonPath('tabla.0.equipo_id', $inscripciones[2]['equipo_id']);
    }

    public function test_estadisticas_conservan_transferencias_y_atribuyen_autogoles_al_beneficiado(): void
    {
        $escenario = $this->escenario();
        $partido = $escenario['partido'];
        $local = $this->participante($partido, 'local');
        $gol = $this->evento($partido, $local, $escenario['plantillaLocal']['id']);
        $asistente = $this->plantilla($escenario['local']['id'], null, 11);
        $this->evento($partido, $local, $asistente['id'], 'asistencia', $gol['id']);
        $this->evento($partido, $local, $escenario['plantillaVisitante']['id'], 'autogol');
        foreach (['amarilla', 'roja', 'falta'] as $tipo) {
            $this->evento($partido, $local, $escenario['plantillaLocal']['id'], $tipo);
        }
        $this->solicitudArbitral('patch', '/api/partidos/'.$partido['id'], ['estado' => 'finalizado'])->assertOk();
        $this->patchJson('/api/plantillas/'.$escenario['plantillaLocal']['id'], ['fecha_salida' => '2026-02-01'])->assertConflict();
        // Transferencia histórica ficticia para comprobar las estadísticas.
        Plantilla::whereKey($escenario['plantillaLocal']['id'])->update(['fecha_salida' => '2026-02-01']);
        $traslado = $this->plantilla($escenario['visitante']['id'], $escenario['plantillaLocal']['jugador_id'], 11);
        $segundo = $this->partido($escenario['campeonato']['id'], $escenario['local']['id'], $escenario['visitante']['id'], 2);
        $this->solicitudArbitral('patch', '/api/partidos/'.$segundo['id'], ['estado' => 'en_curso'])->assertOk();
        $this->evento($segundo, $this->participante($segundo, 'visitante'), $traslado['id']);
        $this->solicitudArbitral('patch', '/api/partidos/'.$segundo['id'], ['estado' => 'suspendido'])->assertOk();
        $this->patchJson('/api/inscripciones-equipos/'.$escenario['local']['id'], ['estado' => 'retirado'])->assertConflict();
        CampeonatoEquipo::whereKey($escenario['local']['id'])->update(['estado' => 'retirado']);
        $sinEventos = $this->inscripcion($escenario['campeonato']['id'], 'Sin eventos');
        $base = '/api/campeonatos/'.$escenario['campeonato']['id'].'/estadisticas/';
        $this->getJson($base.'goleadores')->assertOk()->assertJsonCount(1, 'resultados')
            ->assertJsonPath('resultados.0.jugador_id', $traslado['jugador_id'])->assertJsonPath('resultados.0.total', 2);
        $this->getJson($base.'asistencias')->assertOk()->assertJsonPath('resultados.0.jugador_id', $asistente['jugador_id'])->assertJsonPath('resultados.0.total', 1);
        foreach (['amarillas', 'rojas', 'faltas'] as $tipo) {
            $this->getJson($base.$tipo)->assertOk()->assertJsonPath('resultados.0.jugador_id', $traslado['jugador_id'])->assertJsonPath('resultados.0.total', 1);
        }
        $equipos = collect($this->getJson($base.'equipos')->assertOk()->json('resultados'))->keyBy('equipo_id');
        $this->assertSame(2, $equipos[$escenario['local']['equipo_id']]['goles']);
        $this->assertSame(1, $equipos[$escenario['visitante']['equipo_id']]['goles']);
        foreach (['amarillas', 'rojas', 'faltas'] as $campo) {
            $this->assertSame(1, $equipos[$escenario['local']['equipo_id']][$campo]);
            $this->assertSame(0, $equipos[$escenario['visitante']['equipo_id']][$campo]);
        }
        $this->assertSame(0, $equipos[$sinEventos['equipo_id']]['goles']);
    }

    public function test_estadisticas_excluyen_programados_cancelados_y_otros_campeonatos(): void
    {
        $escenario = $this->escenario();
        $partido = $escenario['partido'];
        $this->evento($partido, $this->participante($partido, 'local'), $escenario['plantillaLocal']['id']);
        $this->patchJson('/api/partidos/'.$partido['id'], ['estado' => 'cancelado'])->assertOk();
        $programado = $this->partido($escenario['campeonato']['id'], $escenario['local']['id'], $escenario['visitante']['id']);
        // Simula un evento legado que no debería contarse por el estado del partido.
        EventoEstadistico::create(['equipo_partido_id' => $this->participante($programado, 'local'),
            'plantilla_id' => $escenario['plantillaLocal']['id'], 'tipo' => 'gol', 'minuto' => 1]);
        $otro = $this->escenario();
        $this->evento($otro['partido'], $this->participante($otro['partido'], 'local'), $otro['plantillaLocal']['id']);
        $base = '/api/campeonatos/'.$escenario['campeonato']['id'].'/estadisticas/';
        foreach (['goleadores', 'asistencias', 'amarillas', 'rojas', 'faltas'] as $tipo) {
            $this->getJson($base.$tipo)->assertOk()->assertJsonPath('resultados', []);
        }
        foreach ($this->getJson($base.'equipos')->assertOk()->json('resultados') as $equipo) {
            $this->assertSame(0, $equipo['goles']);
        }
    }
}
