<?php

use App\Http\Controllers\Api\PartidoController;
use App\Models\Campeonato;
use App\Models\EventoEstadistico;
use App\Models\User;
use App\Services\EstadisticaService;
use App\Services\EventoEstadisticoService;
use App\Services\TablaPosicionesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/entorno.php';
iniciarEntornoIntegral();
try {
    $http = json_decode(file_get_contents(__DIR__.'/sprint42-http.json'), true, flags: JSON_THROW_ON_ERROR);
    $fixture = json_decode(file_get_contents(dirname(getenv('BEESOCC_INTEGRAL_CONFIG')).'/sprint42-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($http['base'] !== DB::connection()->getDatabaseName()) {
        throw new RuntimeException('La evidencia corresponde a otro laboratorio.');
    }
    $completo = Campeonato::findOrFail($http['campeonato_completo']);
    $fase = $completo->fases()->sole();
    $goles = app(EstadisticaService::class)->goleadores($completo)->getData(true)['resultados'];
    if ($fase->estado !== 'finalizada' || $fase->rondas()->where('estado', '!=', 'finalizada')->exists()
        || $completo->partidos()->count() !== 7 || array_sum(array_column($goles, 'total')) !== 7) {
        throw new RuntimeException('Cuadro completo o estadística de goles incorrectos.');
    }
    $golesDeJuego = EventoEstadistico::whereHas('equipoPartido.partido', fn ($q) => $q->where('campeonato_id', $completo->id))->count();
    if ($golesDeJuego !== 7 || DB::table('grupos')->count() !== 0) {
        throw new RuntimeException('La tanda produjo eventos ficticios o se activaron grupos.');
    }
    $liga = Campeonato::findOrFail($http['campeonato_liga']);
    $partido = $liga->partidos()->orderBy('id')->first();
    if ($partido->estado !== 'programado') {
        throw new RuntimeException('La verificación de liga ya fue ejecutada.');
    }
    Auth::shouldUse('web');
    Auth::guard('web')->setUser(User::findOrFail($fixture['arbitro']['id']));
    DB::transaction(function () use ($partido) {
        app(PartidoController::class)->cambiarEstado(Request::create('/api/partidos/'.$partido->id, 'PATCH', ['estado' => 'en_curso']), $partido);
        $local = $partido->participantes()->where('rol', 'local')->firstOrFail();
        $servicio = app(EventoEstadisticoService::class);
        $datos = $servicio->validar(['equipo_partido_id' => $local->id, 'plantilla_id' => $local->campeonatoEquipo->plantillas()->sole()->id, 'tipo' => 'gol', 'minuto' => 20]);
        $servicio->registrar($partido->refresh(), $datos);
        app(PartidoController::class)->finalizar(Request::create('/api/partidos/'.$partido->id.'/finalizar', 'POST'), $partido->refresh());
    });
    $tabla = app(TablaPosicionesService::class)->index($liga)->getData(true)['tabla'];
    $goleadores = app(EstadisticaService::class)->goleadores($liga)->getData(true)['resultados'];
    if (count($tabla) !== 3 || (int) $tabla[0]['PTS'] !== 3 || (int) $tabla[0]['GF'] !== 1
        || array_sum(array_column($goleadores, 'total')) !== 1 || $liga->partidos()->count() !== 3
        || $liga->partidos()->whereNotNull('fase_id')->exists() || $liga->fases()->exists()) {
        throw new RuntimeException('Regresión de liga con partidos reales.');
    }
    $resultado = ['ok' => true, 'base' => DB::connection()->getDatabaseName(), 'tipo' => 'Servicios y controlador Laravel sobre MariaDB real',
        'eliminacion' => ['finalizada' => true, 'partidos' => 7, 'eventos_y_goles_individuales' => 7, 'penales_sin_goles_ficticios' => true],
        'liga' => ['partidos' => 3, 'equipos_en_tabla' => 3, 'puntos_ganador' => 3, 'goles_ganador' => 1, 'fase_id_permanece_null' => true], 'grupos_sin_activar' => true];
    file_put_contents(__DIR__.'/sprint42-compatibilidad.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, 'Falló compatibilidad: '.$error->getMessage());
    exit(1);
}
