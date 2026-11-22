<?php

namespace Tests\Feature;

use App\Models\User;

class SeleccionRolesTest extends BackendTestCase
{
    public function test_los_destinos_aplican_propiedad_publicacion_y_asignacion_vigente(): void
    {
        $propietario = auth('web')->user();
        $arbitro = User::factory()->create();
        $privada = $this->campeonato('Liga privada')['id'];
        $publicada = $this->campeonato('Liga publicada')['id'];
        $gestion = '/api/campeonatos/'.$privada;
        $partidos = '/api/mis-arbitrajes/'.$privada.'/partidos';
        $publico = '/api/publico/campeonatos';

        $this->patchJson('/api/campeonatos/'.$publicada.'/publicacion', ['publicado' => true])->assertOk();
        $this->getJson($gestion)->assertOk();
        $this->getJson($partidos)->assertForbidden();
        $this->postJson($gestion.'/arbitro', ['usuario_id' => $arbitro->id])->assertCreated();

        app('auth')->forgetGuards();
        $this->actingAs($arbitro, 'web');
        $this->getJson('/api/campeonatos')->assertOk()->assertJsonCount(0);
        $this->getJson($gestion)->assertForbidden();
        $this->getJson('/api/mis-arbitrajes')->assertOk()->assertJsonPath('asignaciones.0.campeonato.id', $privada);
        $this->getJson($partidos)->assertOk();
        $this->getJson($publico)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $publicada);
        $this->getJson($publico.'/'.$privada)->assertNotFound();

        app('auth')->forgetGuards();
        $this->actingAs($propietario, 'web');
        $this->deleteJson($gestion.'/arbitro', ['motivo_revocacion' => 'Fin de la asignación.'])->assertOk();
        app('auth')->forgetGuards();
        $this->actingAs($arbitro, 'web');
        $this->getJson('/api/mis-arbitrajes')->assertOk()->assertJsonCount(0, 'asignaciones');
        $this->getJson($partidos)->assertForbidden();
    }
}
