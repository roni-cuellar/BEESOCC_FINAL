<?php

namespace Tests\Feature;

use App\Models\AsignacionArbitro;
use App\Models\Partido;
use App\Models\Plantilla;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ControlArbitralTest extends BackendTestCase
{
    private function preparar(): array
    {
        $escenario = $this->escenario(false);
        $escenario['administrador'] = auth('web')->user();
        $escenario['arbitro'] = User::factory()->create();
        $this->postJson('/api/campeonatos/'.$escenario['campeonato']['id'].'/arbitro', [
            'usuario_id' => $escenario['arbitro']->id,
        ])->assertCreated();

        return $escenario;
    }

    private function entrar(User $usuario): void
    {
        app('auth')->forgetGuards();
        $this->actingAs($usuario, 'web');
    }

    private function datos(array $escenario, string $tipo = 'gol'): array
    {
        return ['equipo_partido_id' => $this->participante($escenario['partido'], 'local'),
            'plantilla_id' => $escenario['plantillaLocal']['id'], 'tipo' => $tipo, 'minuto' => 10];
    }

    private function comprobarBloqueo(array $escenario, int $codigo): void
    {
        $ruta = '/api/partidos/'.$escenario['partido']['id'];
        $this->getJson($ruta)->assertStatus($codigo);
        $this->getJson($ruta.'/marcador')->assertStatus($codigo);
        $this->getJson($ruta.'/plantillas-arbitrales')->assertStatus($codigo);
        $this->getJson($ruta.'/eventos')->assertStatus($codigo);
        foreach (['en_curso', 'suspendido', 'finalizado'] as $estado) {
            $this->patchJson($ruta, ['estado' => $estado])->assertStatus($codigo);
        }
        $this->postJson($ruta.'/eventos', $this->datos($escenario))->assertStatus($codigo);
        $this->assertDatabaseHas('partidos', ['id' => $escenario['partido']['id'], 'estado' => 'programado']);
        $this->assertDatabaseCount('eventos_estadisticos', 0);
    }

    public function test_arbitro_consulta_inicia_suspende_reanuda_y_finaliza_sin_administrar(): void
    {
        $e = $this->preparar();
        $this->entrar($e['arbitro']);
        $ruta = '/api/partidos/'.$e['partido']['id'];
        foreach (['', '/marcador', '/eventos', '/plantillas-arbitrales'] as $sufijo) {
            $this->getJson($ruta.$sufijo)->assertOk();
        }
        $this->getJson('/api/mis-arbitrajes/'.$e['campeonato']['id'].'/partidos')->assertOk()->assertJsonCount(1);
        $this->patchJson($ruta, ['lugar' => 'Otra sede'])->assertForbidden();
        $this->deleteJson($ruta)->assertForbidden();
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/generar-calendario')->assertForbidden();
        foreach (['en_curso', 'suspendido', 'en_curso', 'finalizado'] as $estado) {
            $this->patchJson($ruta, ['estado' => $estado])->assertOk()->assertJsonPath('partido.estado', $estado);
        }
        $this->patchJson($ruta, ['estado' => 'en_curso'])->assertConflict();
        $this->postJson($ruta.'/eventos', $this->datos($e))->assertConflict();
    }

    public function test_revocado_y_reemplazado_pierden_permisos_inmediatamente(): void
    {
        $e = $this->preparar();
        $nuevo = User::factory()->create();
        $ruta = '/api/campeonatos/'.$e['campeonato']['id'].'/arbitro';
        $this->patchJson($ruta, ['usuario_id' => $nuevo->id, 'motivo_revocacion' => 'Reemplazo'])->assertOk();
        $this->entrar($e['arbitro']);
        $this->comprobarBloqueo($e, 403);
        $this->entrar($e['administrador']);
        $this->deleteJson($ruta, ['motivo_revocacion' => 'Fin'])->assertOk();
        $this->entrar($nuevo);
        $this->comprobarBloqueo($e, 403);
    }

    public function test_arbitro_de_otro_campeonato_no_interviene(): void
    {
        $e = $this->preparar();
        $otro = $this->campeonato();
        $usuario = User::factory()->create();
        $this->postJson('/api/campeonatos/'.$otro['id'].'/arbitro', ['usuario_id' => $usuario->id])->assertCreated();
        $this->entrar($usuario);
        $this->comprobarBloqueo($e, 403);
    }

    public function test_asignacion_futura_no_autoriza(): void
    {
        $e = $this->preparar();
        AsignacionArbitro::where('campeonato_id', $e['campeonato']['id'])->update(['fecha_inicio' => now()->addDay()]);
        $this->entrar($e['arbitro']);
        $this->comprobarBloqueo($e, 403);
    }

    public function test_invitado_necesita_autenticacion(): void
    {
        $e = $this->preparar();
        auth('web')->logout();
        app('auth')->forgetGuards();
        $this->comprobarBloqueo($e, 401);
    }

    public function test_administrador_organiza_y_consulta_pero_no_escribe_acta(): void
    {
        $e = $this->preparar();
        $ruta = '/api/partidos/'.$e['partido']['id'];
        $this->patchJson($ruta, ['lugar' => 'Cancha central'])->assertOk();
        $this->getJson($ruta.'/marcador')->assertOk();
        foreach (['en_curso', 'suspendido', 'finalizado'] as $estado) {
            $this->patchJson($ruta, ['estado' => $estado])->assertForbidden();
        }
        $this->postJson($ruta.'/eventos', $this->datos($e))->assertForbidden();
        $partido = Partido::findOrFail($e['partido']['id']);
        $this->assertFalse(Gate::allows('gestionarActa', $partido));
        $this->entrar($e['arbitro']);
        $this->assertTrue(Gate::allows('gestionarActa', $partido));
        $this->entrar($e['administrador']);
        $this->patchJson($ruta, ['estado' => 'cancelado'])->assertOk();
    }

    public function test_rechaza_estados_invalidos_y_campos_organizativos_mezclados(): void
    {
        $e = $this->preparar();
        $this->entrar($e['arbitro']);
        $ruta = '/api/partidos/'.$e['partido']['id'];
        foreach (['suspendido', 'finalizado'] as $estado) {
            $this->patchJson($ruta, ['estado' => $estado])->assertConflict();
        }
        foreach (['inventado', 'programado'] as $estado) {
            $this->patchJson($ruta, ['estado' => $estado])->assertUnprocessable();
        }
        $this->patchJson($ruta, ['estado' => 'en_curso', 'lugar' => 'Intrusión'])->assertUnprocessable();
        $this->patchJson($ruta, ['estado' => 'cancelado'])->assertForbidden();
        $this->patchJson($ruta, ['estado' => 'en_curso'])->assertOk();
        $this->patchJson($ruta, ['estado' => 'en_curso'])->assertConflict();
    }

    public function test_rechaza_participantes_plantillas_y_goles_de_otro_partido(): void
    {
        $e = $this->preparar();
        $otro = $this->escenario();
        $gol = $this->evento($otro['partido'], $this->participante($otro['partido'], 'local'), $otro['plantillaLocal']['id']);
        $this->entrar($e['arbitro']);
        $ruta = '/api/partidos/'.$e['partido']['id'];
        $this->patchJson($ruta, ['estado' => 'en_curso'])->assertOk();
        foreach ([
            ['equipo_partido_id' => $this->participante($otro['partido'], 'local')],
            ['plantilla_id' => $otro['plantillaLocal']['id']],
            ['plantilla_id' => $e['plantillaVisitante']['id']],
            ['tipo' => 'asistencia', 'gol_relacionado_id' => $gol['id']],
            ['tipo' => 'autogol'],
            ['jugador_id' => $e['plantillaLocal']['jugador_id']],
            ['partido_id' => $otro['partido']['id']],
        ] as $cambio) {
            $this->postJson($ruta.'/eventos', array_replace($this->datos($e), $cambio))->assertUnprocessable();
        }
        $this->patchJson('/api/eventos/'.$gol['id'], ['minuto' => 11])->assertForbidden();
        $this->deleteJson('/api/eventos/'.$gol['id'])->assertForbidden();
        $this->assertDatabaseCount('eventos_estadisticos', 1);
    }

    public function test_valida_tipo_minuto_asistencia_y_elegibilidad_temporal(): void
    {
        $e = $this->preparar();
        $this->entrar($e['arbitro']);
        $ruta = '/api/partidos/'.$e['partido']['id'];
        $this->patchJson($ruta, ['estado' => 'en_curso'])->assertOk();
        foreach ([['tipo' => 'penal'], ['minuto' => -1], ['minuto' => 151], ['tipo' => 'asistencia']] as $cambio) {
            $this->postJson($ruta.'/eventos', array_replace($this->datos($e), $cambio))->assertUnprocessable();
        }
        Partido::whereKey($e['partido']['id'])->update(['fecha_hora' => '2026-05-15 10:00:00']);
        $plantilla = Plantilla::findOrFail($e['plantillaLocal']['id']);
        $plantilla->update(['fecha_ingreso' => '2026-05-16']);
        $this->postJson($ruta.'/eventos', $this->datos($e))->assertUnprocessable();
        $plantilla->update(['fecha_ingreso' => '2026-01-01', 'fecha_salida' => '2026-05-14']);
        $this->postJson($ruta.'/eventos', $this->datos($e))->assertUnprocessable();
        $plantilla->update(['fecha_salida' => null]);
        $this->postJson($ruta.'/eventos', $this->datos($e))->assertCreated();
    }

    public function test_revocacion_impide_corregir_y_borrar_eventos_existentes(): void
    {
        $e = $this->preparar();
        $this->solicitudArbitral('patch', '/api/partidos/'.$e['partido']['id'], ['estado' => 'en_curso'])->assertOk();
        $gol = $this->evento($e['partido'], $this->participante($e['partido'], 'local'), $e['plantillaLocal']['id']);
        foreach ([$e['administrador'], $e['arbitro']] as $usuario) {
            if ($usuario->is($e['arbitro'])) {
                $this->deleteJson('/api/campeonatos/'.$e['campeonato']['id'].'/arbitro', ['motivo_revocacion' => 'Fin'])->assertOk();
            }
            $this->entrar($usuario);
            $this->patchJson('/api/eventos/'.$gol['id'], ['minuto' => 22])->assertForbidden();
            $this->deleteJson('/api/eventos/'.$gol['id'])->assertForbidden();
        }
        $this->assertDatabaseHas('eventos_estadisticos', ['id' => $gol['id'], 'minuto' => 15]);
    }
}
