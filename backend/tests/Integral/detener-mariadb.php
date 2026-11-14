<?php

$ruta = getenv('BEESOCC_MARIADB_CONFIG');
if (! $ruta || ! is_file($ruta)) {
    throw new RuntimeException('Falta el archivo de la instancia temporal.');
}
$configuracion = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
$esperado = str_replace('\\', '/', $configuracion['datadir']);
if (! preg_match('#/beesocc_23c_mariadb_\d{8}_\d{6}_[a-f0-9]{8}/data$#', $esperado)
    || $configuracion['port'] !== '3317' || $configuracion['host'] !== '127.0.0.1') {
    throw new RuntimeException('El destino no corresponde a la instancia temporal.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=3317;charset=utf8mb4', 'root', $configuracion['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$activo = rtrim(str_replace('\\', '/', $pdo->query('SELECT @@datadir')->fetchColumn()), '/');
if (strcasecmp($activo, $esperado) !== 0) {
    throw new RuntimeException('No se detendrá una instancia con otro directorio de datos.');
}
$pdo->exec('SHUTDOWN');
echo 'Instancia MariaDB temporal detenida. Directorio y base de pruebas conservados.'.PHP_EOL;
