<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;

class CodigoLiga
{
    public const ALFABETO = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const LONGITUD = 10;

    public const INDICE = 'campeonatos_codigo_liga_unique';

    public function nuevo(): string
    {
        $codigo = '';
        // El alfabeto tiene 32 símbolos: cada uno recibe la misma cantidad de valores de byte.
        foreach (str_split(random_bytes(self::LONGITUD)) as $byte) {
            $codigo .= self::ALFABETO[ord($byte) % strlen(self::ALFABETO)];
        }

        return $codigo;
    }

    public function guardar(callable $guardar): mixed
    {
        for ($intento = 1; ; $intento++) {
            try {
                return $guardar($this->nuevo());
            } catch (UniqueConstraintViolationException $error) {
                $detalle = $error->errorInfo[2] ?? '';
                $colision = str_contains($detalle, self::INDICE)
                    || $detalle === 'UNIQUE constraint failed: campeonatos.codigo_liga';
                if (! $colision || $intento >= 5) {
                    throw $error;
                }
            }
        }
    }

    public static function normalizar(mixed $valor): ?string
    {
        $codigo = is_string($valor) ? strtoupper(trim($valor)) : '';

        return strlen($codigo) === self::LONGITUD && strspn($codigo, self::ALFABETO) === self::LONGITUD
            ? $codigo
            : null;
    }
}
