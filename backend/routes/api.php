<?php

use App\Http\Controllers\Api\ArbitrajeController;
use App\Http\Controllers\Api\AsignacionArbitroController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampeonatoController;
use App\Http\Controllers\Api\CampeonatoEquipoController;
use App\Http\Controllers\Api\ConfiguracionCampeonatoController;
use App\Http\Controllers\Api\CorreccionPartidoController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\EventoEstadisticoController;
use App\Http\Controllers\Api\FaseController;
use App\Http\Controllers\Api\GrupoController;
use App\Http\Controllers\Api\JugadorController;
use App\Http\Controllers\Api\PartidoController;
use App\Http\Controllers\Api\PlantillaController;
use App\Http\Controllers\Api\PublicoCampeonatoController;
use App\Http\Controllers\Api\TablaPosicionesController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Estos endpoints siempre necesitan sesión y CSRF, incluso sin Origin.
Route::middleware('web')->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->group(function () {
    Route::post('/registro', [AuthController::class, 'registro'])->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/usuario', [AuthController::class, 'usuario']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/grupos/{grupo}/desempates', [GrupoController::class, 'desempates']);
    Route::post('/fases/{fase}/previsualizar-eliminacion', [FaseController::class, 'previsualizarDesdeGrupos']);
    Route::post('/fases/{fase}/generar-eliminacion', [FaseController::class, 'generarDesdeGrupos']);
    Route::post('/grupos/{grupo}/previsualizar-desempate', [GrupoController::class, 'previsualizarDesempate']);
    Route::post('/grupos/{grupo}/generar-desempate', [GrupoController::class, 'generarDesempate']);
    Route::post('/fases/{fase}/previsualizar-grupos', [GrupoController::class, 'previsualizar']);
    Route::post('/fases/{fase}/confirmar-grupos', [GrupoController::class, 'confirmar']);
    Route::post('/fases/{fase}/generar-calendario-grupos', [GrupoController::class, 'calendario']);
    Route::get('/grupos/{grupo}/tabla', [GrupoController::class, 'tabla']);
    Route::get('/fases/{fase}/clasificacion-grupos', [GrupoController::class, 'clasificacion']);
    Route::post('/fases/{fase}/clasificar-grupos', [GrupoController::class, 'clasificar']);
    Route::get('/campeonatos/{campeonato}/fases', [FaseController::class, 'index']);
    Route::post('/campeonatos/{campeonato}/fases', [FaseController::class, 'store']);
    Route::get('/fases/{fase}', [FaseController::class, 'show']);
    Route::get('/fases/{fase}/auditoria', [FaseController::class, 'auditoria']);
    Route::post('/fases/{fase}/previsualizar-llaves', [FaseController::class, 'previsualizar']);
    Route::post('/fases/{fase}/generar-llaves', [FaseController::class, 'generar']);
    Route::post('/llaves/{llave}/avanzar', [FaseController::class, 'avanzar']);
    Route::post('/llaves/{llave}/penales', [FaseController::class, 'penales']);
    Route::get('/partidos/{partido}/auditoria', [CorreccionPartidoController::class, 'auditoria']);
    Route::get('/partidos/{partido}/correcciones', [CorreccionPartidoController::class, 'index']);
    Route::post('/partidos/{partido}/correcciones', [CorreccionPartidoController::class, 'store']);
    Route::post('/partidos/{partido}/finalizar', [PartidoController::class, 'finalizar']);
    Route::patch('/correcciones/{correccion}/autorizar', [CorreccionPartidoController::class, 'resolver']);
    Route::post('/correcciones/{correccion}/aplicar', [CorreccionPartidoController::class, 'aplicar']);
    Route::get('/mis-arbitrajes/{campeonato}/partidos', [ArbitrajeController::class, 'partidos']);
    Route::get('/partidos/{partido}/plantillas-arbitrales', [ArbitrajeController::class, 'plantillas']);
    Route::get('/mis-arbitrajes', [AsignacionArbitroController::class, 'misArbitrajes']);
    Route::post('/campeonatos/{campeonato}/arbitro/buscar-usuario', [AsignacionArbitroController::class, 'buscarUsuario'])->middleware('throttle:10,1');
    Route::get('/campeonatos/{campeonato}/arbitro', [AsignacionArbitroController::class, 'vigente']);
    Route::get('/campeonatos/{campeonato}/arbitro/historial', [AsignacionArbitroController::class, 'historial']);
    Route::post('/campeonatos/{campeonato}/arbitro', [AsignacionArbitroController::class, 'store']);
    Route::patch('/campeonatos/{campeonato}/arbitro', [AsignacionArbitroController::class, 'update']);
    Route::delete('/campeonatos/{campeonato}/arbitro', [AsignacionArbitroController::class, 'revocar']);
    Route::patch('/campeonatos/{campeonato}/publicacion', [CampeonatoController::class, 'publicacion']);
    Route::get('/campeonatos', [CampeonatoController::class, 'index']);
    Route::post('/campeonatos', [CampeonatoController::class, 'store']);
    Route::get('/campeonatos/{campeonato}', [CampeonatoController::class, 'show']);
    Route::match(['put', 'patch'], '/campeonatos/{campeonato}', [CampeonatoController::class, 'update']);
    Route::delete('/campeonatos/{campeonato}', [CampeonatoController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/equipos', [EquipoController::class, 'index']);
    Route::post('/equipos', [EquipoController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/equipos/{equipo}', [EquipoController::class, 'show']);
    Route::match(['put', 'patch'], '/equipos/{equipo}', [EquipoController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/equipos/{equipo}', [EquipoController::class, 'destroy'])->middleware('auth:sanctum');

    Route::get('/jugadores', [JugadorController::class, 'index']);
    Route::post('/jugadores', [JugadorController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/jugadores/{jugador}', [JugadorController::class, 'show']);
    Route::match(['put', 'patch'], '/jugadores/{jugador}', [JugadorController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/jugadores/{jugador}', [JugadorController::class, 'destroy'])->middleware('auth:sanctum');

    Route::get('/campeonatos/{campeonato}/inscripciones-equipos', [CampeonatoEquipoController::class, 'index']);
    Route::post('/campeonatos/{campeonato}/inscripciones-equipos', [CampeonatoEquipoController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/inscripciones-equipos/{campeonatoEquipo}', [CampeonatoEquipoController::class, 'show']);
    Route::patch('/inscripciones-equipos/{campeonatoEquipo}', [CampeonatoEquipoController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/inscripciones-equipos/{campeonatoEquipo}', [CampeonatoEquipoController::class, 'destroy'])->middleware('auth:sanctum');

    Route::get('/inscripciones-equipos/{campeonatoEquipo}/plantillas', [PlantillaController::class, 'index']);
    Route::post('/inscripciones-equipos/{campeonatoEquipo}/plantillas', [PlantillaController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/plantillas/{plantilla}', [PlantillaController::class, 'show']);
    Route::patch('/plantillas/{plantilla}', [PlantillaController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/plantillas/{plantilla}', [PlantillaController::class, 'destroy'])->middleware('auth:sanctum');

    Route::get('/campeonatos/{campeonato}/partidos', [PartidoController::class, 'index']);
    Route::post('/campeonatos/{campeonato}/partidos', [PartidoController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/partidos/{partido}', [PartidoController::class, 'show']);
    Route::get('/partidos/{partido}/marcador', [PartidoController::class, 'marcador']);
    Route::match(['put', 'patch'], '/partidos/{partido}', [PartidoController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/partidos/{partido}', [PartidoController::class, 'destroy'])->middleware('auth:sanctum');

    Route::post('/campeonatos/{campeonato}/generar-calendario', [PartidoController::class, 'generarCalendario'])->middleware('auth:sanctum');

    Route::get('/partidos/{partido}/eventos', [EventoEstadisticoController::class, 'index']);
    Route::post('/partidos/{partido}/eventos', [EventoEstadisticoController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/eventos/{evento}', [EventoEstadisticoController::class, 'show']);
    Route::patch('/eventos/{evento}', [EventoEstadisticoController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/eventos/{evento}', [EventoEstadisticoController::class, 'destroy'])->middleware('auth:sanctum');

    Route::get('/campeonatos/{campeonato}/tabla-posiciones', [TablaPosicionesController::class, 'index']);

    Route::get('/campeonatos/{campeonato}/estadisticas/goleadores', [EstadisticaController::class, 'goleadores']);
    Route::get('/campeonatos/{campeonato}/estadisticas/asistencias', [EstadisticaController::class, 'asistencias']);
    Route::get('/campeonatos/{campeonato}/estadisticas/amarillas', [EstadisticaController::class, 'amarillas']);
    Route::get('/campeonatos/{campeonato}/estadisticas/rojas', [EstadisticaController::class, 'rojas']);
    Route::get('/campeonatos/{campeonato}/estadisticas/faltas', [EstadisticaController::class, 'faltas']);
    Route::get('/campeonatos/{campeonato}/estadisticas/equipos', [EstadisticaController::class, 'equipos']);

    Route::get('/campeonatos/{campeonato}/configuracion', [ConfiguracionCampeonatoController::class, 'show']);
    Route::post('/campeonatos/{campeonato}/configuracion', [ConfiguracionCampeonatoController::class, 'store'])->middleware('auth:sanctum');
    Route::patch('/campeonatos/{campeonato}/configuracion', [ConfiguracionCampeonatoController::class, 'update'])->middleware('auth:sanctum');

});

Route::prefix('publico/campeonatos')->where(['campeonato' => '[0-9]+', 'partido' => '[0-9]+', 'inscripcion' => '[0-9]+'])->group(function () {
    Route::get('/', [PublicoCampeonatoController::class, 'index']);
    Route::get('/{campeonato}', [PublicoCampeonatoController::class, 'show']);
    Route::get('/{campeonato}/equipos', [PublicoCampeonatoController::class, 'equipos']);
    Route::get('/{campeonato}/equipos/{inscripcion}/plantilla', [PublicoCampeonatoController::class, 'plantillas']);
    Route::get('/{campeonato}/partidos', [PublicoCampeonatoController::class, 'partidos']);
    Route::get('/{campeonato}/partidos/{partido}', [PublicoCampeonatoController::class, 'partido']);
    Route::get('/{campeonato}/partidos/{partido}/eventos', [PublicoCampeonatoController::class, 'eventos']);
    Route::get('/{campeonato}/tabla', [PublicoCampeonatoController::class, 'tabla']);
    Route::get('/{campeonato}/estadisticas/{tipo}', [PublicoCampeonatoController::class, 'estadisticas']);
});
