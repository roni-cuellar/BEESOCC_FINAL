# Pruebas integrales de BEESOCC 2.0

Estas pruebas recorren las rutas HTTP reales, las validaciones, los controladores,
Eloquent y una base de datos aislada. No utilizan simulaciones de los controladores.
Los métodos auxiliares solo preparan datos de prueba mediante la API.

## Archivos

- `Feature/BackendTestCase.php`: comprueba el aislamiento, aplica migraciones
  pendientes y revierte los datos de cada caso mediante una transacción.
- `Feature/ApiCrudTest.php`: campeonatos, equipos, jugadores y configuración.
- `Feature/ApiDeportivaTest.php`: inscripciones, plantillas, partidos, calendario,
  eventos y marcadores.
- `Feature/ApiResultadosTest.php`: tabla de posiciones, desempates y estadísticas
  históricas de jugadores transferidos y equipos retirados.
- `Feature/FlujoCompletoTest.php`: campeonato, configuración, cuatro equipos,
  jugadores, calendario, partido 2-1, finalización, puntos y estadísticas.

## Protección de los datos

No se utiliza `RefreshDatabase` ni `migrate:fresh`.
Antes de migrar o escribir, la clase base exige:

- Entorno `testing` y configuración de Laravel sin caché.
- Ausencia de `DB_URL`, para evitar que cambie el destino real.
- SQLite exclusivamente `:memory:`, con claves foráneas activadas; o MySQL
  exclusivamente `beesocc_v2_test`, verificando también `SELECT DATABASE()`.

Un destino diferente provoca un error antes de las migraciones y de los datos
de prueba. No se permite `beesocc_v2`. Las migraciones se ejecutan antes de la
transacción del caso; no se borran tablas. La transacción revierte solamente
los datos del caso al terminar, incluso si falla una aserción.

La base MySQL debe ser exclusiva de estas pruebas y comenzar sin datos deportivos.
No ejecutar pruebas en paralelo contra la misma base MySQL con esta configuración.
No se han cambiado `.env`, `phpunit.xml` ni credenciales existentes.
Actualmente no existe `.env.testing` y `phpunit.xml` ya selecciona SQLite en memoria.

## Ejecutar de forma aislada con SQLite

Desde `backend`, en una terminal PowerShell nueva:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
php vendor/bin/phpunit --testdox
```

Cierra esa terminal al terminar; las variables solo corresponden a ese proceso
y sus procesos hijos. No se necesita iniciar el servidor con `artisan serve`.
PHPUnit procesa las peticiones dentro de Laravel.

Solo el flujo completo:

```powershell
php vendor/bin/phpunit --filter FlujoCompletoTest --testdox
```

## Preparar MySQL independiente

1. Crear una base vacía `beesocc_v2_test` con `utf8mb4`. Por ejemplo, desde el
   administrador de MySQL:

   ```sql
   CREATE DATABASE beesocc_v2_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Utilizar un usuario con permisos únicamente sobre `beesocc_v2_test`, incluidos
   los necesarios para las migraciones. No utilizar la base de desarrollo.
3. Si no existe, crear localmente `.env.testing` sin sobrescribir archivos.
   Configurar `APP_ENV=testing`, una `APP_KEY` válida, host, puerto y las credenciales
   del usuario de pruebas. No publicar ni versionar ese archivo con credenciales.
4. En una terminal PowerShell nueva, forzar los valores siguientes. Los valores
   de entorno tienen prioridad sobre las entradas actuales de `phpunit.xml`, que
   no tienen `force="true"`:

   ```powershell
   $env:APP_ENV = 'testing'
   $env:DB_CONNECTION = 'mysql'
   $env:DB_DATABASE = 'beesocc_v2_test'
   $env:DB_URL = ''
   php vendor/bin/phpunit --testdox
   ```

La clase base creará las tablas con las migraciones pendientes, después de
comprobar el destino. No necesita un comando manual de limpieza. Si hay caché
de configuración o no se puede verificar el aislamiento, detener la ejecución
y resolver esa configuración antes de continuar. No cambiar la base de desarrollo.

## Resultados y límites

Verificación del 25 de octubre de 2026:

- Sintaxis PHP y estilo con Pint aprobados en los cinco archivos PHP nuevos.
- Suite Feature: 31 pruebas aprobadas, 0 fallos y 658 aserciones.
- Suite completa: 32 pruebas aprobadas, 0 fallos y 659 aserciones, incluyendo
  los dos ejemplos originales de Laravel (Feature y Unit).
- Incluye 30 pruebas nuevas y el ejemplo Feature original de Laravel.
- Las pruebas utilizaron SQLite en memoria con claves foráneas activadas.
- No hubo conexión a la base MySQL de desarrollo ni cambios a sus datos.
- El primer arranque de PHPUnit dentro del entorno restringido de Windows
  no pudo superar la comprobación de lectura de `vendor/autoload.php`.
  Al ejecutarlo con el acceso de lectura permitido, la suite pasó. Ese primer
  intento no ejecutó casos y no fue un fallo de la lógica de negocio.

Las aserciones cubren creación y consulta, actualización parcial, validación,
protección del historial, calendario, transiciones, eventos, marcador, todos los
campos de posiciones, ambos desempates, estadísticas e inscripciones históricas.
Los datos inconsistentes que no permite crear la API se preparan explícitamente
con Eloquent solo en la base aislada, para comprobar las respuestas defensivas.

Pendientes:

- Repetir la suite en MySQL `beesocc_v2_test`: SQLite no demuestra el comportamiento
  específico de InnoDB, colaciones, bloqueos o tipos de datos MySQL.
- Solicitudes simultáneas para configuración e inscripciones duplicadas.
- Jugadores y dorsales activos duplicados ante concurrencia.
- Generación simultánea del calendario y creación manual de partidos.
- Registro de eventos simultáneo con cambios de estado o eliminación del partido.
- Asistencias simultáneas para un mismo gol y eliminación de goles relacionados.
- Lecturas de posiciones y estadísticas durante modificaciones simultáneas.

La elegibilidad por fechas de inscripción y las correcciones de partidos
finalizados siguen pendientes de una decisión de negocio; las pruebas respetan
las reglas actuales. No se modificaron controladores, modelos ni migraciones.
