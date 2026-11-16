<?php

namespace App\Services;

use App\Models\Fase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CrucesGruposService
{
    public function propagarCorreccion(Fase $eliminacion, array $vista, string $motivo): void
    {
        abort_unless($vista['lista_para_clasificar'], 409, 'La corrección deja clasificados sin resolver.');
        $porOrigen = [];
        foreach ($vista['candidatos'] as $c) {
            $porOrigen[$c['origen_grupo_id'].':'.$c['origen_puesto']] = $c;
        }
        $ronda = $eliminacion->rondas()->firstOrFail();
        $llaves = [];
        foreach ($ronda->llaves()->with('plazas.participante')->get() as $llave) {
            foreach ($llave->plazas as $plaza) {
                if ($plaza->origen_tipo === 'bye') {
                    continue;
                }
                $candidato = $porOrigen[$plaza->origen_grupo_id.':'.$plaza->origen_puesto] ?? null;
                abort_unless($plaza->origen_tipo === 'grupo_puesto' && $candidato, 409, 'La procedencia del cuadro es inconsistente.');
                if ($plaza->participante->campeonato_equipo_id === $candidato['campeonato_equipo_id']) {
                    continue;
                }
                $participante = $eliminacion->equipos()->firstOrCreate(['campeonato_equipo_id' => $candidato['campeonato_equipo_id']], ['estado' => 'activo']);
                app(AvanceEliminacionService::class)->cambiarPlaza($plaza, $participante->id, $motivo, false);
                $llaves[$llave->id] = $llave;
            }
        }
        foreach ($llaves as $llave) {
            app(AvanceEliminacionService::class)->preparar($llave->fresh());
        }
        $eliminacion->update(['clasificacion_huella' => $vista['huella']]);
    }

    private function firma(array $datos): string
    {
        return hash_hmac('sha256', json_encode($datos, JSON_THROW_ON_ERROR), config('app.key'));
    }

    private function sortear(array $valores): array
    {
        for ($i = count($valores) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$valores[$i], $valores[$j]] = [$valores[$j], $valores[$i]];
        }

        return $valores;
    }

    public function previsualizar(Fase $fase, array $datos, bool $confirmando = false): array
    {
        Gate::authorize('update', $fase);
        abort_if(Fase::where('fase_origen_id', $fase->id)->exists(), 409, 'La eliminación de estos grupos ya fue generada.');
        $clasificacion = app(ClasificacionGruposService::class)->preparar($fase);
        abort_unless($clasificacion['clasificacion_vigente'], 409, 'Confirma primero la clasificación oficial y sus desempates.');
        $candidatos = $clasificacion['candidatos'];
        $n = count($candidatos);
        abort_unless($n >= 2 && $n <= 32768, 422, 'La cantidad de clasificados no cabe en el generador existente.');
        $capacidad = 2;
        $rondas = 1;
        while ($capacidad < $n) {
            $capacidad *= 2;
            $rondas++;
        }
        abort_unless(count($datos['rondas']) === $rondas, 422, 'Configura todas las rondas de eliminación.');
        $porOrigen = [];
        foreach ($candidatos as $c) {
            $porOrigen[$c['origen_grupo_id'].':'.$c['origen_puesto']] = $c;
        }
        $contexto = ['fase_id' => $fase->id, 'huella' => $clasificacion['huella']];
        if (isset($datos['sorteo'])) {
            $sorteo = $datos['sorteo'];
            abort_unless(isset($datos['confirmacion_sorteo']) && hash_equals($this->firma($contexto + ['sorteo' => $sorteo]), $datos['confirmacion_sorteo']), 409, 'El sorteo no coincide con la clasificación vigente.');
        } else {
            abort_if($confirmando || isset($datos['cruces']), 422, 'Obtén primero el sorteo firmado antes de editar o confirmar cruces.');
            $sorteo = [];
            for ($puesto = 1; $puesto <= $fase->clasificados_por_grupo; $puesto++) {
                $iguales = array_keys(array_filter($porOrigen, fn ($c) => $c['origen_puesto'] === $puesto));
                array_push($sorteo, ...$this->sortear($iguales));
            }
        }
        $esperados = array_keys($porOrigen);
        $enviados = $sorteo;
        sort($esperados);
        sort($enviados);
        abort_unless($esperados === $enviados && count(array_unique($sorteo)) === $n, 422, 'El sorteo debe contener cada procedencia una vez.');
        $puestos = array_map(fn ($origen) => $porOrigen[$origen]['origen_puesto'], $sorteo);
        $ordenados = $puestos;
        sort($ordenados);
        abort_unless($puestos === $ordenados, 422, 'Los mejores puestos tienen prioridad para los descansos.');
        $descansos = array_slice($sorteo, 0, $capacidad - $n);
        $restantes = array_slice($sorteo, count($descansos));
        $automaticos = array_map(fn ($origen) => [$origen, null], $descansos);
        $porGrupo = [];
        foreach ($restantes as $origen) {
            $porGrupo[$porOrigen[$origen]['origen_grupo_id']][] = $origen;
        }
        $maximo = max(array_map('count', $porGrupo));
        $minimosMismoGrupo = max(0, $maximo - intdiv(count($restantes), 2));
        while ($porGrupo) {
            uasort($porGrupo, fn ($a, $b) => count($b) <=> count($a));
            $grupoA = array_key_first($porGrupo);
            $a = array_shift($porGrupo[$grupoA]);
            if (! $porGrupo[$grupoA]) {
                unset($porGrupo[$grupoA]);
            }
            $otros = array_filter($porGrupo, fn ($g) => $g !== $grupoA, ARRAY_FILTER_USE_KEY);
            uasort($otros, fn ($a, $b) => count($b) <=> count($a));
            $grupoB = array_key_first($otros) ?? $grupoA;
            $b = array_shift($porGrupo[$grupoB]);
            if (! $porGrupo[$grupoB]) {
                unset($porGrupo[$grupoB]);
            }
            $automaticos[] = [$a, $b];
        }
        $cruces = $datos['cruces'] ?? $automaticos;
        abort_if(isset($datos['cruces']) && $datos['metodo'] !== 'manual' && ! $confirmando, 422, 'Usa el modo manual para editar los cruces.');
        abort_unless(count($cruces) === intdiv($capacidad, 2), 422, 'Cantidad de llaves iniciales incorrecta.');
        $usados = $byes = [];
        $mismoGrupo = 0;
        foreach ($cruces as $par) {
            abort_unless(count($par) === 2 && ($par[0] !== null || $par[1] !== null), 422, 'Cada llave requiere dos plazas y al menos un clasificado.');
            foreach ($par as $origen) {
                if ($origen !== null) {
                    abort_unless(isset($porOrigen[$origen]), 422, 'Procedencia ajena a los clasificados.');
                    $usados[] = $origen;
                }
            }
            if (in_array(null, $par, true)) {
                $byes[] = $par[0] ?? $par[1];
            } elseif ($porOrigen[$par[0]]['origen_grupo_id'] === $porOrigen[$par[1]]['origen_grupo_id']) {
                $mismoGrupo++;
            }
        }
        $sinRepetidos = $usados;
        sort($sinRepetidos);
        $byesOrden = $byes;
        sort($byesOrden);
        $sorteados = $descansos;
        sort($sorteados);
        abort_unless($sinRepetidos === $esperados && $byesOrden === $sorteados && $mismoGrupo === $minimosMismoGrupo, 422, 'Hay equipos repetidos, descansos alterados o cruces del mismo grupo evitables.');
        $vista = $contexto + ['metodo' => $datos['metodo'], 'rondas' => $datos['rondas'], 'sorteo' => $sorteo,
            'confirmacion_sorteo' => $this->firma($contexto + ['sorteo' => $sorteo]), 'cruces' => $cruces,
            'clasificados' => $candidatos, 'descansos' => $descansos, 'cruces_mismo_grupo_inevitables' => $mismoGrupo];

        return $vista + ['confirmacion' => $this->firma($vista)];
    }

    public function generar(Fase $origen, array $datos): Fase
    {
        return DB::transaction(function () use ($origen, $datos) {
            $origen = app(DistribucionGruposService::class)->bloquear($origen);
            $vista = $this->previsualizar($origen, $datos, true);
            abort_unless(hash_equals($vista['confirmacion'], $datos['confirmacion']), 409, 'La clasificación o los cruces cambiaron; vuelve a previsualizar.');
            $fase = $origen->campeonato->fases()->create(['nombre' => 'Eliminación de grupos', 'orden' => $origen->campeonato->fases()->max('orden') + 1,
                'tipo' => 'eliminacion', 'estado' => 'planificacion', 'fase_origen_id' => $origen->id, 'clasificacion_huella' => $vista['huella']]);
            $procedencias = array_column($vista['clasificados'], null, 'campeonato_equipo_id');
            $porOrigen = [];
            foreach ($vista['clasificados'] as $c) {
                $porOrigen[$c['origen_grupo_id'].':'.$c['origen_puesto']] = $c['campeonato_equipo_id'];
            }
            $construccion = ['siembra' => array_keys($procedencias), 'rondas' => $vista['rondas'],
                'cruces' => array_map(fn ($par) => ['inscripciones' => array_map(fn ($o) => $o === null ? null : $porOrigen[$o], $par)], $vista['cruces'])];
            $fase = app(GeneracionEliminacionService::class)->construir($fase, $construccion, $procedencias);
            app(DistribucionGruposService::class)->auditar($origen, 'generar_eliminacion_grupos', 'Cruces y sorteo confirmados desde clasificación oficial.', null, $vista + ['fase_eliminacion_id' => $fase->id]);

            return $fase;
        });
    }
}
