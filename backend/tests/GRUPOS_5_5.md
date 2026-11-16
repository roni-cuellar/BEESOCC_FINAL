# Sprint 5.5 — Preparación de migración de grupos y desempates

Ensayo del 15 de noviembre de 2026 en `develop`. **000200 no se ha aplicado a
`beesocc_v2` y requiere autorización explícita del usuario.** No se ejecutará
la migración de tokens. No se modificaron React ni funcionalidades productivas.

## Conexión y estado original

Se comprobó la conexión efectiva de Laravel/XAMPP: `127.0.0.1:3306`, base
`beesocc_v2`, MariaDB **10.4.32**. Las consultas principales se ejecutaron bajo
`TRANSACTION READ ONLY`, con permisos puntuales fuera del sandbox y scripts
en `backend/tests/Integral`, accesibles por PHP de XAMPP.

La principal tiene **27 tablas, 50 registros y 18 migraciones aplicadas**.
Todas las migraciones anteriores esperadas están registradas, excepto tokens,
cuya exclusión es intencional. Las únicas pendientes son:

- `2026_11_12_224943_create_personal_access_tokens_table` — excluida.
- `2026_11_15_000200_create_desempates_grupos` — pendiente de autorización.

No existen tabla de tokens, tabla de expedientes ni columnas de 000200 en la
principal. Se descartó un esquema parcialmente migrado.

## Identidad del archivo validado

SHA-256 de `2026_11_15_000200_create_desempates_grupos.php`:

```text
c48ee2d69c8a6b1c3322401b4f7573e2120833c57b84d5836c90619ba70baebb
```

La evidencia pública 5.4 no había registrado un SHA-256 del archivo. Se verificó
la identidad exacta del contenido con la captura Git de cierre de 5.4
`78c1c0ad12d7fab42c619588971ad1267e39d4b3` y la captura previa `4892834`:
el blob es `5e02621d6dff0e0affd3f9a5b446cafab6089695` en ambas y en el archivo
actual. El SHA-256 se registra ahora sobre ese mismo archivo; no se presenta
como un hash que ya estuviera publicado en 5.4.

## Respaldo y restauración comprobada

Se creó `beesocc_v2_pre_grupos_desempates.sql`, **36.151 bytes**, en una carpeta
nueva dentro de `C:/Users/admin/Documents/BACKUPS_BEESOCC`, fuera del repositorio.
La ruta exacta queda en `Integral/privadas-locales/sprint55-respaldo-ruta.txt`,
ignorada por Git. La carpeta restringe acceso al usuario actual y SYSTEM.
El SQL contiene datos reales y nunca debe incluirse en un commit.

SHA-256 del respaldo:

```text
e836083b8050e3e81be0ed0661755f5e582c1f837f7bd2c1ca5744f6fd34e792
```

Se usó mysqldump con transacción consistente, sin bloqueos de tablas ni órdenes
de borrado. Se verificaron InnoDB y ausencia de vistas, triggers, rutinas y
eventos que requirieran un tratamiento adicional. Las credenciales temporales
de exportación no se expusieron en argumentos y se retiraron tras exportar.
Las instantáneas principales anterior y posterior coincidieron.

Se creó otra instancia MariaDB 10.4.32 con `--no-defaults`, puerto 3317 y datos,
InnoDB, redo y Aria en un directorio nuevo. Se restringieron las carpetas del
motor y de configuración. La cuenta Laravel solo tiene permisos en la base
nueva; se verificaron identidad y rutas físicas antes de escribir, rechazo de
lectura de `beesocc_v2` y rechazo de autenticación en el servidor 3306.
No se iniciaron servidores HTTP con la copia de datos reales.

La restauración en esa base vacía coincidió **exactamente** con estructuras,
registros y huellas de las 27 tablas originales. Se aplicó después, únicamente
en la copia, la ruta explícita de 000200. Resultado:

| Verificación | Resultado |
| --- | --- |
| Tablas después de migrar la copia | 28; solo se agregó `grupo_desempates` |
| Históricos anteriores | 50 registros conservados; huellas idénticas |
| Historial de migraciones | 18 anteriores intactas y una entrada de 000200 |
| Columnas nuevas | 9 |
| Claves foráneas | 4, destinos correctos y `RESTRICT` |
| Restricciones únicas | 3: fase de origen, grupo/clave y llave del expediente |
| JSON en MariaDB | `JSON_VALID` presente |
| Relaciones nuevas en históricos | NULL; no se asignaron datos deportivos artificiales |
| Tokens Sanctum | Sin tabla ni entrada de migración |

Se compararon todas las estructuras ajenas a fases/partidos sin cambios. En
fases y partidos se conservaron las definiciones de cada columna, índice y FK
anterior, además de los valores históricos. Esto incluye el almacenamiento de
liga y eliminación directa; no se repitieron sus suites costosas ya aprobadas
en 5.4. Se preservaron todas las bases y datos anteriores.

La comparación de la principal se repitió antes y después del cierre validado
del motor temporal. **Estructuras, 50 registros, huellas, historial y estado
pendiente permanecen idénticos.** La copia migrada y el respaldo se conservaron.

## Revisión Git y propuesta de commits

Se revisaron los 29 archivos recibidos: 15 modificados y 14 nuevos. Todos
conservan exactamente su contenido respecto de la captura de cierre de 5.4.
Las diferencias cubren controladores, modelos, servicios, rutas, migración,
pruebas, ejecutores y documentación; no incluyen cambios de frontend.
No se detectaron patrones de credenciales privadas en esos archivos.
`git ls-files` no incluye SQL, volcados, archivos `.env`, cookies, fixtures ni
credenciales privadas. Las rutas locales del laboratorio permanecen ignoradas.

Propuesta, **sin ejecutar staging ni commits**:

| Commit propuesto | Contenido explícito |
| --- | --- |
| `feat: completar grupos y eliminacion con desempates oficiales` | 23 archivos de 5.3: 17 productivos/migración, cinco de pruebas y `GRUPOS_5_3.md` |
| `test: validar grupos y eliminacion por HTTP y concurrencia real` | Seis de 5.4: cuatro scripts `sprint54-*`, `GRUPOS_5_4.md` y evidencia agregada 5.4 |
| `docs: preparar migracion de desempates con respaldo y ensayo` | Cuatro de 5.5: dos scripts, este informe y evidencia agregada 5.5 |

Las listas exactas de archivos y hashes están en
`Evidencias/sprint55-evidencia.json`. No usar `git add .`: seleccionar solo esas
listas revisadas. Excluir SQL, manifiesto con instantáneas, configuración de
MariaDB, `entorno.json`, rutas locales, credenciales, cookies, sesiones,
trazas y copias de datos reales. No incluir bases ni carpetas de respaldo.
Estado previsto al cierre: 33 archivos pendientes (29 recibidos + cuatro de
preparación), sin commits ni push.

## Aplicación futura: todavía no autorizada

La acción que se solicita aprobar es aplicar **solo** 000200 en `beesocc_v2`.
Antes de ejecutarla se debe volver a comprobar rama, servidor, historial,
ausencia de esquema parcial, SHA-256 y vigencia del respaldo. Si cambió la
principal, se requiere otro respaldo y revisión antes de proceder.

Este es el comando previsto, **no ejecutado** contra la principal:

```powershell
php artisan migrate --database=mysql --path=database/migrations/2026_11_15_000200_create_desempates_grupos.php --force
```

No usar `migrate` sin `--path`, `migrate:fresh`, rollback, reset ni migraciones
de tokens. MariaDB confirma DDL implícitamente: ante un fallo se detiene la
operación y se conserva el diagnóstico; no se promete rollback transaccional
del DDL ni se elimina historia para simularlo.

Tras una futura aplicación autorizada deberán repetirse las verificaciones de
estructuras, restricciones e históricos y confirmarse que solo se agregó una
entrada al historial. No se propone automatizar commits ni push.

Los scripts nuevos pasaron sintaxis PHP y Pint. La restauración y el ensayo
MariaDB fueron reales. No se repitieron pruebas backend/frontend ni carreras
ya aprobadas, porque no se modificó código productivo. No hubo verificaciones
fallidas en este ensayo. El único pendiente es la autorización explícita para
la aplicación principal; no se ha solicitado ni asumido aprobación para Git.
