<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsLocalTest extends TestCase
{
    public function test_permite_el_frontend_local_con_credenciales(): void
    {
        $this->withHeaders([
            'Origin' => 'http://127.0.0.1:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->options('/api/campeonatos')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_no_autoriza_un_origen_externo(): void
    {
        $this->withHeaders([
            'Origin' => 'https://externo.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/campeonatos')
            ->assertNoContent()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
