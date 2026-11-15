<?php

use App\Models\User;
use App\Services\AvanceEliminacionService;
use App\Services\CorreccionPartidoService;
use App\Services\GeneracionEliminacionService;
use App\Services\ResultadoOficialService;
use App\Services\SiembraEliminacionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
try {
    $carpeta = dirname(getenv('BEESOCC_INTEGRAL_CONFIG'));
    $fixture = json_decode(file_get_contents($carpeta.'/sprint42-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
    $admin = User::findOrFail($fixture['admin']['id']);
    $arbitro = User::findOrFail($fixture['arbitro']['id']);
    Auth::shouldUse('web');
    Auth::guard('web')->setUser($admin);
    // Escenario nuevo para no repetir la eliminación completa ni sobrescribir el ensayo anterior.
    $ensayo = bin2hex(random_bytes(4));
    $resultado = DB::transaction(function () use ($fixture, $admin, $arbitro, $ensayo) {
        $campeonato = $admin->campeonatos()->create(['nombre' => 'Carrera nueva 4.2 '.$ensayo, 'temporada' => '2026', 'formato' => 'eliminacion_directa']);
        $campeonato->asignacionesArbitro()->create(['usuario_id' => $arbitro->id, 'autorizado_por' => $admin->id, 'fecha_inicio' => now()]);
        $siembra = [];
        foreach (array_slice($fixture['equipos'], 0, 4) as $equipo) {
            $inscripcion = $campeonato->inscripcionesEquipos()->create(['equipo_id' => $equipo['equipo'], 'estado' => 'activo']);
            $inscripcion->plantillas()->create(['jugador_id' => $equipo['jugador'], 'dorsal' => 1, 'posicion' => 'Delantero', 'fecha_ingreso' => '2026-01-01']);
            $siembra[] = $inscripcion->id;
        }
        $generacion = app(GeneracionEliminacionService::class);
        $fase = $generacion->crearFase($campeonato, 'Carrera');
        $datos = ['siembra' => $siembra, 'rondas' => array_fill(0, 2, ['modalidad' => 'unico', 'desempate' => 'penales'])];
        $vista = app(SiembraEliminacionService::class)->previsualizar($fase, $datos);
        $fase = $generacion->generar($fase, $datos + ['confirmacion' => $vista['confirmacion']]);
        $llaves = $fase->rondas()->first()->llaves()->get();
        foreach ($llaves as $indice => $llave) {
            Auth::guard('web')->setUser($arbitro);
            $partido = $llave->partidos()->first();
            $partido->update(['estado' => 'en_curso']);
            foreach ($partido->participantes as $participante) {
                $cantidad = $participante->rol === 'local' ? ($indice === 0 ? 2 : 1) : ($indice === 0 ? 1 : 0);
                for ($i = 0; $i < $cantidad; $i++) {
                    $participante->eventos()->create(['plantilla_id' => $participante->campeonatoEquipo->plantillas()->sole()->id, 'tipo' => 'gol', 'minuto' => 15]);
                }
            }
            app(ResultadoOficialService::class)->oficializar($partido);
            Auth::guard('web')->setUser($admin);
            if ($indice === 1) {
                app(AvanceEliminacionService::class)->avanzar($llave);
            }
        }
        Auth::guard('web')->setUser($arbitro);
        $partido = $llaves[0]->partidos()->first();
        $correccion = app(CorreccionPartidoService::class)->solicitar($partido, ['motivo' => 'Ensayo nuevo concurrente de clasificado', 'alcance' => 'eventos']);
        Auth::guard('web')->setUser($admin);
        app(CorreccionPartidoService::class)->resolver($correccion, 'aprobada');
        $operaciones = $partido->participantes()->where('rol', 'local')->first()->eventos()->get()->map(fn ($e) => ['accion' => 'eliminar', 'evento_id' => $e->id])->all();

        return ['ensayo' => $ensayo, 'campeonato' => $campeonato->id, 'fase' => $fase->id, 'llave' => $llaves[0]->id, 'otra_llave' => $llaves[1]->id,
            'partido' => $partido->id, 'correccion' => $correccion->id, 'operaciones' => $operaciones, 'admin' => $admin->id, 'arbitro' => $arbitro->id];
    });
    copy($carpeta.'/sprint42-carrera.json', $carpeta.'/sprint42-carrera-anterior-'.$ensayo.'.json');
    file_put_contents($carpeta.'/sprint42-carrera.json', json_encode($resultado, JSON_PRETTY_PRINT));
    echo json_encode(['ok' => true, 'ensayo_nuevo' => $ensayo]);
} catch (Throwable $error) {
    fwrite(STDERR, 'No se preparó el ensayo: '.get_class($error));
    exit(1);
}
