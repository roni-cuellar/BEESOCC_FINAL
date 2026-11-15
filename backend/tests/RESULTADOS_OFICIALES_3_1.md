# Sprint 3.1 — Resultados oficiales deportivos y auditoría

Rama `develop`. Sin commits ni push. No se modificaron migraciones anteriores
ni se ejecutaron migraciones sobre `beesocc_v2`.

## Reglas documentadas y alcance

Se revisaron el MER y Manual Técnico consolidados de `docs/` y los informes
2.3A, 2.3B y 2.3C. Referencias principales:

- MER: diccionario de `partidos`, `auditoria_cambios` y
  `autorizaciones_correccion`; invariantes I05–I07; casos C17–C18.
- Manual: RF-25, RF-26, RF-29, RF-31, CU-07, CU-08 y matriz de permisos.
- Finalizar confirma eventos y oficializa. El árbitro debe seguir autorizado.
- El administrador organiza y aprueba/rechaza; no registra ni corrige eventos.
- Una corrección oficial requiere autorización por partido, solicitante, motivo
  y alcance, de un solo uso, con trazabilidad del antes y después.
- El resultado deportivo deriva de eventos. No se crea una tabla de resultados
  ni un segundo marcador almacenado.

El usuario confirmó conservar las consultas y estadísticas actuales. P-06 del
Manual deja pendiente el régimen público de resultados; por ello no se añadió
un filtro global de «solo oficiales», ni se oficializaron históricos.

El alcance implementado es **deportivo y corrección de eventos**. El MER define
además resultados administrativos 3–0, pero su resolución pertenece al flujo
de retiro/descalificación, no implementado aquí. `alcance=resultado` no está
habilitado. No se añadieron formatos, grupos, llaves ni reglas de sanción nuevas.

## Qué existía y qué cambia

Existían Sanctum, propiedad, asignación/revocación arbitral, estados y transiciones,
validaciones de eventos, cálculo de marcador, tabla y estadísticas, publicación
y panel React. Se reutilizan sus rutas y reglas, incluidos autogoles y asistencias.

Ahora el cierre mediante PATCH/PUT del partido también guarda `oficializado_at`
y su bitácora. Se añadió el POST de finalización propuesto en el Manual como
entrada al mismo flujo. No se cambia la fecha oficial original al corregir.

Solo se oficializa desde `en_curso`, con participantes y eventos consistentes.
Un partido oficial permanece `finalizado`: no se reabre para corregir, no admite
escrituras ordinarias del acta ni edición organizativa. Se bloquea la eliminación
de partidos con resultado oficial, correcciones o trazabilidad, incluso en 0–0.

La autorización pasa de `solicitada` a `aprobada` o `rechazada`. Únicamente el
solicitante con asignación arbitral vigente puede aplicar una aprobación. Un
sustituto debe presentar su propio expediente. El consumo, las modificaciones y
la auditoría se confirman en una transacción con bloqueos campeonato → partido
→ autorización. Si falla una operación o la bitácora, nada se confirma.

Las operaciones de corrección son registrar, corregir minuto y eliminar eventos.
Para retirar un gol se retira primero su asistencia. La bitácora conserva los
valores de eventos retirados. El marcador y las estadísticas se recalculan a
partir del acta actual usando los servicios existentes; la bitácora preserva
el estado anterior, sin crear contadores paralelos.

Se auditan también eventos ordinarios, estados y programación, con actor real,
fecha, motivo y valores anteriores/posteriores. Las acciones rutinarias conservan
un motivo descriptivo del servidor; el expediente oficial exige motivo humano.
La consulta de auditoría es privada para propietario y árbitro vigente. La API
no ofrece edición/borrado y el modelo bloquea esas operaciones de instancia.
Esto no constituye protección criptográfica ni bloqueo de SQL privilegiado.

## Migración y archivos

Nueva migración: `2026_11_14_235900_add_oficializacion_and_auditoria.php`.

- Amplía `partidos` con `tipo_resultado` y `oficializado_at` nullable.
- Crea las dos entidades nuevas que sí aparecen en el MER, sin duplicar entidades.
- Cinco FK RESTRICT: auditoría a campeonato/usuario; corrección a partido,
  solicitante y autorizador. `entidad_tipo + entidad_id` es metadato, no FK.
- Los valores anteriores del partido se conservan; los históricos permanecen
  sin fecha de oficialización. La clasificación actual continúa contando los
  finalizados, según la compatibilidad expresamente acordada.

Archivos productivos nuevos: modelos `AuditoriaCambio`, `AutorizacionCorreccion`;
`AutorizacionCorreccionPolicy`; `CorreccionPartidoController`; servicios
`AuditoriaPartidoService`, `CorreccionPartidoService`, `EventoEstadisticoService`,
`MarcadorService` y `ResultadoOficialService`.

Modificados: `Partido`, controladores `PartidoController`,
`EventoEstadisticoController`, `PublicoCampeonatoController`,
`TablaPosicionesService` y `routes/api.php`. La validación de eventos y el conteo
de goles se extrajeron para reutilizarlos, conservando contratos anteriores.
No se modificó React en este sprint.

Pruebas nuevas: `tests/Feature/ResultadoOficialTest.php`,
`tests/Integral/resultado-oficial-mariadb.php` y
`tests/Integral/evidencia-resultados-3-1.json`.

## API privada

Todas estas rutas requieren Sanctum y autorización contextual:

- POST `/api/partidos/{partido}/finalizar`, o PATCH existente con `estado=finalizado`.
- POST `/api/partidos/{partido}/correcciones`: `motivo`, `alcance=eventos`.
- PATCH `/api/correcciones/{correccion}/autorizar`: `estado=aprobada|rechazada`.
- POST `/api/correcciones/{correccion}/aplicar`: un lote no vacío de `operaciones`.
- GET `/api/partidos/{partido}/correcciones` y `/auditoria`.

Ejemplo de aplicación, después de la aprobación:

```json
{
  "operaciones": [
    {"accion": "corregir_minuto", "evento_id": 15, "minuto": 23}
  ]
}
```

`eliminar` recibe `evento_id`; `registrar` recibe `datos` con el mismo contrato
del registro ordinario. No se permite cambiar identificadores del evento al
corregir minuto. No hay aprobación implícita, transferencia automática al
sustituto, reapertura ni reutilización de un expediente consumido.

## Verificación

| Entorno | Resultado |
| --- | --- |
| SQLite `:memory:`, PHPUnit | **125 pruebas, 2.404 aserciones**; 19 pruebas nuevas |
| Frontend, Vitest | 74 aprobadas |
| Frontend, servicios | 47 aprobadas; total frontend 121 |
| Lint y build frontend | Aprobados |
| Laravel Pint, archivos del sprint | Aprobado |
| MariaDB 10.4.32 desechable | **10 comprobaciones de servicios/transacciones reales** |

SQLite cubre permisos, revocación y sustitución, campos suplantados, integridad
de marcador/estadísticas, dependencia de asistencia, consumo único, rollback
parcial y ante fallo de auditoría, bitácora privada/inmutable, 0–0 protegido y
una migración sobre datos legacy en otra conexión SQLite aislada. También pasan
las regresiones de publicación y las 106 pruebas anteriores.

MariaDB se ensayó en `beesocc_23c_test_20261114_232534_c59fae0b`, reutilizando
las herramientas de aislamiento 2.3C con instancia/directorio nuevos, rutas
absolutas y usuario limitado a esa base. Se comprobaron DDL, JSON recuperable,
oficialización, motivo/actor, aprobación y consumo, rechazo de reutilización,
marcador, conservación del cierre original, rollback y cinco FK RESTRICT.
La instancia se cerró correctamente y las **44 huellas de archivos principales
permanecieron idénticas**.

La prueba MariaDB de 3.1 invoca servicios Laravel con actores de prueba: **no es
una prueba HTTP, CSRF ni de navegador**. Las 30 comprobaciones HTTP de 2.3C son
evidencia anterior, no se vuelven a atribuir a los endpoints nuevos. Tampoco se
ensayó una carrera simultánea de consumo/revocación en esta entrega.

## Pendientes y siguientes pasos

- Conectar en React la consulta de oficialización, expediente, decisión y aplicación.
- Prueba HTTP/Sanctum y de concurrencia real de los endpoints nuevos en laboratorio.
- Definir la transición de resultados históricos y el filtro público de oficiales,
  conservando por ahora las consultas acordadas. No inventar fechas retroactivas.
- Implementar por separado las resoluciones administrativas 3–0 y su corrección.
- DP-07 continúa abierta. Se conserva la validación temporal de 2.3A, incluido
  el respaldo a fecha de operación cuando no hay `fecha_hora`; no se introduce
  una nueva interpretación del día de salida. Los snapshots de nombres/escudos
  posteriores (P-07 del Manual) tampoco se resuelven mediante esta bitácora.
- Aplicar la migración principal solo después de respaldo revisado y autorización
  expresa. Esta ejecución no la aplicó ni autoriza hacerlo automáticamente.
