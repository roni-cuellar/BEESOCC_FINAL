<?php

namespace Tests\Feature;

use App\Models\AuditoriaCambio;
use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use App\Services\DesempateGrupoService;
use App\Services\TablaGruposService;

class PublicoFormatosTest extends BackendTestCase
{
    private function preparar(string $formato): array
    {
        $c = $this->postJson('/api/campeonatos', ['nombre' => 'Copa pública', 'temporada' => '2026', 'formato' => $formato])->assertCreated()->json('campeonato');
        $ids = [];
        for ($i = 1; $i <= 4; $i++) {
            $ids[] = $this->inscripcion($c['id'], 'Equipo '.$i)['id'];
        }
        $fase = null;
        if ($formato !== 'todos_contra_todos') {
            $fase = $this->postJson('/api/campeonatos/'.$c['id'].'/fases', ['nombre' => 'Fase pública', 'tipo' => $formato === 'grupos_eliminacion' ? 'grupos' : 'eliminacion'])->assertCreated()->json('fase');
            if ($formato === 'grupos_eliminacion') {
                $datos = ['metodo' => 'manual', 'cantidad_grupos' => 2, 'clasificados_por_grupo' => 1, 'grupos' => [array_slice($ids, 0, 2), array_slice($ids, 2, 2)]];
                $v = $this->postJson('/api/fases/'.$fase['id'].'/previsualizar-grupos', $datos)->assertOk()->json();
                $this->postJson('/api/fases/'.$fase['id'].'/confirmar-grupos', $datos + ['confirmacion' => $v['confirmacion']])->assertCreated();
                $this->postJson('/api/fases/'.$fase['id'].'/generar-calendario-grupos')->assertCreated();
            } else {
                $datos = ['siembra' => $ids, 'rondas' => array_fill(0, 2, ['modalidad' => 'unico', 'desempate' => 'penales'])];
                $v = $this->postJson('/api/fases/'.$fase['id'].'/previsualizar-llaves', $datos)->assertOk()->json();
                $this->postJson('/api/fases/'.$fase['id'].'/generar-llaves', $datos + ['confirmacion' => $v['confirmacion']])->assertCreated();
            }
        }
        Campeonato::findOrFail($c['id'])->forceFill(['publicado' => true])->save();

        return [$c['id'], $fase['id'] ?? null, $ids];
    }

    private function invitado(): void
    {
        auth('web')->logout();
        app('auth')->forgetGuards();
    }

    private function privadoAusente(array $datos): void
    {
        foreach ($datos as $campo => $valor) {
            $this->assertNotContains($campo, ['email', 'password', 'fecha_nacimiento', 'administrador_id', 'motivo', 'motivo_administrativo', 'motivo_inactividad', 'administrativo_por_id', 'autorizacion_id', 'auditoria', 'confirmacion', 'huella', 'clave', 'solicitado_por_id']);
            if (is_array($valor)) {
                $this->privadoAusente($valor);
            }
        }
    }

    public function test_tres_formatos_publicos_y_ocultamiento_sin_autenticacion(): void
    {
        $casos = [];
        foreach (['todos_contra_todos', 'eliminacion_directa', 'grupos_eliminacion'] as $formato) {
            $casos[] = $this->preparar($formato);
        }
        $this->invitado();
        foreach ($casos as [$id]) {
            foreach (['', '/deporte', '/equipos', '/partidos', '/estadisticas/goleadores'] as $ruta) {
                $r = $this->getJson('/api/publico/campeonatos/'.$id.$ruta)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
                $this->privadoAusente($r->json());
            }
            Campeonato::findOrFail($id)->forceFill(['publicado' => false])->save();
            foreach (['', '/deporte', '/equipos', '/partidos', '/tabla', '/estadisticas/goleadores'] as $ruta) {
                $this->getJson('/api/publico/campeonatos/'.$id.$ruta)->assertNotFound();
            }
        }
    }

    public function test_cuadro_incompleto_sin_ganadores_inventados_y_sin_rutas_de_escritura(): void
    {
        [$id] = $this->preparar('eliminacion_directa');
        $this->invitado();
        $r = $this->getJson('/api/publico/campeonatos/'.$id.'/deporte')->assertOk();
        $r->assertJsonPath('campeon', null)->assertJsonCount(2, 'fases.0.rondas');
        $r->assertJsonPath('fases.0.rondas.1.llaves.0.ganador_fase_equipo_id', null);
        $this->privadoAusente($r->json());
        $this->postJson('/api/publico/campeonatos/'.$id.'/deporte')->assertStatus(405);
        $this->getJson('/api/campeonatos/'.$id.'/fases')->assertUnauthorized();
    }

    public function test_administrativo_publico_separa_deportivo_y_oculta_motivos(): void
    {
        [$id, $fase, $ids] = $this->preparar('eliminacion_directa');
        $p = Partido::where('campeonato_id', $id)->firstOrFail();
        $p->forceFill(['estado' => 'finalizado', 'tipo_resultado' => 'administrativo', 'oficializado_at' => now(),
            'administrativo_beneficiado_id' => $p->participantes->firstWhere('rol', 'local')->campeonato_equipo_id, 'administrativo_sancionado_id' => $p->participantes->firstWhere('rol', 'visitante')->campeonato_equipo_id, 'administrativo_por_id' => auth()->id(), 'motivo_administrativo' => 'MOTIVO INTERNO'])->save();
        $this->invitado();
        foreach (['/deporte', '/partidos', '/partidos/'.$p->id] as $ruta) {
            $r = $this->getJson('/api/publico/campeonatos/'.$id.$ruta)->assertOk();
            $this->privadoAusente($r->json());
            $this->assertStringNotContainsString('MOTIVO INTERNO', $r->getContent());
        }
        $this->getJson('/api/publico/campeonatos/'.$id.'/deporte')->assertJsonPath('fases.0.rondas.0.llaves.0.partidos.0.marcador.local.goles', 3);
        $this->getJson('/api/publico/campeonatos/'.$id.'/partidos/'.$p->id)->assertJsonPath('marcador_deportivo.local', 0);
        $this->getJson('/api/publico/campeonatos/'.$id.'/estadisticas/goleadores')->assertJsonCount(0, 'resultados');
    }

    public function test_grupos_provisionales_oficiales_y_empate_pendiente(): void
    {
        [$id] = $this->preparar('grupos_eliminacion');
        Partido::where('campeonato_id', $id)->update(['estado' => 'finalizado', 'tipo_resultado' => 'deportivo']);
        $this->invitado();
        $r = $this->getJson('/api/publico/campeonatos/'.$id.'/deporte')->assertOk();
        $r->assertJsonPath('fases.0.clasificacion.clasificacion_vigente', false)->assertJsonCount(0, 'fases.0.clasificacion.candidatos');
        $r->assertJsonPath('fases.0.tablas_provisionales.0.tabla.0.PJ', 1)->assertJsonPath('fases.0.tablas_oficiales.0.tabla.0.PJ', 0);
        Partido::where('campeonato_id', $id)->update(['oficializado_at' => now()]);
        $r = $this->getJson('/api/publico/campeonatos/'.$id.'/deporte')->assertOk();
        $this->assertNotEmpty($r->json('fases.0.tablas_oficiales.0.pendientes_resolucion'));
        $r->assertJsonPath('campeon', null);
        $this->privadoAusente($r->json());
    }

    public function test_campeon_solo_desde_resolucion_del_backend_y_sin_exponer_auditoria(): void
    {
        [$id, , $ids] = $this->preparar('todos_contra_todos');
        $usuario = auth()->id();
        $this->invitado();
        $base = '/api/publico/campeonatos/'.$id.'/deporte';
        $this->getJson($base)->assertOk()->assertJsonPath('campeon', null);
        Campeonato::findOrFail($id)->update(['estado' => 'finalizado']);
        $this->getJson($base)->assertOk()->assertJsonPath('campeon', null);
        AuditoriaCambio::create(['campeonato_id' => $id, 'usuario_id' => $usuario, 'entidad_tipo' => 'campeonato', 'entidad_id' => $id, 'accion' => 'finalizar_campeonato', 'motivo' => 'PRIVADO', 'posterior_json' => ['campeon_campeonato_equipo_id' => $ids[0]], 'fecha' => now()]);
        $r = $this->getJson($base)->assertOk()->assertJsonPath('campeon.nombre', 'Equipo 1');
        $this->privadoAusente($r->json());
    }

    public function test_clasificados_publicos_solo_despues_de_confirmacion_oficial(): void
    {
        [$id, $fase] = $this->preparar('grupos_eliminacion');
        foreach (Partido::where('campeonato_id', $id)->get() as $p) {
            $p->forceFill(['estado' => 'finalizado', 'tipo_resultado' => 'administrativo', 'oficializado_at' => now(),
                'administrativo_beneficiado_id' => $p->participantes->firstWhere('rol', 'local')->campeonato_equipo_id,
                'administrativo_sancionado_id' => $p->participantes->firstWhere('rol', 'visitante')->campeonato_equipo_id,
                'administrativo_por_id' => auth()->id(), 'motivo_administrativo' => 'Reservado'])->save();
        }
        $base = '/api/publico/campeonatos/'.$id.'/deporte';
        $this->getJson($base)->assertOk()->assertJsonCount(0, 'fases.0.clasificacion.candidatos');
        $vista = $this->getJson('/api/fases/'.$fase.'/clasificacion-grupos')->assertOk()->json();
        $this->postJson('/api/fases/'.$fase.'/clasificar-grupos', ['confirmacion' => $vista['confirmacion']])->assertOk();
        $this->invitado();
        $r = $this->getJson($base)->assertOk()->assertJsonPath('fases.0.clasificacion.clasificacion_vigente', true)->assertJsonCount(2, 'fases.0.clasificacion.candidatos');
        $this->privadoAusente($r->json());
    }

    public function test_relacion_de_fase_ajena_no_expone_equipos_privados(): void
    {
        [$id, $fase] = $this->preparar('eliminacion_directa');
        $otro = $this->campeonato('Privado');
        $ajeno = $this->inscripcion($otro['id'], 'NOMBRE RESERVADO');
        Fase::findOrFail($fase)->equipos()->firstOrFail()->update(['campeonato_equipo_id' => $ajeno['id']]);
        $this->invitado();
        $r = $this->getJson('/api/publico/campeonatos/'.$id.'/deporte')->assertNotFound();
        $this->assertStringNotContainsString('NOMBRE RESERVADO', $r->getContent());
    }

    public function test_partido_extra_publico_conserva_penales_sin_expediente_privado(): void
    {
        [$id, $fase] = $this->preparar('grupos_eliminacion');
        Partido::where('campeonato_id', $id)->update(['estado' => 'finalizado', 'tipo_resultado' => 'deportivo', 'oficializado_at' => now()]);
        $grupo = Fase::findOrFail($fase)->grupos()->firstOrFail();
        $tabla = app(TablaGruposService::class)->calcular($grupo, true);
        $servicio = app(DesempateGrupoService::class);
        $clave = $tabla['pendientes_resolucion'][0]['clave'];
        $vista = $servicio->previsualizar($grupo, $clave);
        $caso = $servicio->generar($grupo, $clave, $vista['confirmacion']);
        $caso->llave->update(['penales_plaza_1' => 4, 'penales_plaza_2' => 3]);
        $this->invitado();
        $r = $this->getJson('/api/publico/campeonatos/'.$id.'/deporte')->assertOk();
        $r->assertJsonPath('fases.0.rondas_desempate.0.llaves.0.penales_plaza_1', 4);
        $this->privadoAusente($r->json());
        $this->getJson('/api/publico/campeonatos/'.$id.'/partidos')->assertOk()->assertJsonFragment(['tipo_partido' => 'desempate']);
    }
}
