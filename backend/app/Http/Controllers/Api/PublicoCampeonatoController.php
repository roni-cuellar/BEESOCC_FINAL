<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\CampeonatoEquipo;
use App\Models\Partido;
use App\Models\Plantilla;
use App\Services\EstadisticaService;
use App\Services\TablaPosicionesService;
use Illuminate\Http\JsonResponse;

class PublicoCampeonatoController extends Controller
{
    private function campeonato(int $id): Campeonato
    {
        return Campeonato::where('publicado', true)->whereNotNull('administrador_id')->findOrFail($id);
    }

    private function datosCampeonato(Campeonato $campeonato): array
    {
        return $campeonato->only(['id', 'nombre', 'descripcion', 'temporada', 'formato', 'estado']);
    }

    private function datosEquipo(CampeonatoEquipo $inscripcion, Campeonato $campeonato): array
    {
        abort_unless($inscripcion->equipo && (int) $inscripcion->campeonato_id === (int) $campeonato->id
            && (int) $inscripcion->equipo->administrador_id === (int) $campeonato->administrador_id, 404);

        return $inscripcion->equipo->only(['id', 'nombre', 'escudo']);
    }

    private function datosJugador(Plantilla $plantilla, Campeonato $campeonato): array
    {
        $this->datosEquipo($plantilla->campeonatoEquipo, $campeonato);
        abort_unless($plantilla->jugador && (int) $plantilla->jugador->administrador_id === (int) $campeonato->administrador_id, 404);

        return [
            'jugador' => $plantilla->jugador->only(['id', 'nombre', 'apellido']),
            'dorsal' => $plantilla->dorsal,
            'posicion' => $plantilla->posicion,
        ];
    }

    private function datosPartido(Partido $partido, Campeonato $campeonato): array
    {
        $participantes = $partido->participantes->map(fn ($participante) => [
            'rol' => $participante->rol,
            'equipo' => $this->datosEquipo($participante->campeonatoEquipo, $campeonato),
            'goles' => $participante->eventos->whereIn('tipo', ['gol', 'autogol'])->count(),
        ]);

        return $partido->only(['id', 'jornada', 'fecha_hora', 'lugar', 'estado']) + ['participantes' => $participantes];
    }

    private function respuesta(mixed $datos): JsonResponse
    {
        // Evita reutilizar una respuesta guardada después de ocultar el campeonato.
        return response()->json($datos)->header('Cache-Control', 'no-store');
    }

    public function index(): JsonResponse
    {
        return $this->respuesta(Campeonato::where('publicado', true)->whereNotNull('administrador_id')
            ->latest()->get()->map(fn ($campeonato) => $this->datosCampeonato($campeonato)));
    }

    public function show(int $campeonato): JsonResponse
    {
        return $this->respuesta($this->datosCampeonato($this->campeonato($campeonato)));
    }

    public function equipos(int $campeonato): JsonResponse
    {
        $liga = $this->campeonato($campeonato);

        return $this->respuesta($liga->inscripcionesEquipos()->with('equipo')->get()->map(fn ($inscripcion) => [
            'inscripcion_id' => $inscripcion->id,
            'estado' => $inscripcion->estado,
            'equipo' => $this->datosEquipo($inscripcion, $liga),
        ]));
    }

    public function plantillas(int $campeonato, int $inscripcion): JsonResponse
    {
        $liga = $this->campeonato($campeonato);
        $equipo = $liga->inscripcionesEquipos()->findOrFail($inscripcion);
        $this->datosEquipo($equipo, $liga);

        return $this->respuesta($equipo->plantillas()->whereNull('fecha_salida')->with('jugador')->get()
            ->map(fn ($plantilla) => $this->datosJugador($plantilla, $liga)));
    }

    public function partidos(int $campeonato): JsonResponse
    {
        $liga = $this->campeonato($campeonato);

        return $this->respuesta($liga->partidos()->with(['participantes.campeonatoEquipo.equipo', 'participantes.eventos'])
            ->orderBy('jornada')->orderBy('id')->get()->map(fn ($partido) => $this->datosPartido($partido, $liga)));
    }

    public function partido(int $campeonato, int $partido): JsonResponse
    {
        $liga = $this->campeonato($campeonato);

        return $this->respuesta($this->datosPartido($liga->partidos()->findOrFail($partido), $liga));
    }

    public function eventos(int $campeonato, int $partido): JsonResponse
    {
        $liga = $this->campeonato($campeonato);
        $encuentro = $liga->partidos()->findOrFail($partido);
        $this->datosPartido($encuentro, $liga);
        $eventos = [];
        foreach ($encuentro->participantes as $participante) {
            foreach ($participante->eventos as $evento) {
                $jugador = $this->datosJugador($evento->plantilla, $liga);
                abort_unless($encuentro->participantes->contains('campeonato_equipo_id', $evento->plantilla->campeonato_equipo_id), 404);
                $eventos[] = ['tipo' => $evento->tipo, 'minuto' => $evento->minuto, 'rol' => $participante->rol] + $jugador;
            }
        }

        return $this->respuesta(collect($eventos)->sortBy('minuto')->values());
    }

    private function comprobarInscripciones(Campeonato $campeonato): void
    {
        foreach ($campeonato->inscripcionesEquipos()->with(['equipo', 'plantillas.jugador'])->get() as $inscripcion) {
            $this->datosEquipo($inscripcion, $campeonato);
            foreach ($inscripcion->plantillas as $plantilla) {
                $this->datosJugador($plantilla, $campeonato);
            }
        }
    }

    public function tabla(int $campeonato, TablaPosicionesService $tabla): JsonResponse
    {
        $liga = $this->campeonato($campeonato);
        $this->comprobarInscripciones($liga);
        $resultado = $tabla->index($liga);
        if ($resultado->getStatusCode() !== 200) {
            return $this->respuesta(['message' => 'La clasificación no está disponible.'])->setStatusCode(409);
        }
        $datos = $resultado->getData(true);

        return $this->respuesta(['tabla' => collect($datos['tabla'])->map(fn ($fila) => collect($fila)->only([
            'equipo_id', 'nombre', 'PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS', 'posicion',
        ]))]);
    }

    public function estadisticas(int $campeonato, string $tipo, EstadisticaService $estadistica): JsonResponse
    {
        abort_unless(in_array($tipo, ['goleadores', 'asistencias', 'amarillas', 'rojas', 'faltas', 'equipos'], true), 404);
        $liga = $this->campeonato($campeonato);
        $this->comprobarInscripciones($liga);
        $resultado = $estadistica->{$tipo}($liga);
        if ($resultado->getStatusCode() !== 200) {
            return $this->respuesta(['message' => 'Las estadísticas no están disponibles.'])->setStatusCode(409);
        }
        $campos = $tipo === 'equipos' ? ['equipo_id', 'nombre', 'goles', 'amarillas', 'rojas', 'faltas'] : ['jugador_id', 'nombre', 'apellido', 'total'];

        return $this->respuesta(['estadistica' => $tipo, 'resultados' => collect($resultado->getData(true)['resultados'])
            ->map(fn ($fila) => collect($fila)->only($campos))]);
    }
}
