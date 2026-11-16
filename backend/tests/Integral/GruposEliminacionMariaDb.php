<?php

namespace Tests\Integral;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\GruposEliminacionTest;

// Ejecución explícita; nunca forma parte de la suite SQLite predeterminada.
class GruposEliminacionMariaDb extends GruposEliminacionTest
{
    private static bool $esquemaCreado = false;

    protected function prepararBasePruebas(): Connection
    {
        $ruta = getenv('BEESOCC_TEST_MARIADB_CONFIG');
        if (! $ruta || ! is_file($ruta) || ! $this->app->environment('testing') || $this->app->configurationIsCached()) {
            throw new RuntimeException('Se requiere configuración explícita de laboratorio y entorno testing sin caché.');
        }
        $datos = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        if (! preg_match('/^beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/', $datos['DB_DATABASE'] ?? '')
            || ! preg_match('/^beesocc23c_[a-f0-9]+$/', $datos['DB_USERNAME'] ?? '')
            || ($datos['DB_HOST'] ?? '') !== '127.0.0.1' || ($datos['DB_PORT'] ?? '') !== '3317'
            || ! empty($datos['DB_URL']) || ($datos['APP_ENV'] ?? '') !== 'integral') {
            throw new RuntimeException('Destino ajeno al laboratorio aislado.');
        }
        $conexion = config('database.connections.mysql');
        $conexion = array_replace($conexion, ['url' => null, 'host' => '127.0.0.1', 'port' => '3317', 'unix_socket' => '',
            'database' => $datos['DB_DATABASE'], 'username' => $datos['DB_USERNAME'], 'password' => $datos['DB_PASSWORD']]);
        config(['database.default' => 'mysql', 'database.connections' => ['mysql' => $conexion]]);
        DB::purge();
        $base = DB::connection();
        $identidad = $base->selectOne('SELECT DATABASE() AS base, CURRENT_USER() AS cuenta, @@port AS puerto, @@datadir AS datos, @@innodb_data_home_dir AS innodb, @@innodb_log_group_home_dir AS redo, @@aria_log_dir_path AS aria');
        if ($identidad->base !== $datos['DB_DATABASE'] || ! str_starts_with($identidad->cuenta, $datos['DB_USERNAME'].'@') || (int) $identidad->puerto !== 3317) {
            throw new RuntimeException('Identidad de la conexión inesperada.');
        }
        $esperado = rtrim(str_replace('\\', '/', $datos['BEESOCC_MARIADB_DATADIR']), '/');
        if (! preg_match('#/beesocc_23c_mariadb_\d{8}_\d{6}_[a-f0-9]{8}/data$#', $esperado)) {
            throw new RuntimeException('Directorio no aislado.');
        }
        foreach (['datos', 'innodb', 'redo', 'aria'] as $campo) {
            if (strcasecmp(rtrim(str_replace('\\', '/', $identidad->{$campo}), '/'), $esperado) !== 0) {
                throw new RuntimeException('Archivos del motor fuera del laboratorio.');
            }
        }
        foreach ($base->select('SHOW GRANTS') as $fila) {
            $permiso = array_values((array) $fila)[0];
            if (! str_starts_with($permiso, 'GRANT USAGE ON *.*') && ! str_contains($permiso, 'ON `'.$identidad->base.'`.* TO')) {
                throw new RuntimeException('Cuenta con permisos fuera de la base temporal.');
            }
        }
        if (! self::$esquemaCreado) {
            if ($base->select('SHOW TABLES')) {
                throw new RuntimeException('La base debe estar vacía antes de crear el esquema.');
            }
            foreach (glob(database_path('migrations/*.php')) as $archivo) {
                (require $archivo)->up();
            }
            self::$esquemaCreado = true;
        }

        return $base;
    }
}
