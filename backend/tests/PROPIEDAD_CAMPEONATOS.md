# Sprint 1.3 — propiedad de campeonatos

## Aplicación verificada en MySQL el 12 de noviembre de 2026

Rama develop. Respaldo SQL indicado por el usuario: archivo existente de 22 979
bytes; se consultaron únicamente sus metadatos, sin leer su contenido. Esto no
certifica la restauración del respaldo.

Se aplicó únicamente 2026_11_12_235000_add_administrador_id_to_campeonatos_table
mediante migrate con --path específico, sin otras migraciones. migrate:status
confirmó Ran en batch 5. La migración de personal_access_tokens sigue Pending.

Verificaciones reales: administrador_id nullable, FK a users.id con DELETE RESTRICT,
dos campeonatos conservados y ambos con administrador_id NULL. Se compararon en
memoria todas las columnas anteriores de ambas filas antes/después: valores e IDs
idénticos. Solo se añadió la migración indicada al registro de migraciones.

Pruebas posteriores: PHPUnit 52/52 y 760 aserciones en SQLite en memoria, incluidos
creación por usuario autenticado, propietario automático y restricciones ajenas;
servicios frontend 47/47, Vitest 20/20, lint y build correctos. No se crearon usuarios
ni campeonatos de prueba en MySQL. La creación HTTP sobre MySQL queda para una
verificación manual autorizada. No se asignó propietario al legado.

Las secciones siguientes conservan el análisis y el plan previo. Las referencias
a migración pendiente o no ejecutada describen ese estado anterior; el estado
vigente es el registrado en esta sección. Sin commits ni push.

## Alcance

El CRUD /api/campeonatos requiere auth:sanctum. El listado contiene únicamente
campeonatos del usuario; show, update y destroy usan CampeonatoPolicy. Laravel
descubre la Policy por la convención App/Models y App/Policies. La creación usa
la relación del usuario autenticado; administrador_id no está en fillable y se rechazan
valores de propietario enviados por el cliente. Un valor vacío no permite borrar
ni reasignar el propietario. No existe endpoint para reclamar campeonatos.

La relación User::campeonatos() es hasMany y Campeonato::administrador() es belongsTo.
No se añadieron roles, permisos de árbitro, librerías ni cambios en React.

Se leyó el contenido completo de los dos documentos consolidados 1.1 de docs/:
BEESOCC_FINAL_03_Modelo_Entidad_Relacion_Consolidado.docx y
BEESOCC_FINAL_Manual_Tecnico_Consolidado.docx. Las reglas se contrastaron con los
modelos, controlador, Policy y route:list actuales.

El MER R01, diccionario de campeonatos y etapa M1 usan administrador_id. El Manual
§4.4 y §4.5 coincide. Se corrigió el borrador anterior de este Sprint, que usaba
user_id por la instrucción inicial: la migración pendiente ahora agrega solamente
administrador_id. No se alteró una migración histórica ni se renombró una columna
en MySQL. Se rechaza tanto administrador_id como user_id enviado con un valor
para que el cliente no elija el propietario.

## Reglas trazadas a la documentación

| Fuente | Regla | Implementación o límite del Sprint |
| --- | --- | --- |
| MER R01, I01; Manual RF-03 y HU-02 | Un propietario, muchas competiciones por cuenta | administrador_id, relaciones y Policy |
| MER M1 y §8.1; Manual §4.5 | Legado nullable y asignación humana | Sin defaults de propietario ni backfill automático |
| Manual D-03 y §7.2 | Cualquier cuenta autenticada crea su competición | create permite al usuario autenticado; se convierte en propietario de esa competición |
| MER §1.1; Manual D-02 y §7.2 | Perfil contextual, selector sin privilegios | No se añade rol global; seleccionar perfil no permite acceder a otra competición |
| MER I01/I05/I06; Manual tabla 24 | Propietario y árbitro distintos, atribuciones separadas | Registrado como diseño; no se implementa arbitraje en este Sprint |
| Manual RF-08 | Eliminar solo sin historial | Se mantiene bloqueo 409 si hay inscripciones o partidos |
| Manual CP-01 | Denegar consulta administrativa ajena | 403 en CRUD privado, también para legado sin dueño |
| MER I08; Manual RF-07, §6.5 tabla 21 y §8.5 | Público sin login, solo lectura de datos publicados | Contrato de diseño separado, pendiente de reglas de publicación |

La tabla 24 indica NO crear competición desde el perfil árbitro, pero D-03 y el
párrafo posterior explican que una cuenta puede administrar una competición y
arbitrar otra. Se interpreta como permiso contextual, no como prohibición global
de crear campeonatos a una cuenta que arbitra otra competición. No se creó una
columna rol ni un permiso de árbitro.

## Contraste de rutas privadas y públicas

| Rutas | Documento FINAL | Estado del código |
| --- | --- | --- |
| GET/POST /api/campeonatos y GET/PUT/PATCH/DELETE /api/campeonatos/{id} | Administración privada por propietario | Sanctum + Policy; listado propio |
| GET /api/publico/campeonatos | Catálogo de campeonatos publicados, sin cuenta | Propuesta del Manual tabla 21; no existe todavía |
| GET /api/publico/campeonatos/{id}/tabla | Clasificación oficial pública | Propuesta del Manual tabla 21; no existe todavía |
| Consultas existentes de partidos, marcador, eventos, inscripciones, plantillas, tabla y estadísticas | Información pública separada y filtrada por publicación | Heredadas sin auth; no equivalen a las rutas públicas FINAL |
| Catálogos GET /api/equipos y /api/jugadores | Catálogos privados del organizador; solo datos publicados en vistas públicas | Siguen sin aislamiento, fuera del Sprint 1.3 |
| Escrituras de configuración, equipos, jugadores, inscripciones, plantillas, partidos y eventos | Privadas, con propietario o árbitro según la operación | Conservan acceso heredado; no intervenidas |

Regla no definida: MER DP-06 y Manual P-06 dejan pendiente cuándo se publica un
campeonato, visibilidad en planificación y posibilidad de ligas privadas. El MER
además marca visibilidad como alcance pendiente. No se asume que planificacion,
en_curso o finalizado equivalgan a publicado. No se agregó visibilidad ni se
implementaron endpoints públicos con filtros inventados. Se reportó esta brecha
antes de modificar el comportamiento de acceso público, que permanece intacto.
También quedan fuera del Sprint los estados, archivo, formatos y permisos deportivos.

La seguridad implementada abarca el CRUD de campeonatos. Las escrituras internas
de configuración, inscripciones, partidos, eventos, etc., conservan sus permisos
anteriores por el alcance solicitado. Todavía necesitan autorización propia antes
de exponer el sistema públicamente; esta entrega no garantiza la administración
exclusiva del propietario en todos los módulos deportivos.

## Migración y datos

Nueva: 2026_11_12_235000_add_administrador_id_to_campeonatos_table.php.
Agrega administrador_id nullable, índice y FK a users.id, con restricción al eliminar usuarios.
No tiene default de usuario, actualizaciones de registros ni borrados en up().
down() define la reversión estándar pero no se ejecutó.

Consulta de solo lectura realizada: 2 campeonatos, sin columna administrador_id y 12
migraciones registradas en MySQL. Por tanto los 2 campeonatos existentes requerirán
confirmar propietario. No se ejecutó esta migración ni se modificó MySQL.
La migración de tokens de Sanctum también continúa sin aplicación en esa base.

Los campeonatos anteriores quedarán con administrador_id NULL y fuera del listado privado.
La Policy bloquea consulta privada, actualización y eliminación incluso si un
usuario envía su propio ID. No se asignan al primer usuario ni al usuario actual.

## Asignación posterior segura (no ejecutada)

1. Inventariar identificadores de los dos campeonatos y usuarios mediante una
   revisión autorizada; no publicar nombres, correos ni volcados en Git.
2. Confirmar por separado quién es el propietario legítimo de cada campeonato.
   Preparar una tabla campeonato_id → administrador_id y obtener aprobación explícita.
3. Con respaldo verificado, aplicar únicamente los pares aprobados en una
   transacción y con condición administrador_id IS NULL. Comprobar una fila afectada por par
   y la existencia del usuario. Si un par ya tiene propietario, detener y revisar.
4. Comprobar relaciones y acceso con dos cuentas. Mantener NULL si no hay certeza.
   No se creó un comando de asignación automática ni se ejecutó ninguna asignación.

## Aplicación posterior en MySQL

1. Pausar escrituras y coordinar la aplicación del código y del esquema: el nuevo
   listado usa administrador_id y no debe probarse contra el esquema anterior.
2. Exportar estructura y datos de la base actual con Workbench o mysqldump usando
   solicitud interactiva de contraseña. Guardar fuera del repositorio; validar
   el respaldo mediante restauración en un entorno separado autorizado.
3. Revisar el archivo nuevo y migrate:status. Confirmar el respaldo y autorizar
   la aplicación. No usar un migrate global que aplique también otras pendientes.
4. Solo tras cumplir lo anterior, ejecutar desde backend:
   `php artisan migrate --path=database/migrations/2026_11_12_235000_add_administrador_id_to_campeonatos_table.php`.
   Este comando está documentado para una aplicación posterior; NO se ejecutó.
5. Consultar migrate:status y verificar la columna, FK, igualdad del conteo de
   campeonatos y conservación de sus valores anteriores. Los dos antiguos deben
   tener NULL. No ejecutar fresh, reset ni rollback sobre los datos existentes.
6. Crear un campeonato de prueba autorizado con una cuenta y comprobar propietario,
   listado y denegación con otra cuenta. La comprobación real en MySQL queda pendiente.

## Pruebas

PHPUnit: 52 pruebas, 760 aserciones aprobadas en SQLite :memory:.
BackendTestCase ahora rechaza cualquier conexión persistente y crea el esquema
efímero invocando up() en memoria, sin comandos Artisan migrate/reset/rollback.
Las pruebas deportivas actúan como un usuario autenticado; no se deshabilita
autorización. Las pruebas de propiedad cambian de usuario limpiando los guards
entre solicitudes para simular sesiones independientes.

Incluye creación y relaciones, listados separados, lectura/escritura ajena,
falsificación de propietario, huérfanos, invitado, acceso heredado de consultas deportivas,
FK inválida y preservación de un registro previo al cambio de esquema.
SQLite no sustituye la validación posterior del DDL en MySQL respaldado.
