<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IdentidadDeportivaTest extends BackendTestCase
{
    public function test_identidades_y_ficha_comparten_propiedad_y_conservan_el_ranking_existente(): void
    {
        $liga = $this->escenario();
        $id = $liga['campeonato']['id'];
        $jugador = $liga['plantillaLocal']['jugador_id'];
        $equipo = $liga['visitante']['equipo_id'];
        Jugador::findOrFail($jugador)->update(['foto' => 'https://example.test/foto.png']);
        Equipo::findOrFail($equipo)->update(['escudo' => 'https://example.test/escudo.png']);
        $this->evento($liga['partido'], $this->participante($liga['partido'], 'local'), $liga['plantillaLocal']['id']);
        DB::table('plantillas')->where('id', $liga['plantillaLocal']['id'])->update(['fecha_salida' => '2026-10-01']);
        $this->plantilla($liga['visitante']['id'], $jugador, 22);

        $privado = '/api/campeonatos/'.$id;
        $publico = '/api/publico/campeonatos/'.$id;
        $ruta = '/identidades-deportivas';
        $this->getJson($publico.$ruta)->assertNotFound();
        $this->getJson($privado.$ruta)->assertOk()->assertJsonFragment([
            'jugador_id' => $jugador, 'foto' => 'https://example.test/foto.png',
            'equipo' => ['id' => $equipo, 'nombre' => 'Leones', 'escudo' => 'https://example.test/escudo.png'],
        ])->assertDontSee('fecha_nacimiento')->assertDontSee('administrador_id');
        $this->getJson($privado.'/fichas/jugadores/'.$jugador)->assertOk()
            ->assertJsonPath('equipos.0.dorsal', 22)->assertJsonPath('estadisticas.goles', 1);
        $this->patchJson($privado.'/publicacion', ['publicado' => true])->assertOk();
        $this->getJson($publico.$ruta)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee('fecha_nacimiento')->assertDontSee('administrador_id');
        $this->getJson($publico.'/estadisticas/goleadores')->assertOk()->assertExactJson([
            'estadistica' => 'goleadores',
            'resultados' => [['jugador_id' => $jugador, 'nombre' => 'Carlos', 'apellido' => 'Pérez', 'total' => 1]],
        ]);
        $this->patchJson($publico.$ruta, [])->assertMethodNotAllowed();

        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        $this->getJson($privado.$ruta)->assertForbidden();
        $this->getJson($privado.'/fichas/jugadores/'.$jugador)->assertForbidden();
        $this->getJson($publico.$ruta)->assertOk();
    }
}
