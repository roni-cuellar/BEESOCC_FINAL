# Sprint 2.1 — asignaciones de árbitros

## Diseño y alcance

Referencias: MER consolidado de docs/, R04–R06, I01/I03/I04, C01–C04 y etapa M2;
Manual Técnico consolidado, RF-05/RF-06, CU-02/CU-10, §4.4/4.5 y tabla 24.
Se implementa gestión de asignaciones; no permisos completos sobre actas o estados.
PartidoPolicy::gestionarActa y los controladores de eventos/partidos permanecen intactos.
No se cambiaron React, dependencias, migraciones anteriores ni datos reales.

## Archivos

| Archivo en backend/ | Responsabilidad |
| --- | --- |
| database/migrations/2026_11_12_235300_create_asignaciones_arbitro_table.php | Tabla nueva, tres FK RESTRICT, fechas, motivo, timestamps y restricción de activo único |
| app/Models/AsignacionArbitro.php | Relaciones campeonato, usuario y autorizador; casts de fechas; oculta la columna técnica |
| app/Models/Campeonato.php | Relación hasMany con todas las asignaciones |
| app/Models/User.php | Asignaciones recibidas/autorizadas y consulta esArbitroDe por campeonato y vigencia |
| app/Policies/CampeonatoPolicy.php | gestionarArbitros reutiliza la comprobación de propietario no nulo |
| app/Services/AsignacionArbitroService.php | Bloqueo del campeonato, transacciones y reglas de asignación/reemplazo/revocación |
| app/Http/Controllers/Api/AsignacionArbitroController.php | Gate, validación, consulta y respuestas JSON; delega las escrituras al servicio |
| app/Http/Controllers/Api/CampeonatoController.php | Devuelve 409 al intentar borrar un campeonato con historial arbitral, antes de que la FK lo rechace |
| routes/api.php | Cinco rutas administrativas con auth:sanctum |
| tests/Feature/AsignacionArbitroTest.php | Casos positivos/negativos, atomicidad, FK, índice y conservación de historial |
| tests/ASIGNACIONES_ARBITRO.md | Este informe y procedimiento de validación posterior |

## Endpoints

Todos requieren Sanctum y propietario. Un árbitro asignado no puede administrar
sus propias autorizaciones ni consultar estos endpoints de administración.

| Método | Ruta | Datos y resultado |
| --- | --- | --- |
| POST | /api/campeonatos/{id}/arbitro | usuario_id; 201 con asignacion |
| PATCH | /api/campeonatos/{id}/arbitro | usuario_id y motivo_revocacion; 200 con nueva asignacion |
| DELETE | /api/campeonatos/{id}/arbitro | motivo_revocacion; 200, cierra vigencia SIN eliminar la fila |
| GET | /api/campeonatos/{id}/arbitro | asignacion vigente o null |
| GET | /api/campeonatos/{id}/arbitro/historial | asignaciones, recientes primero |

POST/PATCH corresponden al Manual. GET vigente/historial y DELETE para revocación
completan el objetivo sin crear un CRUD genérico que permita borrar historia.
Las consultas incluyen de las cuentas solo id/name, sin email ni credenciales.
No se añaden estos datos a las respuestas de espectadores.

Invitado: 401. Ajeno o campeonato sin dueño: 403. Usuario inexistente, autoasignación
o campos inválidos: 422. Segundo activo, reemplazo por el mismo árbitro o ausencia
de vigente para reemplazar/revocar: 409. No se acepta autoría, campeonato ni fechas
del cliente; servidor asigna autorizado_por, fecha_inicio y fecha_fin.
El motivo es obligatorio al reemplazar/revocar y se limita a 1.000 caracteres;
la columna es nullable porque una asignación nueva aún no tiene revocación.

## Atomicidad y árbitro activo único

Antes de escribir se bloquea la fila de campeonatos con lockForUpdate dentro de
DB::transaction. El bloqueo del padre serializa incluso la primera asignación,
cuando aún no existen filas hijas. Se vuelve a autorizar después de obtenerlo.
Reemplazar cierra fecha_fin de la anterior y crea otra dentro de la misma transacción.
Si falla la creación, se revierte toda esa operación y la anterior sigue vigente.
Se permiten hasta tres intentos transaccionales ante bloqueos/deadlocks recuperables.

Protección física adicional:

```sql
campeonato_activo_id BIGINT UNSIGNED
  GENERATED ALWAYS AS (
    CASE WHEN fecha_fin IS NULL THEN campeonato_id ELSE NULL END
  ) STORED
UNIQUE (campeonato_activo_id)
```

No es UNIQUE(campeonato_id,fecha_fin). Cada activa produce el mismo campeonato_id
y colisiona en el índice si hay otra; las revocadas producen NULL y pueden coexistir.
La columna es calculada, no la suministra el cliente ni la aplicación. Sus FK están
en las columnas ordinarias, no en la columna calculada. Las tres usan RESTRICT para
conservar historia. Una violación de unicidad al escribir se convierte en 409.

Laravel documenta storedAs para MySQL/MariaDB y SQLite:
https://laravel.com/docs/12.x/migrations#column-modifiers
Referencias oficiales para índices de columnas calculadas:
https://dev.mysql.com/doc/refman/8.0/en/create-table-generated-columns.html
https://mariadb.com/docs/server/reference/sql-statements/data-definition/create/generated-columns

Consulta de solo lectura: el servidor local es 10.4.32-MariaDB, conectado mediante
el driver mysql. La tabla nueva no existe y la migración no figura aplicada.
La implementación está diseñada para MySQL/MariaDB, pero su DDL e índice no se han
ejecutado en ese servidor. SQLite no acredita compatibilidad física o concurrencia real.

## Autorización arbitral y límites de Sprint

User::esArbitroDe consulta la base cada vez, por campeonato, fecha_inicio no futura
y fecha_fin NULL; un revocado no pasa la comprobación. Se rechaza propietario igual
a árbitro. Un usuario puede tener asignaciones activas en varias ligas de otros dueños.
No existe rol global ni se concede permiso por seleccionar un perfil visual.

El helper no se conectó aún a eventos/estados: incluso asignado, el árbitro sigue sin
ese acceso hasta Sprint 2.3. El propietario mantiene la excepción temporal gestionarActa
ya acordada en Sprint 1.4. No se implementa un expediente de auditoria_cambios, que
corresponde a otra oleada; se conserva el historial de asignaciones y sus motivos.

## Pruebas y pendientes

Resultado final ejecutado: PHPUnit 92/92, 1.649 aserciones. La suite incluye los
casos deportivos y de seguridad anteriores. Pint y git diff --check correctos.
No se ejecutaron pruebas de navegador ni suites frontend en este Sprint de backend.

Se ejecutan exclusivamente en SQLite :memory: con el guard de seguridad existente.
Incluyen asignación, autoasignación, campos falsificados, usuario inexistente,
segundo activo, reemplazo/historial, restauración atómica ante fallo, revocación y
reasignación, motivos, usuario en varias ligas, alcance por campeonato, invitados,
usuarios ajenos/perfil visual, legado sin dueño, no borrado de historia y no concesión
prematura de acceso a actas. El índice se prueba con una inserción directa que omite
el servicio, y las tres FK se prueban con referencias inexistentes.

La prueba de índice es secuencial en SQLite, NO una carrera real de dos conexiones.
Concurrencia real MySQL/MariaDB queda expresamente PENDIENTE, al igual que la prueba
de aplicación física y recuperación desde respaldo. No se crearon usuarios, ligas
ni asignaciones de prueba en la base actual.

## Aplicación posterior segura

1. Respaldar la base actual fuera del repositorio y validar restauración aislada.
2. Confirmar versión/engine y compatibilidad de columna generada/índice en una copia
   autorizada del mismo motor. No activar endpoints ni el nuevo chequeo de borrado
   de campeonato contra una base que aún no tenga esta tabla.
3. Obtener autorización y aplicar únicamente esta migración por su --path específico;
   no ejecutar globalmente las pendientes ni aplicar tokens Sanctum por accidente.
4. Verificar migrate:status, tres FK RESTRICT, columna generada y UNIQUE; comparar los
   registros deportivos anteriores, que no deben cambiar.
5. En una base de prueba autorizada, lanzar dos POST simultáneos de asignación a una
   liga inicialmente sin árbitro: una creación y un 409, exactamente una activa.
   Probar también reemplazos/revocaciones simultáneos, atomicidad y múltiples filas
   históricas. Registrar ejecución real antes de dar concurrencia por aprobada.
6. Mantener la migración de tokens pendiente y los permisos completos para Sprint 2.3.

Esta entrega no aplica ninguna migración en MySQL y no concede autorización de despliegue.
