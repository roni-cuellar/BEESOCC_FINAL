<?php

namespace Tests\Feature;

use App\Models\Jugador;
use Illuminate\Support\Facades\DB;

class EspectadorTest extends BackendTestCase
{
    public function test_ficha_publica_usa_totales_de_la_liga_y_excluye_datos_privados(): void
    {
        $liga = $this->escenario();
        $otra = $this->escenario();
        $id = $liga['campeonato']['id'];
        $jugador = $liga['plantillaLocal']['jugador_id'];
        $foto = 'https://example.test/jugador-publico.webp';
        Jugador::findOrFail($jugador)->update(['foto' => $foto]);

        $this->evento($liga['partido'], $this->participante($liga['partido'], 'local'), $liga['plantillaLocal']['id']);
        $plantillaAjena = $this->plantilla($otra['local']['id'], $jugador, 25);
        $this->evento($otra['partido'], $this->participante($otra['partido'], 'local'), $plantillaAjena['id']);
        $ruta = '/api/publico/campeonatos/'.$id;
        $ficha = $ruta.'/jugadores/'.$jugador;
        $this->getJson($ficha)->assertNotFound();
        $this->patchJson('/api/campeonatos/'.$id.'/publicacion', ['publicado' => true])->assertOk();
        auth('web')->logout();
        app('auth')->forgetGuards();

        $this->getJson($ruta.'/equipos')->assertOk()->assertJsonPath('0.equipo.ciudad', 'Bogotá');
        $this->getJson($ruta.'/equipos/'.$liga['local']['id'].'/plantilla')->assertOk()
            ->assertJsonPath('0.jugador.id', $jugador)->assertJsonPath('0.dorsal', 10);
        $this->getJson($ficha)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'jugador' => ['id' => $jugador, 'nombre' => 'Carlos', 'apellido' => 'Pérez', 'foto' => $foto],
                'equipos' => [[
                    'equipo' => ['id' => $liga['local']['equipo_id'], 'nombre' => 'Águilas', 'escudo' => null],
                    'dorsal' => 10, 'posicion' => 'Delantero', 'vigente' => true,
                ]],
                'estadisticas' => ['goles' => 1, 'asistencias' => 0, 'amarillas' => 0, 'rojas' => 0, 'faltas' => 0],
            ]);
        $this->getJson($ruta.'/jugadores/'.$otra['plantillaVisitante']['jugador_id'])->assertNotFound();
        $this->patchJson($ficha, ['dorsal' => 25])->assertMethodNotAllowed();
        $this->getJson('/api/campeonatos/'.$id)->assertUnauthorized();
    }

    public function test_ficha_conserva_inscripciones_historicas_y_respeta_su_propietario(): void
    {
        $liga = $this->escenario();
        $id = $liga['campeonato']['id'];
        $plantilla = $liga['plantillaLocal'];
        DB::table('plantillas')->where('id', $plantilla['id'])->update(['fecha_salida' => '2026-04-01']);
        $this->plantilla($liga['visitante']['id'], $plantilla['jugador_id'], 11);
        $this->patchJson('/api/campeonatos/'.$id.'/publicacion', ['publicado' => true])->assertOk();
        $ruta = '/api/publico/campeonatos/'.$id.'/jugadores/'.$plantilla['jugador_id'];
        $this->getJson($ruta)->assertOk()->assertJsonPath('equipos.0.dorsal', 11)
            ->assertJsonPath('equipos.0.vigente', true)->assertJsonPath('equipos.1.dorsal', 10)
            ->assertJsonPath('equipos.1.vigente', false)->assertJsonMissingPath('equipos.1.fecha_salida');

        DB::table('jugadores')->where('id', $plantilla['jugador_id'])->update(['administrador_id' => null]);
        $this->getJson($ruta)->assertNotFound();
    }
}
