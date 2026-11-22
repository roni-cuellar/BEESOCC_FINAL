<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Jugador;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PanelArbitralTest extends BackendTestCase
{
    private function prepararEscenario(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(13, 0));
        $propietario = auth('web')->user();
        $escenario = $this->escenario(false);
        $liga = Campeonato::findOrFail($escenario['campeonato']['id']);
        $liga->configuracion()->create(['modalidad' => 'Fútbol 5', 'minimo_jugadores' => 1]);
        $arbitro = User::factory()->create();
        $this->postJson('/api/campeonatos/'.$liga->id.'/arbitro', ['usuario_id' => $arbitro->id])->assertCreated();
        $partido = '/api/partidos/'.$escenario['partido']['id'];
        $ruta = $partido.'/panel-arbitral';
        $datos = ['version' => 0, 'duracion_minutos' => 2, 'titulares' => [
            'local' => [$escenario['plantillaLocal']['id']], 'visitante' => [$escenario['plantillaVisitante']['id']],
        ]];

        return compact('propietario', 'arbitro', 'escenario', 'liga', 'partido', 'ruta', 'datos');
    }

    private function entrar(User $usuario): void
    {
        app('auth')->forgetGuards();
        $this->actingAs($usuario, 'web');
    }

    public function test_persiste_titulares_y_reloj_y_fija_el_minuto_en_el_servidor(): void
    {
        ['liga' => $liga, 'arbitro' => $arbitro, 'escenario' => $escenario, 'partido' => $partido, 'ruta' => $ruta, 'datos' => $datos] = $this->prepararEscenario();
        $foto = 'https://example.test/titular.png';
        Jugador::findOrFail($escenario['plantillaLocal']['jugador_id'])->update(['foto' => $foto]);
        $this->entrar($arbitro);
        $this->putJson($ruta, $datos)->assertOk()->assertJsonPath('version', 1)
            ->assertJsonPath('campeonato_en_curso', false)->assertJsonPath('elegibles.local.0.jugador.foto', $foto);
        $this->patchJson($partido, ['estado' => 'en_curso', 'version_panel' => 1])->assertConflict();
        $liga->update(['estado' => 'en_curso']);
        $this->entrar($arbitro);
        $this->getJson($ruta)->assertOk()->assertJsonPath('titulares', $datos['titulares'])
            ->assertJsonPath('duracion_minutos', 2)->assertJsonMissingPath('elegibles.local.0.jugador.administrador_id');
        $this->patchJson($partido, ['estado' => 'en_curso', 'version_panel' => 1])->assertOk();
        $this->putJson($ruta, array_replace($datos, ['version' => 2]))->assertConflict();
        $this->travel(70)->seconds();
        $this->getJson($ruta)->assertOk()->assertJsonPath('segundos', 70)->assertJsonPath('en_marcha', true);

        $this->patchJson($ruta.'/reloj', ['accion' => 'pausar', 'version' => 2])->assertOk()
            ->assertJsonPath('segundos', 70)->assertJsonPath('en_marcha', false)->assertJsonPath('version', 3);
        $this->travel(40)->seconds();
        $this->getJson($ruta)->assertOk()->assertJsonPath('segundos', 70);
        $evento = [
            'equipo_partido_id' => $this->participante($escenario['partido'], 'local'),
            'plantilla_id' => $escenario['plantillaLocal']['id'],
            'tipo' => 'gol',
            'minuto' => 149,
        ];
        $this->postJson($partido.'/eventos', $evento)->assertCreated()->assertJsonPath('evento.minuto', 1);
        $this->patchJson($ruta.'/reloj', ['accion' => 'reanudar', 'version' => 2])->assertConflict();
        $this->patchJson($ruta.'/reloj', ['accion' => 'reanudar', 'version' => 3])->assertOk()->assertJsonPath('version', 4);
        $this->travel(58)->seconds();
        $this->getJson($ruta)->assertOk()->assertJsonPath('segundos', 120);
        $this->getJson($partido)->assertOk()->assertJsonPath('estado', 'en_curso')->assertJsonPath('oficializado_at', null);
        unset($evento['minuto']);
        $evento['tipo'] = 'amarilla';
        $this->postJson($partido.'/eventos', $evento)->assertCreated()->assertJsonPath('evento.minuto', 2);
        $this->postJson($partido.'/finalizar')->assertOk()->assertJsonPath('partido.estado', 'finalizado');
        $this->getJson($ruta)->assertOk()->assertJsonPath('segundos', 120)->assertJsonPath('en_marcha', false);
        $this->getJson($partido.'/marcador')->assertOk()->assertJsonPath('local.goles', 1);
        $this->assertDatabaseHas('auditoria_cambios', ['entidad_id' => $escenario['partido']['id'], 'accion' => 'pausar_reloj']);
    }

    public function test_valida_elegibilidad_limites_y_minimos_y_rechaza_versiones_obsoletas(): void
    {
        ['liga' => $liga, 'arbitro' => $arbitro, 'escenario' => $escenario, 'partido' => $partido, 'ruta' => $ruta, 'datos' => $datos] = $this->prepararEscenario();
        $adicionales = [];
        for ($dorsal = 11; $dorsal <= 15; $dorsal++) {
            $adicionales[] = $this->plantilla($escenario['local']['id'], null, $dorsal)['id'];
        }
        $this->entrar($arbitro);
        $this->putJson($ruta, array_replace($datos, ['duracion_minutos' => 151]))->assertUnprocessable();
        $this->putJson($ruta, array_replace($datos, ['titulares' => [
            'local' => [...$datos['titulares']['local'], ...$adicionales], 'visitante' => $datos['titulares']['visitante'],
        ]]))->assertUnprocessable();
        $this->putJson($ruta, array_replace($datos, ['titulares' => [
            'local' => $datos['titulares']['visitante'], 'visitante' => $datos['titulares']['local'],
        ]]))->assertUnprocessable();
        DB::table('plantillas')->where('id', $adicionales[0])->update(['fecha_ingreso' => '2026-11-23']);
        $this->putJson($ruta, array_replace($datos, ['titulares' => [
            'local' => [$adicionales[0]], 'visitante' => $datos['titulares']['visitante'],
        ]]))->assertUnprocessable();
        $this->putJson($ruta, array_replace($datos, ['titulares' => ['local' => [], 'visitante' => []]]))->assertOk();
        $this->putJson($ruta, $datos)->assertConflict();
        $liga->update(['estado' => 'en_curso']);
        $this->patchJson($partido, ['estado' => 'en_curso', 'version_panel' => 1])->assertUnprocessable();
        $this->getJson($partido)->assertOk()->assertJsonPath('estado', 'programado');
    }

    public function test_solo_el_arbitro_vigente_accede_y_el_contrato_publico_no_expone_el_panel(): void
    {
        ['liga' => $liga, 'propietario' => $propietario, 'arbitro' => $arbitro, 'escenario' => $escenario, 'ruta' => $ruta, 'datos' => $datos] = $this->prepararEscenario();
        $this->getJson($ruta)->assertForbidden();
        $this->putJson($ruta, $datos)->assertForbidden();
        $this->entrar($arbitro);
        $this->putJson($ruta, $datos)->assertOk();
        $this->entrar($propietario);
        $base = '/api/campeonatos/'.$liga->id;
        $this->patchJson($base.'/publicacion', ['publicado' => true])->assertOk();
        $this->deleteJson($base.'/arbitro', ['motivo_revocacion' => 'Asignación terminada.'])->assertOk();
        $this->entrar($arbitro);
        $this->getJson($ruta)->assertForbidden();
        $this->patchJson($ruta.'/reloj', ['accion' => 'reanudar', 'version' => 1])->assertForbidden();
        auth('web')->logout();
        app('auth')->forgetGuards();
        $this->getJson($ruta)->assertUnauthorized();
        $this->getJson('/api/publico/campeonatos/'.$liga->id.'/partidos/'.$escenario['partido']['id'])
            ->assertOk()->assertJsonMissingPath('titulares')->assertJsonMissingPath('reloj_desde');
    }
}
