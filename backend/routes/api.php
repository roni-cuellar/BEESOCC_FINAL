<?php

use App\Http\Controllers\Api\CampeonatoController;
use App\Http\Controllers\Api\CampeonatoEquipoController;
use App\Http\Controllers\Api\ConfiguracionCampeonatoController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\EventoEstadisticoController;
use App\Http\Controllers\Api\JugadorController;
use App\Http\Controllers\Api\PartidoController;
use App\Http\Controllers\Api\PlantillaController;
use App\Http\Controllers\Api\TablaPosicionesController;
use Illuminate\Support\Facades\Route;

Route::get('/campeonatos', [CampeonatoController::class, 'index']);
Route::post('/campeonatos', [CampeonatoController::class, 'store']);
Route::get('/campeonatos/{campeonato}', [CampeonatoController::class, 'show']);
Route::match(['put', 'patch'], '/campeonatos/{campeonato}', [CampeonatoController::class, 'update']);
Route::delete('/campeonatos/{campeonato}', [CampeonatoController::class, 'destroy']);

Route::get('/equipos', [EquipoController::class, 'index']);
Route::post('/equipos', [EquipoController::class, 'store']);
Route::get('/equipos/{equipo}', [EquipoController::class, 'show']);
Route::match(['put', 'patch'], '/equipos/{equipo}', [EquipoController::class, 'update']);
Route::delete('/equipos/{equipo}', [EquipoController::class, 'destroy']);

Route::get('/jugadores', [JugadorController::class, 'index']);
Route::post('/jugadores', [JugadorController::class, 'store']);
Route::get('/jugadores/{jugador}', [JugadorController::class, 'show']);
Route::match(['put', 'patch'], '/jugadores/{jugador}', [JugadorController::class, 'update']);
Route::delete('/jugadores/{jugador}', [JugadorController::class, 'destroy']);

Route::get('/campeonatos/{campeonato}/inscripciones-equipos', [CampeonatoEquipoController::class, 'index']);
Route::post('/campeonatos/{campeonato}/inscripciones-equipos', [CampeonatoEquipoController::class, 'store']);
Route::get('/inscripciones-equipos/{campeonatoEquipo}', [CampeonatoEquipoController::class, 'show']);
Route::patch('/inscripciones-equipos/{campeonatoEquipo}', [CampeonatoEquipoController::class, 'update']);
Route::delete('/inscripciones-equipos/{campeonatoEquipo}', [CampeonatoEquipoController::class, 'destroy']);

Route::get('/inscripciones-equipos/{campeonatoEquipo}/plantillas', [PlantillaController::class, 'index']);
Route::post('/inscripciones-equipos/{campeonatoEquipo}/plantillas', [PlantillaController::class, 'store']);
Route::get('/plantillas/{plantilla}', [PlantillaController::class, 'show']);
Route::patch('/plantillas/{plantilla}', [PlantillaController::class, 'update']);
Route::delete('/plantillas/{plantilla}', [PlantillaController::class, 'destroy']);

Route::get('/campeonatos/{campeonato}/partidos', [PartidoController::class, 'index']);
Route::post('/campeonatos/{campeonato}/partidos', [PartidoController::class, 'store']);
Route::get('/partidos/{partido}', [PartidoController::class, 'show']);
Route::get('/partidos/{partido}/marcador', [PartidoController::class, 'marcador']);
Route::match(['put', 'patch'], '/partidos/{partido}', [PartidoController::class, 'update']);
Route::delete('/partidos/{partido}', [PartidoController::class, 'destroy']);

Route::post('/campeonatos/{campeonato}/generar-calendario', [PartidoController::class, 'generarCalendario']);

Route::get('/partidos/{partido}/eventos', [EventoEstadisticoController::class, 'index']);
Route::post('/partidos/{partido}/eventos', [EventoEstadisticoController::class, 'store']);
Route::get('/eventos/{evento}', [EventoEstadisticoController::class, 'show']);
Route::patch('/eventos/{evento}', [EventoEstadisticoController::class, 'update']);
Route::delete('/eventos/{evento}', [EventoEstadisticoController::class, 'destroy']);

Route::get('/campeonatos/{campeonato}/tabla-posiciones', [TablaPosicionesController::class, 'index']);

Route::get('/campeonatos/{campeonato}/estadisticas/goleadores', [EstadisticaController::class, 'goleadores']);
Route::get('/campeonatos/{campeonato}/estadisticas/asistencias', [EstadisticaController::class, 'asistencias']);
Route::get('/campeonatos/{campeonato}/estadisticas/amarillas', [EstadisticaController::class, 'amarillas']);
Route::get('/campeonatos/{campeonato}/estadisticas/rojas', [EstadisticaController::class, 'rojas']);
Route::get('/campeonatos/{campeonato}/estadisticas/faltas', [EstadisticaController::class, 'faltas']);
Route::get('/campeonatos/{campeonato}/estadisticas/equipos', [EstadisticaController::class, 'equipos']);

Route::get('/campeonatos/{campeonato}/configuracion', [ConfiguracionCampeonatoController::class, 'show']);
Route::post('/campeonatos/{campeonato}/configuracion', [ConfiguracionCampeonatoController::class, 'store']);
Route::patch('/campeonatos/{campeonato}/configuracion', [ConfiguracionCampeonatoController::class, 'update']);
