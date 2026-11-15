<?php

use Illuminate\Contracts\Console\Kernel;

// Solo crea recursos nuevos. La conexión administrativa nunca selecciona una base.
require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached()) {
    throw new RuntimeException('Detenido: existe configuración en caché.');
}
$configuracion = config('database.connections.mysql');
if ($configuracion['database'] !== 'beesocc_v2' || ! empty($configuracion['url'])) {
    throw new RuntimeException('Detenido: no se identificó de forma inequívoca la conexión principal.');
}
$rutaServidor = getenv('BEESOCC_MARIADB_CONFIG');
if (! $rutaServidor || ! is_file($rutaServidor)) {
    throw new RuntimeException('Se requiere una instancia MariaDB nueva y separada.');
}
$servidor = json_decode(file_get_contents($rutaServidor), true, flags: JSON_THROW_ON_ERROR);
$directorio = str_replace('\\', '/', $servidor['datadir']);
if (! preg_match('#/beesocc_23c_mariadb_\d{8}_\d{6}_[a-f0-9]{8}/data$#', $directorio)
    || $servidor['host'] !== '127.0.0.1' || $servidor['port'] !== '3317') {
    throw new RuntimeException('La instancia no corresponde al laboratorio aislado.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=3317;charset=utf8mb4',
    'root', $servidor['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== null) {
    throw new RuntimeException('La conexión administrativa no debe tener una base seleccionada.');
}

$directorioActivo = rtrim(str_replace('\\', '/', $pdo->query('SELECT @@datadir')->fetchColumn()), '/');
if (strcasecmp($directorioActivo, $directorio) !== 0
    || (int) $pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'beesocc_v2'")->fetchColumn() !== 0) {
    throw new RuntimeException('La instancia contiene la principal o tiene otro directorio de datos.');
}
$sufijo = date('Ymd_His').'_'.bin2hex(random_bytes(4));
$base = 'beesocc_23c_test_'.$sufijo;
$usuario = 'beesocc23c_'.bin2hex(random_bytes(5));
$clave = bin2hex(random_bytes(24));
$consulta = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
$consulta->execute([$base]);
if ((int) $consulta->fetchColumn() !== 0) {
    throw new RuntimeException('Detenido: la base ya existe.');
}
$consulta = $pdo->prepare('SELECT COUNT(*) FROM mysql.user WHERE User = ?');
$consulta->execute([$usuario]);
if ((int) $consulta->fetchColumn() !== 0) {
    throw new RuntimeException('Detenido: la cuenta de pruebas ya existe.');
}

$pdo->exec('CREATE DATABASE `'.$base.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$cuenta = $pdo->quote($usuario)."@'localhost'";
$pdo->exec('CREATE USER '.$cuenta.' IDENTIFIED BY '.$pdo->quote($clave));
$pdo->exec('GRANT ALL PRIVILEGES ON `'.$base.'`.* TO '.$cuenta);

$carpeta = str_replace('\\', '/', sys_get_temp_dir()).'/'.$base;
foreach (['', '/storage/framework/views', '/storage/framework/cache/data', '/storage/framework/sessions', '/storage/logs'] as $directorio) {
    mkdir($carpeta.$directorio, 0700, true);
}
file_put_contents($carpeta.'/.env', "APP_ENV=integral\n");
$entorno = [
    'APP_ENV' => 'integral', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_URL' => 'http://127.0.0.1:8013',
    'APP_CONFIG_CACHE' => $carpeta.'/config.php', 'APP_SERVICES_CACHE' => $carpeta.'/services.php',
    'APP_PACKAGES_CACHE' => $carpeta.'/packages.php', 'APP_EVENTS_CACHE' => $carpeta.'/events.php',
    'APP_ROUTES_CACHE' => $carpeta.'/routes.php',
    'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3317', 'DB_DATABASE' => $base,
    'DB_USERNAME' => $usuario, 'DB_PASSWORD' => $clave, 'DB_SOCKET' => '',
    'BEESOCC_MARIADB_DATADIR' => $directorioActivo,
    'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database',
    'SESSION_COOKIE' => 'beesocc23c_'.bin2hex(random_bytes(4)), 'SESSION_DOMAIN' => 'null',
    'SESSION_SECURE_COOKIE' => 'false', 'SESSION_SAME_SITE' => 'lax',
    'SANCTUM_STATEFUL_DOMAINS' => 'beesocc23c-admin.localhost:5175,beesocc23c-arbitro.localhost:5175,beesocc23c-ajeno.localhost:5175',
    'CORS_ALLOWED_ORIGINS' => 'http://beesocc23c-admin.localhost:5175,http://beesocc23c-arbitro.localhost:5175,http://beesocc23c-ajeno.localhost:5175',
    'LOG_CHANNEL' => 'single', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'log', 'BCRYPT_ROUNDS' => '4',
];
file_put_contents($carpeta.'/entorno.json', json_encode($entorno, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
// Con la principal activa se compara una instantánea lógica, no archivos InnoDB abiertos.
if (is_file(dirname($rutaServidor).'/principal_antes.json')) {
    copy(dirname($rutaServidor).'/principal_antes.json', $carpeta.'/principal_antes.json');
}
echo json_encode(['base_nueva' => $base, 'configuracion_temporal' => $carpeta.'/entorno.json',
    'motor' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'directorio_aislado' => $directorioActivo], JSON_PRETTY_PRINT);
