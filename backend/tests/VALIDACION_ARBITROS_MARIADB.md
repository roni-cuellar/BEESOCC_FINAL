# Validación arbitral en MariaDB 10.4.32

Fecha: 12 de noviembre de 2026, zona America/Bogota. Rama develop; sin commits ni push.

## Resultado de la copia aislada

Respaldo actualizado, fuera del repositorio:
C:/Users/admin/Documents/BACKUPS_BEESOCC/beesocc_arbitros_20261112_202542_7a7f92.sql
(24.781 bytes). Exportación terminada correctamente. Se verificaron marcador final,
definiciones de tablas y ausencia de selección de otra base en el archivo. No se
mostraron credenciales ni contenido privado.

La copia exitosa se creó con nombre nuevo, comprobado como inexistente:
beesocc_arbitros_test_20261112_203232_bba695.

La restauración coincide con todas las tablas y filas originales del respaldo.
Solo se aplicó allí 2026_11_12_235300_create_asignaciones_arbitro_table mediante
--database explícito y --path específico. La migración de tokens no se aplicó.
Se comprobó conservación de las filas restauradas antes de introducir fixtures.

Resultados reales del motor InnoDB:

- Tabla creada correctamente y tres FK RESTRICT: campeonato_id → campeonatos.id,
  usuario_id → users.id y autorizado_por → users.id.
- campeonato_activo_id es STORED GENERATED y su índice es UNIQUE.
- Un INSERT directo de un segundo activo falla por la restricción de la base.
- Revocar conserva la fila y permite otra asignación activa.
- Reemplazar cierra la anterior y crea otra; una sola activa en esa competición.
- Dos procesos independientes enviaron solicitudes POST a través del kernel HTTP
  de Laravel, con rutas, Sanctum, Policy y servicio reales sobre la copia.
- Un bloqueo controlado del campeonato permitió observar ambas conexiones en
  INNODB_TRX con LOCK WAIT al mismo tiempo. Al liberarlo, respuestas 201 y 409,
  exactamente una asignación activa. No fueron peticiones TCP desde un navegador:
  fueron solicitudes al kernel en procesos PHP independientes y conexiones reales.

La copia contiene cuatro asignaciones de fixtures aisladas al final: tres del
ensayo funcional y una de concurrencia. No se modificaron los campeonatos o usuarios
copiados para esos ensayos; se crearon cuentas y competiciones nuevas solo en la copia.

PHPUnit volvió a pasar: 92 pruebas, 1.649 aserciones en SQLite en memoria.

## Incidencia de aislamiento y estado de la principal

El primer intento tuvo un error del script de validación: al copiar la configuración
resuelta de Laravel se conservó el nombre interno de conexión mysql. El migrador
utiliza ese nombre al ejecutar la migración y la operación de esquema creó
asignaciones_arbitro en la principal, mientras el registro de migración quedó en
la primera copia. Esa tabla nueva de la principal está VACÍA, pero constituye un
cambio de esquema no autorizado por el objetivo. No se debe ocultar esta incidencia.

Se detuvo la ejecución y se comprobó por lectura que las 17 tablas anteriores
comparadas con el respaldo restaurado conservan sus filas. La tabla nueva tiene
cero filas y no hay registro de esa migración en la principal.

El intento de retirar exclusivamente esa tabla accidental fue rechazado por la
revisión automática: un DROP TABLE en la principal requiere autorización explícita.
La instrucción no se ejecutó en ese intento. Posteriormente el usuario autorizó
expresamente retirar exclusivamente la tabla vacía. Se verificaron nuevamente el
respaldo, cero filas, ausencia de referencias entrantes y ausencia de registro de
migración. Se retiró solo esa tabla bajo bloqueo. La comparación posterior con el
respaldo restaurado confirmó el esquema original y todas las filas históricas
intactas. Al comparar migrations se excluyó únicamente la fila arbitral adicional
de la primera copia. No se eliminó ninguna base ni se aplicó la migración principal.
El script de validación se corrigió sin cambiar código de producción: cada conexión
recibe el nombre interno correcto, todas las conexiones Laravel se redirigen a la
copia, se invalida el Schema builder previamente resuelto, se comprueba SELECT
DATABASE() y las conexiones originales rechazan escrituras antes de ejecutarlas.
Los PDO originales se conservan solo para comparar lecturas de la principal.

Una segunda copia permitió comprobar DDL, pero un alias inconsistente causó una
espera de bloqueo en el ensayo funcional. Se detuvo únicamente ese proceso de
validación. Tras corregir el alias, la tercera copia completó todos los ensayos.
Las bases creadas por esta tarea se conservaron; no se eliminó ninguna base:

- beesocc_arbitros_test_20261112_202542_7a7f92 (primer intento; no usar como evidencia final).
- beesocc_arbitros_test_20261112_203004_41d5cc (ensayo interrumpido).
- beesocc_arbitros_test_20261112_203232_bba695 (validación completa).

No se muestran datos personales de las copias: contienen un respaldo y deben tratarse
como datos privados. No se publican volcados ni credenciales en Git.

## Antes de aplicar en la principal

La migración es compatible con el MariaDB ensayado; el índice y la concurrencia
funcionaron en la copia. La tabla accidental fue retirada con autorización
explícita y el esquema anterior quedó restaurado y verificado.

La migración arbitral continúa pendiente en la principal. Antes de aplicarla,
obtener autorización separada y crear un respaldo actualizado; ejecutar solo su
ruta específica. Esta corrección no autoriza aplicar la migración principal.
GestionarActa, React y módulos deportivos no se modificaron.
## Aplicación definitiva autorizada en la principal

Posteriormente, el usuario autorizó expresamente aplicar únicamente
2026_11_12_235300_create_asignaciones_arbitro_table.php en beesocc_v2.
Las secciones anteriores describen el estado previo a esta autorización.

Antes de ejecutar se comprobó nuevamente que el respaldo de 24.781 bytes,
restaurado en la primera copia, coincide con el esquema y las filas actuales.
Se verificaron ausencia de la tabla y del registro de migración, destino real
beesocc_v2 y huella SHA-256 del archivo. No se ejecutaron scripts de validación
que creen bases ni fixtures; los nuevos controles previos fueron de lectura.

Se ejecutó Artisan migrate con --database=mysql y exclusivamente
--path=database/migrations/2026_11_12_235300_create_asignaciones_arbitro_table.php.
Resultado DONE; migrate:status confirma Ran, lote 8. Tokens Sanctum sigue Pending.

Verificaciones posteriores por lectura:

- Tres FK RESTRICT hacia campeonatos.id, users.id y users.id.
- Columna campeonato_activo_id STORED GENERATED e índice único correcto.
- Estructura idéntica a la copia validada, ignorando el contador AUTO_INCREMENT.
- Tabla arbitral vacía: no se crearon asignaciones de prueba en la principal.
- Huellas de todas las filas históricas y estructuras anteriores sin cambios.
  En migrations se excluyó solo la nueva entrada arbitral al comparar.
- Conservados 3 campeonatos, 8 equipos, 3 jugadores y las relaciones históricas.

Pruebas repetidas después de aplicar:

- PHPUnit: 92 aprobadas, 1.649 aserciones, SQLite en memoria.
- npm test: 47 aprobadas.
- npm run test:unit: 33 aprobadas, cuatro archivos.

No se probaron altas ni concurrencia sobre la principal; la evidencia de concurrencia
corresponde a la copia aislada descrita arriba. Sin cambios en React, commits ni push.