<?php

namespace App\Services;

use App\Models\Partido;
use App\Models\Plantilla;
use Illuminate\Support\Str;

class PanelArbitralService
{
    private function limites(Partido $partido): array
    {
        $configuracion = $partido->campeonato->configuracion;
        $modalidad = Str::lower(Str::ascii(trim($configuracion?->modalidad ?? '')));

        return [
            'minimo' => max(1, $configuracion?->minimo_jugadores ?? 1),
            'maximo' => ['futbol 5' => 5, 'futbol 7' => 7, 'futbol 11' => 11][$modalidad] ?? null,
        ];
    }

    private function plantillas(Partido $partido, bool $incluirTitulares = false): array
    {
        $fecha = ($partido->fecha_hora ?? now())->toDateString();
        $propietario = (int) $partido->campeonato->administrador_id;
        $elegibles = [];
        foreach ($partido->participantes as $participante) {
            $inscripcion = $participante->campeonatoEquipo;
            $guardados = $incluirTitulares ? ($partido->titulares[$participante->rol] ?? []) : [];
            $elegibles[$participante->rol] = $inscripcion->plantillas()
                ->with('jugador:id,nombre,apellido,foto,administrador_id')->orderBy('dorsal')->get()
                ->filter(fn (Plantilla $p) => $p->jugador && (int) $p->jugador->administrador_id === $propietario
                    && (($inscripcion->estado === 'activo' && $p->estaVigenteEn($fecha)) || in_array($p->id, $guardados, true)))->values();
        }

        return $elegibles;
    }

    private function comprobarTitulares(Partido $partido, array $titulares, bool $completos): void
    {
        ['minimo' => $minimo, 'maximo' => $maximo] = $this->limites($partido);
        $elegibles = $this->plantillas($partido);
        $jugadores = [];
        foreach ($titulares as $rol => $ids) {
            $cantidad = count($ids);
            abort_if($maximo !== null && $cantidad > $maximo, 422, 'La selección supera el máximo de titulares de la modalidad.');
            abort_if($completos && $cantidad < $minimo, 422, "Selecciona al menos {$minimo} titulares elegibles por equipo.");
            $plantillas = $elegibles[$rol]->whereIn('id', $ids);
            abort_unless($plantillas->count() === $cantidad, 422, 'Todos los titulares deben estar inscritos y ser elegibles para su equipo en la fecha del partido.');
            $jugadores = [...$jugadores, ...$plantillas->pluck('jugador_id')];
        }
        abort_unless(count(array_unique($jugadores)) === count($jugadores), 422, 'Un jugador solo puede aparecer una vez entre los titulares.');
    }

    public function comprobarVersion(Partido $partido, int $version): void
    {
        abort_unless($partido->version_panel === $version, 409, 'El panel cambió en otra consulta. Revisa el estado actualizado.');
    }

    public function preparar(Partido $partido, array $datos): void
    {
        $this->comprobarVersion($partido, $datos['version']);
        abort_unless($partido->estado === 'programado', 409, 'Solo se puede preparar un partido programado.');
        $titulares = array_map(fn ($ids) => array_map('intval', $ids), $datos['titulares']);
        $this->comprobarTitulares($partido, $titulares, false);
        $partido->duracion_minutos = $datos['duracion_minutos'];
        $partido->titulares = $titulares;
        $partido->version_panel++;
        $partido->save();
    }

    private function segundos(Partido $partido, int $ahora): int
    {
        $tramo = $partido->reloj_desde ? max(0, $ahora - $partido->reloj_desde->timestamp) : 0;

        return min($partido->duracion_minutos * 60, $partido->reloj_segundos + $tramo);
    }

    public function minuto(Partido $partido): int
    {
        return intdiv($this->segundos($partido, now()->timestamp), 60);
    }

    // Las transiciones deportivas conservan sus reglas; solo sincronizan el reloj.
    public function alCambiarEstado(Partido $partido, string $destino): void
    {
        if ($partido->duracion_minutos === null || $destino === $partido->estado) {
            return;
        }

        if ($destino === 'en_curso' && $partido->estado === 'programado') {
            $this->comprobarTitulares($partido, $partido->titulares ?? ['local' => [], 'visitante' => []], true);
        }

        $ahora = now();
        $partido->reloj_segundos = $this->segundos($partido, $ahora->timestamp);
        $partido->reloj_desde = $destino === 'en_curso' && $partido->reloj_segundos < $partido->duracion_minutos * 60 ? $ahora : null;
        $partido->version_panel++;
    }

    public function moverReloj(Partido $partido, string $accion, int $version): void
    {
        $this->comprobarVersion($partido, $version);
        abort_unless($partido->estado === 'en_curso' && $partido->duracion_minutos !== null, 409, 'El reloj requiere un partido en curso con duración configurada.');
        $pausar = $accion === 'pausar';
        abort_unless($pausar === ($partido->reloj_desde !== null), 409, 'El reloj ya está en el estado solicitado.');
        $ahora = now();
        $segundos = $this->segundos($partido, $ahora->timestamp);
        abort_if(! $pausar && $segundos >= $partido->duracion_minutos * 60, 409, 'Ya se alcanzó la duración configurada.');

        $partido->reloj_segundos = $segundos;
        $partido->reloj_desde = $pausar ? null : $ahora;
        $partido->version_panel++;
        $partido->save();
    }

    public function consultar(Partido $partido): array
    {
        return [
            'duracion_minutos' => $partido->duracion_minutos,
            'segundos' => $this->segundos($partido, now()->timestamp),
            'en_marcha' => $partido->reloj_desde !== null,
            'version' => $partido->version_panel,
            'limites' => $this->limites($partido),
            'campeonato_en_curso' => $partido->campeonato->estado === 'en_curso',
            'titulares' => $partido->titulares ?? ['local' => [], 'visitante' => []],
            'elegibles' => array_map(fn ($lista) => $lista->map(fn (Plantilla $p) => $p->only(['id', 'dorsal', 'posicion'])
                + ['jugador' => $p->jugador->only(['nombre', 'apellido', 'foto'])]), $this->plantillas($partido, $partido->estado !== 'programado')),
        ];
    }
}
