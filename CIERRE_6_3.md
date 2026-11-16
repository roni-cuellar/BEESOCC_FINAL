# Cierre de Sprint 6.3 — Resoluciones administrativas

Fecha: 16 de noviembre de 2026. Rama `develop`. Commit local de implementación: `ba12be3340c9539a3cd4f707bd3b93cf05030f00` (35 rutas revisadas y respaldadas). No se volvió a crear el commit ni se realizó push. Las herramientas y evidencias de este cierre quedan sin commit.

## Resultado

Integración real de React, Laravel, Sanctum y MariaDB 10.4.32 aprobada para los tres formatos. No se detectaron defectos productivos nuevos ni fue necesario modificar React, servicios deportivos o migraciones. Se conservó el trabajo anterior a la interrupción y se continuó desde el cuadro eliminatorio. La pantalla inicialmente mostraba carga; recargar y esperar las respuestas reales permitió recuperar la sesión existente, sin cambios en los datos.

## Aislamiento y respaldo

- Principal: `127.0.0.1:3306`, `beesocc_v2`, consultada exclusivamente en modo lectura.
- Copia para ensayo y laboratorio ficticio: instancias independientes de la principal, utilizadas sucesivamente en `127.0.0.1:3317`, con directorios físicos separados, cuentas limitadas y configuraciones privadas. Se verificaron permisos, motor, directorios y rechazo de acceso a la principal antes de escribir. La copia quedó detenida y conservada antes de iniciar el laboratorio ficticio.
- Laravel real en `8013` y Vite en `5175`; cookies, sesiones y CSRF reales. Orígenes locales separados para administrador y árbitro. Ningún adaptador simulado.
- Respaldo SQL externo privado: 39.696 bytes, SHA-256 `16c0f5c6c354e1df62d6bc9c77b41ca155231b71b5d6b4534990f085205adc6e`. Inventarios, configuraciones, SQL y cookies permanecen fuera de los archivos publicables.

## Ensayo de 000400, ya realizado

Migración: `2026_11_16_000400_add_resoluciones_administrativas.php`.
SHA-256: `fa1c7e536f44ee9c812b6c7a82de926d8285af71b7eaca2d1c4f2c09c3dbda9c`.

La restauración coincidió con las estructuras, índices y registros de referencia. Las sesiones de la copia también coincidieron. Se aplicó exclusivamente 000400 en la copia: permanecieron 28 tablas, se verificaron seis columnas anulables y tres FK `RESTRICT`; las columnas nuevas quedaron nulas en históricos. Se conservaron columnas, índices y FK anteriores, los datos de todas las tablas, tres campeonatos y tres plantillas. El historial pasó de 20 a 21 entradas, agregando únicamente 000400. Tokens Sanctum no se ejecutó. El ensayo no se repitió al retomar.

La comparación final de la principal contra el respaldo aprobó las estructuras y todas las huellas deportivas: 28 tablas, 55 registros de referencia y 20 migraciones. 000400 y tokens continúan pendientes, sin columnas parcialmente aplicadas. Las sesiones se compararon por separado y su huella también coincidió, sin borrarlas ni atribuirles un origen.

## Recorridos observados desde React

| Escenario | Acción y resultado observado |
| --- | --- |
| Liga | Administrador registró 3–0, con equipos y motivo explícitos. El acta conservó por separado el marcador deportivo previo 1–0 y su único evento real. |
| Rectificación válida | Solicitud, aprobación y aplicación desde React; expediente consumido y auditoría conservada. |
| Retiro | Retiro voluntario con motivo desde la pantalla de equipos; inscripción conservada con estado `retirado`. |
| Descalificación | Descalificación administrativa con motivo desde React; inscripción conservada con estado `descalificado`. No se confundió con una baja ordinaria. |
| Eliminación directa | Victoria administrativa de semifinal desde React. Ambas solicitudes de avance se confirmaron en el cuadro existente; Laravel resolvió las llaves y generó la final con los participantes correctos. |
| Grupos + eliminación | Victoria administrativa desde el acta de grupos. La pantalla mostró posiciones oficiales, 3 puntos y 3 GF, además de los dos clasificados confirmados por Laravel. |
| DP-03 | Intento de avance desde React con ambos participantes descalificados: mensaje explícito y rechazo 409, sin ganador automático. |
| Rectificación incompatible | Intento desde React de cambiar el ganador de semifinal con la final iniciada: 409 y mensaje de revisión administrativa. El expediente siguió aprobado y disponible. |
| Árbitro | Inicio de sesión real y consulta del acta. Marcadores separados, evento real y expediente consumido visibles; formulario de resolución administrativa ausente. |

La creación de campeonatos, inscripciones, plantillas, requisitos, arbitraje y calendarios se preparó por API real. También se prepararon por API la otra semifinal, el otro grupo, la confirmación de clasificación, las dos sanciones del caso DP-03 y el inicio de la final con el expediente incompatible. Estas acciones no se presentan como realizadas desde React. La generación del cuadro derivado de grupos no se repitió: este cierre verificó el resultado administrativo y su clasificación oficial, reutilizando la cobertura anterior del generador.

## Comprobaciones y límites

| Tipo | Resultado |
| --- | --- |
| HTTP de red real, preparación | 65 comprobaciones iniciales, 2 de semifinal secundaria, 4 de grupos y 3 de preparación DP-03. |
| HTTP de red real, cierre | 7 de preparación de conflicto y 17 de verificación final. **Total: 98 aprobadas**, sin contar las peticiones adicionales del navegador como pruebas automatizadas independientes. |
| Integridad del conflicto | Partido y final idénticos a sus DTO previos, participantes e identificadores conservados, auditoría sin entrada de éxito y autorización aprobada sin fecha de consumo. |
| Estadísticas | Liga conserva exactamente un gol deportivo real; directa, grupos y doble descalificación no tienen goleadores ficticios. Resultado efectivo 3–0 separado del deportivo. |
| Permisos | API rechaza resolución administrativa del árbitro con 403; el navegador no ofrece ese formulario al árbitro autorizado. |
| SQLite / kernel Laravel | Se conservan los resultados de implementación: 211 pruebas, 5.234 aserciones. No se repitió la suite al no cambiar código productivo. No equivalen a HTTP de red. |
| Frontend automatizado | Se conservan 257 pruebas (210 Vitest y 47 Node) de implementación; no se repitieron sin cambios de interfaz. |
| Concurrencia MariaDB | Cinco carreras reales ya aprobadas en implementación; no se repitieron ni se contabilizan como recorrido de navegador. |
| Calidad de cierre | Pint sobre PHP versionado y herramientas nuevas revisadas, sintaxis PHP/Node, lint, build y revisión de diferencias. Sin dependencias nuevas. |

Evidencia publicable: `backend/tests/Evidencias/sprint63-cierre-evidencia.json`. Las capturas muestran únicamente el laboratorio ficticio y se conservan fuera del repositorio. Los archivos privados detallados y los directorios de ambos laboratorios se conservan para diagnóstico.

## Reproducción segura

1. Reutilizar `iniciar-mariadb.ps1`, `preparar-entorno.ps1` y `entorno.php`, con nuevas rutas privadas y cuenta limitada, verificando identidad, permisos y almacenamiento antes de escribir. No utilizar la configuración de la principal para Laravel de laboratorio.
2. Para un ensayo nuevo autorizado, `sprint63-cierre-principal.php preparar` crea una referencia externa y exportación consistente; `comparar` solo consulta la principal y verifica esa referencia. Requiere `BEESOCC_BACKUP_DIR` externo privado. No sobrescribe respaldos anteriores.
3. En una copia nueva vacía, definir `BEESOCC_INTEGRAL_CONFIG` y `BEESOCC_COPIA_MANIFIESTO`; ejecutar `sprint63-cierre-copia.php`. Restaura una sola vez y migra únicamente 000400. Nunca ejecutarlo en la copia ya ensayada.
4. Para un laboratorio ficticio nuevo, `sprint63-cierre-sembrar.php` requiere configuración aislada y `BEESOCC_SCHEMA_MANIFIESTO`: restaura solo el esquema vacío verificado y metadatos de migraciones, sin datos originales. Rechaza una base no vacía y no ejecuta migraciones.
5. Servir Laravel con `sprint57-servidor.php` y Vite con proxy al puerto del laboratorio. Definir `BEESOCC_INTEGRAL_DIR` privado para `sprint63-cierre-http.mjs`. Sus modos `preparar`, `preparar_avance`, `completar_grupos`, `preparar_dp03`, `incompatible` y `verificar` se intercalan con las acciones de la tabla anterior; cada modo aprobado está protegido contra repetición.
6. No publicar configuración, claves temporales, cookies, logs completos o SQL. Las credenciales ficticias se derivan exclusivamente de la clave aleatoria del laboratorio y no se incluyen en las evidencias.

## Procedimiento pendiente para la principal

**No ejecutar sin autorización expresa posterior.** Coordinar una ventana sin escrituras deportivas, confirmar `develop`, PHP de XAMPP y MariaDB 10.4.32 en `127.0.0.1:3306`, verificar el SHA anterior y ejecutar `sprint63-cierre-principal.php comparar` con la referencia privada vigente. Detenerse ante diferencias deportivas, estructurales o respaldo alterado; renovar respaldo y ensayo antes de solicitar otra autorización. Diferenciar sesiones sin modificarlas.

Si se autoriza y todas las verificaciones siguen aprobadas, el único comando previsto desde `backend` es:

```powershell
C:/xampp/php/php.exe artisan migrate --database=mysql --path=database/migrations/2026_11_16_000400_add_resoluciones_administrativas.php --force
```

Después, verificar en solo lectura 28 tablas, 21 migraciones, seis columnas, tres FK `RESTRICT`, conservación de los valores anteriores y tokens todavía pendiente. Ante un error DDL, detenerse y conservar diagnóstico; no ejecutar rollback ni correcciones destructivas automáticas. Este procedimiento se documenta, pero no se ejecutó en la principal.

## Decisiones pendientes y estado final

- **DP-03:** proponer una decisión extraordinaria separada y motivada que declare la vacante y sus procedencias afectadas. Antes de implementar, la autoridad deportiva debe aprobar expresamente si se cancela la llave o cómo se trata la plaza vacante en rondas siguientes, incluida una posible final. Exigir autorización, auditoría y validación de destinos no iniciados; no rehabilitar sancionados, sustituir equipos ni conceder descansos automáticamente. Hasta esa aprobación se conserva el bloqueo seguro.
- **DP-07:** sin cambios; elegibilidad inclusiva con `DATE`. La precisión temporal futura requiere decisión documentada y validación de históricos antes de cualquier conversión.
- Sin cambios productivos nuevos. No se modificaron históricos, propietarios, sesiones, esquema principal ni documentos maestros. No se inició Sprint 6.4.
- Commit `ba12be3` conservado. Seis archivos nuevos del cierre quedan pendientes (cuatro herramientas, este informe y evidencia sanitizada), sin staging, commits adicionales ni push.
