<?php

// Guarda huellas lógicas; nunca incluye datos personales en el informe.
function instantanea(PDO $pdo, ?array $columnas = null): array
{
    $pdo->exec("SET time_zone = '+00:00'");
    $resultado = [];
    $tablas = $columnas ? array_keys($columnas) : $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tablas);
    foreach ($tablas as $tabla) {
        $campos = $columnas[$tabla]['columnas'] ?? $pdo->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_COLUMN);
        $seleccion = implode(',', array_map(fn ($campo) => "`$campo`", $campos));
        $filas = $pdo->query("SELECT $seleccion FROM `$tabla`")->fetchAll(PDO::FETCH_NUM);
        $filas = array_map(fn ($fila) => json_encode(array_map(fn ($valor) => $valor === null ? null : (string) $valor, $fila), JSON_THROW_ON_ERROR), $filas);
        sort($filas);
        $resultado[$tabla] = ['columnas' => $campos, 'cantidad' => count($filas), 'sha256' => hash('sha256', implode("\n", $filas)),
            'estructura' => $pdo->query("SHOW CREATE TABLE `$tabla`")->fetch(PDO::FETCH_NUM)[1]];
    }

    return $resultado;
}
