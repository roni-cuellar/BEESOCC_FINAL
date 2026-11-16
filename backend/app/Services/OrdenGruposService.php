<?php

namespace App\Services;

class OrdenGruposService
{
    public function acumular(array &$tabla, int $local, int $visitante, int $gl, int $gv): void
    {
        foreach ([[$local, $gl, $gv], [$visitante, $gv, $gl]] as [$id, $gf, $gc]) {
            $tabla[$id]['PJ']++;
            $tabla[$id]['GF'] += $gf;
            $tabla[$id]['GC'] += $gc;
            $tabla[$id]['DG'] = $tabla[$id]['GF'] - $tabla[$id]['GC'];
            $tabla[$id][$gf > $gc ? 'PG' : ($gf === $gc ? 'PE' : 'PP')]++;
            $tabla[$id]['PTS'] += $gf > $gc ? 3 : ($gf === $gc ? 1 : 0);
        }
    }

    public function bloques(array $filas): array
    {
        usort($filas, fn ($a, $b) => [$b['PTS'], $b['DG'], $b['GF']] <=> [$a['PTS'], $a['DG'], $a['GF']]);
        $bloques = [];
        foreach ($filas as $fila) {
            $bloques[$fila['PTS'].':'.$fila['DG'].':'.$fila['GF']][] = $fila;
        }

        return array_values($bloques);
    }

    public function directos(array $filas, array $partidos): array
    {
        if (count($filas) < 2) {
            return [$filas];
        }
        $ids = array_column($filas, 'campeonato_equipo_id');
        $mini = [];
        foreach ($filas as $fila) {
            $mini[$fila['campeonato_equipo_id']] = array_replace($fila, array_fill_keys(['PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS'], 0));
        }
        $cantidad = 0;
        foreach ($partidos as $p) {
            if (in_array($p['local'], $ids, true) && in_array($p['visitante'], $ids, true)) {
                $this->acumular($mini, $p['local'], $p['visitante'], $p['goles_local'], $p['goles_visitante']);
                $cantidad++;
            }
        }
        // No se usa una minitabla con enfrentamientos faltantes.
        if ($cantidad !== intdiv(count($ids) * (count($ids) - 1), 2)) {
            return [$filas];
        }
        $bloques = $this->bloques(array_values($mini));
        if (count($bloques) === 1) {
            return [$filas];
        }
        $originales = array_column($filas, null, 'campeonato_equipo_id');
        $resultado = [];
        foreach ($bloques as $bloque) {
            $subgrupo = array_map(fn ($f) => $originales[$f['campeonato_equipo_id']], $bloque);
            // Cada recursión reduce el conjunto empatado; no hay ciclos.
            array_push($resultado, ...$this->directos($subgrupo, $partidos));
        }

        return $resultado;
    }
}
