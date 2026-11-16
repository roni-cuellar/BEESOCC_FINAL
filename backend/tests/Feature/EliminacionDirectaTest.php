<?php

namespace Tests\Feature;

use App\Models\AuditoriaCambio;
use App\Models\CampeonatoEquipo;
use App\Models\Fase;
use App\Models\Llave;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EliminacionDirectaTest extends BackendTestCase
{
    private array $plantillas = [];

    private function torneo(int $cantidad = 4, string $modalidad = 'unico', string $desempate = 'penales'): array
    {
        $campeonato = $this->postJson('/api/campeonatos', ['nombre' => 'Copa eliminatoria', 'temporada' => '2026', 'formato' => 'eliminacion_directa'])->assertCreated()->json('campeonato');
        $siembra = [];
        for ($i = 1; $i <= $cantidad; $i++) {
            $inscripcion = $this->inscripcion($campeonato['id'], 'Equipo '.$i);
            $siembra[] = $inscripcion['id'];
            $this->plantillas[$inscripcion['id']] = $this->plantilla($inscripcion['id']);
        }
        $fase = $this->postJson('/api/campeonatos/'.$campeonato['id'].'/fases', ['nombre' => 'Eliminación', 'tipo' => 'eliminacion'])->assertCreated()->json('fase');
        $numeroRondas = (int) ceil(log($cantidad, 2));
        $datos = ['siembra' => $siembra, 'rondas' => array_fill(0, $numeroRondas, compact('modalidad', 'desempate'))];
        $vista = $this->postJson('/api/fases/'.$fase['id'].'/previsualizar-llaves', $datos)->assertOk()->json();

        return compact('campeonato', 'fase', 'datos', 'vista', 'siembra');
    }

    private function generar(array $torneo): Fase
    {
        $this->postJson('/api/fases/'.$torneo['fase']['id'].'/generar-llaves', $torneo['datos'] + ['confirmacion' => $torneo['vista']['confirmacion']])->assertCreated();

        return Fase::findOrFail($torneo['fase']['id']);
    }

    private function oficializar(Partido $partido, int $local = 1, int $visitante = 0): void
    {
        $this->solicitudArbitral('patch', '/api/partidos/'.$partido->id, ['estado' => 'en_curso'])->assertOk();
        $datos = $this->getJson('/api/partidos/'.$partido->id)->assertOk()->json();
        foreach (['local' => $local, 'visitante' => $visitante] as $rol => $cantidad) {
            $participante = $partido->participantes()->where('rol', $rol)->firstOrFail();
            for ($i = 0; $i < $cantidad; $i++) {
                $this->evento($datos, $participante->id, $this->plantillas[$participante->campeonato_equipo_id]['id']);
            }
        }
        $this->solicitudArbitral('post', '/api/partidos/'.$partido->id.'/finalizar')->assertOk();
    }

    private function entrar(User $usuario): void
    {
        app('auth')->forgetGuards();
        $this->actingAs($usuario, 'web');
    }

    private function correccion(Partido $partido): array
    {
        $admin = auth('web')->user();
        $arbitro = $partido->campeonato->asignacionesArbitro()->whereNull('fecha_fin')->firstOrFail()->usuario;
        $this->entrar($arbitro);
        $solicitud = $this->postJson('/api/partidos/'.$partido->id.'/correcciones', ['motivo' => 'Revisión del gol eliminatorio', 'alcance' => 'eventos'])->assertCreated()->json('correccion');
        $this->entrar($admin);
        $this->patchJson('/api/correcciones/'.$solicitud['id'].'/autorizar', ['estado' => 'aprobada'])->assertOk();
        $this->entrar($arbitro);

        return $solicitud;
    }

    public function test_cuadro_de_seis_da_descanso_a_primeras_siembras_sin_partidos_ficticios(): void
    {
        $e = $this->torneo(6);
        $this->assertSame([$e['siembra'][0], null], $e['vista']['cruces'][0]['inscripciones']);
        $this->assertSame([$e['siembra'][3], $e['siembra'][4]], $e['vista']['cruces'][1]['inscripciones']);
        $this->assertSame([$e['siembra'][1], null], $e['vista']['cruces'][2]['inscripciones']);
        $fase = $this->generar($e);
        $this->assertSame(3, $fase->rondas()->count());
        $this->assertSame(7, Llave::count());
        $this->assertSame(14, DB::table('llave_plazas')->count());
        $this->assertSame(2, Llave::where('metodo_resolucion', 'bye')->count());
        $this->assertSame(2, Partido::count());
        $this->assertSame(0, DB::table('eventos_estadisticos')->count());
        $this->postJson('/api/fases/'.$fase->id.'/generar-llaves', $e['datos'] + ['confirmacion' => $e['vista']['confirmacion']])->assertConflict();
        $this->assertSame(2, Partido::count());
        $this->getJson('/api/fases/'.$fase->id.'/auditoria')->assertOk();
    }

    public function test_avance_hasta_la_final_conserva_partidos_y_no_se_duplica(): void
    {
        $fase = $this->generar($this->torneo());
        foreach ($fase->rondas()->first()->llaves as $llave) {
            $this->oficializar($llave->partidos()->first());
            $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertOk()->assertJsonPath('llave.estado', 'resuelta');
            $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertConflict();
        }
        $final = $fase->rondas()->where('numero', 2)->first()->llaves()->first();
        $this->assertSame(3, Partido::count());
        $this->oficializar($final->partidos()->first());
        $this->postJson('/api/llaves/'.$final->id.'/avanzar')->assertOk();
        $this->assertSame('finalizada', $fase->refresh()->estado);
        $this->assertSame(3, Partido::count());
        $this->assertSame(3, AuditoriaCambio::where('accion', 'resolver_llave')->count());
    }

    public function test_no_clasifica_sin_resultado_oficial_ni_historico_finalizado(): void
    {
        $fase = $this->generar($this->torneo(2));
        $llave = $fase->rondas()->first()->llaves()->first();
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertConflict();
        $partido = $llave->partidos()->first();
        DB::table('partidos')->where('id', $partido->id)->update(['estado' => 'finalizado']);
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertConflict();
        $this->assertNull($llave->refresh()->ganador_fase_equipo_id);
    }

    public function test_ida_y_vuelta_suma_por_equipo_sin_gol_visitante_y_resuelve_penales(): void
    {
        $fase = $this->generar($this->torneo(2, 'ida_vuelta', 'prorroga_penales'));
        $llave = $fase->rondas()->first()->llaves()->first();
        [$ida, $vuelta] = $llave->partidos()->get()->all();
        $this->oficializar($ida, 1, 0);
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertConflict();
        $this->oficializar($vuelta, 2, 1);
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertConflict();
        $admin = auth('web')->user();
        $arbitro = $ida->campeonato->asignacionesArbitro()->whereNull('fecha_fin')->firstOrFail()->usuario;
        $datos = ['penales_plaza_1' => 4, 'penales_plaza_2' => 3, 'motivo' => 'Prórroga disputada; empate global y tanda certificada.'];
        $this->postJson('/api/llaves/'.$llave->id.'/penales', $datos)->assertForbidden();
        $this->entrar($arbitro);
        $this->postJson('/api/llaves/'.$llave->id.'/penales', $datos)->assertOk();
        $this->postJson('/api/llaves/'.$llave->id.'/penales', $datos)->assertConflict();
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertForbidden();
        $this->entrar($admin);
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertOk();
        $this->assertSame('prorroga_penales', $llave->refresh()->metodo_resolucion);
        $this->assertEquals($llave->plazas()->first()->fase_equipo_id, $llave->ganador_fase_equipo_id);
        $this->assertSame(4, DB::table('eventos_estadisticos')->count());
    }

    public function test_siembras_ajenas_duplicadas_y_confirmacion_alterada_no_escriben(): void
    {
        $e = $this->torneo();
        $ruta = '/api/fases/'.$e['fase']['id'];
        $this->postJson($ruta.'/generar-llaves', $e['datos'] + ['confirmacion' => str_repeat('0', 64)])->assertConflict();
        $datos = $e['datos'];
        $datos['siembra'][0] = $datos['siembra'][1];
        $this->postJson($ruta.'/previsualizar-llaves', $datos)->assertUnprocessable();
        $datos['siembra'][0] = 999999;
        $this->postJson($ruta.'/previsualizar-llaves', $datos)->assertUnprocessable();
        $this->assertSame(0, Llave::count());
        $this->assertSame(0, Partido::count());
    }

    public function test_separacion_de_permisos_consulta_privada_y_revocacion(): void
    {
        $admin = auth('web')->user();
        $e = $this->torneo(2);
        $fase = $this->generar($e);
        $arbitro = User::factory()->create();
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/arbitro', ['usuario_id' => $arbitro->id])->assertCreated();
        $this->entrar($arbitro);
        $this->getJson('/api/fases/'.$fase->id)->assertOk();
        $this->getJson('/api/campeonatos/'.$e['campeonato']['id'].'/fases')->assertOk();
        $this->postJson('/api/fases/'.$fase->id.'/generar-llaves', $e['datos'])->assertForbidden();
        $this->entrar(User::factory()->create());
        $this->getJson('/api/fases/'.$fase->id)->assertForbidden();
        $this->getJson('/api/fases/'.$fase->id.'/auditoria')->assertForbidden();
        $this->entrar($admin);
        $this->deleteJson('/api/campeonatos/'.$e['campeonato']['id'].'/arbitro', ['motivo_revocacion' => 'Fin de asignación'])->assertOk();
        $this->entrar($arbitro);
        $this->getJson('/api/fases/'.$fase->id)->assertForbidden();
        app('auth')->forgetGuards();
        auth('web')->logout();
        $this->getJson('/api/fases/'.$fase->id)->assertUnauthorized();
    }

    public function test_correccion_propaga_otro_ganador_sin_cambiar_id_del_partido_programado(): void
    {
        $fase = $this->generar($this->torneo());
        $primera = $fase->rondas()->first()->llaves()->get();
        foreach ($primera as $llave) {
            $this->oficializar($llave->partidos()->first(), 2, 1);
            $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertOk();
        }
        $final = $fase->rondas()->where('numero', 2)->first()->llaves()->first()->partidos()->first();
        $localAnterior = $final->participantes()->where('rol', 'local')->first()->campeonato_equipo_id;
        $partido = $primera[0]->partidos()->first();
        $solicitud = $this->correccion($partido);
        $local = $partido->participantes()->where('rol', 'local')->first();
        $operaciones = $local->eventos()->get()->map(fn ($evento) => ['accion' => 'eliminar', 'evento_id' => $evento->id])->all();
        $this->postJson('/api/correcciones/'.$solicitud['id'].'/aplicar', compact('operaciones'))->assertOk();
        $this->assertNotEquals($localAnterior, $final->participantes()->where('rol', 'local')->first()->campeonato_equipo_id);
        $this->assertSame(3, Partido::count());
        $this->assertDatabaseHas('partidos', ['id' => $final->id, 'estado' => 'programado']);
        $this->assertSame(1, AuditoriaCambio::where('accion', 'actualizar_clasificado')->count());
    }

    public function test_correccion_que_afecta_ronda_iniciada_revierte_eventos_y_consumo(): void
    {
        $fase = $this->generar($this->torneo());
        $primera = $fase->rondas()->first()->llaves()->get();
        foreach ($primera as $llave) {
            $this->oficializar($llave->partidos()->first(), 2, 1);
            $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertOk();
        }
        $final = $fase->rondas()->where('numero', 2)->first()->llaves()->first()->partidos()->first();
        $this->solicitudArbitral('patch', '/api/partidos/'.$final->id, ['estado' => 'en_curso'])->assertOk();
        $partido = $primera[0]->partidos()->first();
        $solicitud = $this->correccion($partido);
        $eventos = $partido->participantes()->where('rol', 'local')->first()->eventos()->pluck('id');
        $antes = AuditoriaCambio::count();
        $operaciones = $eventos->map(fn ($id) => ['accion' => 'eliminar', 'evento_id' => $id])->all();
        $this->postJson('/api/correcciones/'.$solicitud['id'].'/aplicar', compact('operaciones'))->assertConflict();
        $this->assertSame(2, DB::table('eventos_estadisticos')->whereIn('id', $eventos)->count());
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $solicitud['id'], 'estado' => 'aprobada', 'fecha_consumo' => null]);
        $this->assertSame($antes, AuditoriaCambio::count());
    }

    public function test_no_admite_edicion_de_plazas_partidos_sueltos_ni_borrado_del_cuadro(): void
    {
        $e = $this->torneo(2);
        $fase = $this->generar($e);
        $partido = $fase->rondas()->first()->llaves()->first()->partidos()->first();
        $this->deleteJson('/api/partidos/'.$partido->id)->assertConflict();
        $this->patchJson('/api/partidos/'.$partido->id, ['local_id' => $e['siembra'][1], 'visitante_id' => $e['siembra'][0]])->assertConflict();
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/partidos', ['jornada' => 1, 'local_id' => $e['siembra'][0], 'visitante_id' => $e['siembra'][1]])->assertConflict();
        $this->patchJson('/api/campeonatos/'.$e['campeonato']['id'], ['formato' => 'todos_contra_todos'])->assertConflict();
        $this->getJson('/api/campeonatos/'.$e['campeonato']['id'].'/tabla-posiciones')->assertConflict();
    }

    public function test_penales_requieren_empate_oficial_y_totales_distintos(): void
    {
        $fase = $this->generar($this->torneo(2));
        $llave = $fase->rondas()->first()->llaves()->first();
        $partido = $llave->partidos()->first();
        $this->oficializar($partido, 0, 0);
        $arbitro = $partido->campeonato->asignacionesArbitro()->whereNull('fecha_fin')->firstOrFail()->usuario;
        $this->entrar($arbitro);
        $this->postJson('/api/llaves/'.$llave->id.'/penales', ['penales_plaza_1' => 4, 'penales_plaza_2' => 4, 'motivo' => 'Tanda'])->assertUnprocessable();
        $this->postJson('/api/llaves/'.$llave->id.'/penales', ['penales_plaza_1' => 256, 'penales_plaza_2' => 3, 'motivo' => 'Tanda'])->assertUnprocessable();
        $this->postJson('/api/llaves/'.$llave->id.'/penales', ['penales_plaza_1' => 4, 'penales_plaza_2' => 3, 'motivo' => 'Tanda certificada'])->assertOk();
        $this->assertSame(0, DB::table('eventos_estadisticos')->count());
        $this->assertSame(1, AuditoriaCambio::where('accion', 'registrar_penales')->count());
    }

    public function test_tres_equipos_generan_un_descanso_y_los_retiros_bloquean_el_avance(): void
    {
        $e = $this->torneo(3);
        $fase = $this->generar($e);
        $this->assertSame(1, Llave::where('metodo_resolucion', 'bye')->count());
        $llave = $fase->rondas()->first()->llaves()->where('estado', 'pendiente')->first();
        $partido = $llave->partidos()->first();
        $this->oficializar($partido);
        $inscripcion = $partido->participantes()->where('rol', 'visitante')->first()->campeonato_equipo_id;
        $this->patchJson('/api/inscripciones-equipos/'.$inscripcion, ['estado' => 'retirado'])->assertConflict();
        // Simula un retiro legado para conservar la prueba del bloqueo del motor.
        CampeonatoEquipo::findOrFail($inscripcion)->update(['estado' => 'retirado']);
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertConflict();
        $this->assertSame('pendiente', $llave->refresh()->estado);
        $this->assertSame(1, Partido::count());
    }

    public function test_confirmacion_obsoleta_fase_repetida_y_grupos_no_se_admiten(): void
    {
        $e = $this->torneo(2);
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/fases', ['tipo' => 'eliminacion', 'nombre' => 'Duplicada'])->assertConflict();
        $this->postJson('/api/campeonatos/'.$e['campeonato']['id'].'/fases', ['tipo' => 'grupos', 'nombre' => 'Grupos'])->assertUnprocessable();
        $this->inscripcion($e['campeonato']['id'], 'Inscripción posterior a la vista previa');
        $this->postJson('/api/fases/'.$e['fase']['id'].'/generar-llaves', $e['datos'] + ['confirmacion' => $e['vista']['confirmacion']])->assertUnprocessable();
        $this->assertSame(0, Partido::count());
        $this->assertSame(0, DB::table('grupos')->count());
    }

    public function test_no_recibe_ganador_del_cliente_ni_ubicacion_de_partido_manipulada(): void
    {
        $fase = $this->generar($this->torneo(2));
        $llave = $fase->rondas()->first()->llaves()->first();
        $partido = $llave->partidos()->first();
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar', ['ganador_fase_equipo_id' => 999])->assertUnprocessable();
        $this->patchJson('/api/partidos/'.$partido->id, ['llave_id' => 999])->assertUnprocessable();
        $equipo = $this->equipo('Inscripción no permitida');
        $this->postJson('/api/campeonatos/'.$fase->campeonato_id.'/inscripciones-equipos', ['equipo_id' => $equipo['id']])->assertConflict();
    }

    public function test_correccion_que_deja_empate_rechaza_todo_y_conserva_el_clasificado(): void
    {
        $fase = $this->generar($this->torneo(2));
        $llave = $fase->rondas()->first()->llaves()->first();
        $partido = $llave->partidos()->first();
        $this->oficializar($partido);
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertOk();
        $ganador = $llave->refresh()->ganador_fase_equipo_id;
        $solicitud = $this->correccion($partido);
        $evento = $partido->participantes()->where('rol', 'local')->first()->eventos()->first();
        $this->postJson('/api/correcciones/'.$solicitud['id'].'/aplicar', ['operaciones' => [['accion' => 'eliminar', 'evento_id' => $evento->id]]])->assertConflict();
        $this->assertSame($ganador, $llave->refresh()->ganador_fase_equipo_id);
        $this->assertDatabaseHas('eventos_estadisticos', ['id' => $evento->id]);
        $this->assertDatabaseHas('autorizaciones_correccion', ['id' => $solicitud['id'], 'estado' => 'aprobada']);
    }

    public function test_listas_con_indices_arbitrarios_y_participantes_ajenos_se_rechazan(): void
    {
        $e = $this->torneo(2);
        $datos = $e['datos'];
        $datos['rondas'] = [4 => $datos['rondas'][0]];
        $this->postJson('/api/fases/'.$e['fase']['id'].'/previsualizar-llaves', $datos)->assertUnprocessable();
        $fase = $this->generar($e);
        $llave = $fase->rondas()->first()->llaves()->first();
        $this->oficializar($llave->partidos()->first());
        $otro = $this->inscripcion($this->campeonato()['id'], 'Ajeno al cuadro');
        $participante = $llave->partidos()->first()->participantes()->first();
        DB::table('equipo_partido')->where('id', $participante->id)->update(['campeonato_equipo_id' => $otro['id']]);
        $this->postJson('/api/llaves/'.$llave->id.'/avanzar')->assertConflict();
        $this->assertSame('pendiente', $llave->refresh()->estado);
    }

    public function test_migracion_aditiva_conserva_un_partido_historico_y_no_le_asigna_fase(): void
    {
        $anterior = DB::getDefaultConnection();
        config(['database.connections.legado_eliminacion' => config('database.connections.sqlite')]);
        DB::setDefaultConnection('legado_eliminacion');
        try {
            $nueva = database_path('migrations/2026_11_15_000100_create_estructura_eliminacion.php');
            foreach (glob(database_path('migrations/*.php')) as $archivo) {
                if (basename($archivo) < basename($nueva)) {
                    (require $archivo)->up();
                }
            }
            $campeonato = DB::table('campeonatos')->insertGetId(['nombre' => 'Histórico', 'temporada' => '2025', 'formato' => 'todos_contra_todos']);
            $id = DB::table('partidos')->insertGetId(['campeonato_id' => $campeonato, 'jornada' => 8, 'estado' => 'finalizado', 'lugar' => 'Sede histórica']);
            $antes = (array) DB::table('partidos')->find($id);
            (require $nueva)->up();
            $despues = (array) DB::table('partidos')->find($id);
            foreach ($antes as $campo => $valor) {
                $this->assertSame($valor, $despues[$campo], $campo);
            }
            $this->assertNull($despues['fase_id']);
            $this->assertNull($despues['llave_id']);
            $this->assertSame(0, DB::table('fases')->count());
        } finally {
            DB::disconnect('legado_eliminacion');
            DB::setDefaultConnection($anterior);
        }
    }
}
