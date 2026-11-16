# Sprint 5.7 — Integración real de React, Laravel y MariaDB

Ensayo del 15 de noviembre de 2026 sobre `develop`, base Git
`adda47717a205663aa546a47e8b79a013a07d4cd`. No se hicieron commits ni push.
Referencias: MER y Manual Técnico de `docs/`, `GRUPOS_5_3.md`,
`GRUPOS_5_4.md`, el informe histórico `GRUPOS_5_5.md` y
`frontend/tests/GRUPOS_5_6.md`. Los contratos se contrastaron con los
controladores, servicios y componentes existentes; React no decide ganadores.

## Aislamiento y alcance

Se reutilizaron `sprint52-iniciar.ps1`, `preparar.php`, `entorno.php` y el
procedimiento de 5.4. Instancia nueva MariaDB **10.4.32**, TCP **3317**;
Laravel en **8013** y Vite en **5175**. Directorios de datos, InnoDB, redo y
Aria independientes y verificados antes de migrar o sembrar. Cuenta limitada
a una base ficticia nueva: sin permisos globales, lectura de `beesocc_v2`
denegada y autenticación de esa cuenta en 3306 rechazada. Se restringió el
directorio privado con ACL para el usuario local y SYSTEM.

El sembrador exige una base vacía y excluye expresamente la migración de
`personal_access_tokens`. Solo se migró el laboratorio. Conserva cachés,
almacenamiento, sesiones y configuración separados; purga las conexiones
alternativas y vuelve a verificar la identidad por petición.

Se crearon tres usuarios, siete equipos y jugadores ficticios, y seis
campeonatos privados. No se copiaron datos personales ni históricos reales.
Las claves sintéticas se derivan en memoria; los archivos de sesión, las
configuraciones, trazas y fixtures quedan en TEMP privado fuera de Git.
Los punteros locales están en la carpeta ignorada `privadas-locales`.

## Recorridos observados en navegador con backend real

Se abrió la aplicación normal, sin `tests/browser/grupos.html`, sin adaptador
simulado y sin interceptar respuestas. Vite utilizó `/api` con proxy hacia
8013. Sanctum gestionó cookies, sesión y XSRF reales. Los roles usaron los
hosts separados `beesocc23c-admin.localhost`, `beesocc23c-arbitro.localhost`
y `beesocc23c-ajeno.localhost`, todos en 5175.

| Recorrido desde React | Resultado observado |
| --- | --- |
| Login de administrador y crear «Copa real 5.7 navegador» | Liga privada de grupos + eliminación creada |
| Crear fase, sorteo automático de tres grupos, previsualizar y confirmar | Se conservó la propuesta firmada de Laravel |
| Generar calendario y consultar tablas | Tres encuentros; posiciones provisionales/oficiales diferenciadas |
| Login arbitral, iniciar y finalizar dos encuentros regulares | Un empate oficial y un 1–0 registrado mediante el formulario de eventos |
| Consultar empate, previsualizar y confirmar partido extra | Un expediente y acta adicional separados de la tabla regular |
| Árbitro inicia y oficializa el extra 0–0, certifica tanda 4–3 | Certificación real con motivo y responsable |
| Administrador solicita resolución y confirma clasificación | Clasificados oficiales y puestos obtenidos de Laravel |
| Previsualizar cruces automáticos, procedencias, rondas y descanso | Tres clasificados y un pase sin partido ficticio |
| Intentar confirmar una propuesta tras una corrección HTTP | 409 real; propuesta descartada y clasificación recargada |
| Reconfirmar clasificación, renovar propuesta y generar | Cuadro existente reutilizado, sin un segundo sistema visual |
| Recargar después de otra corrección HTTP compatible | Cambió el participante del origen; se conservaron los identificadores |
| Solicitar avance de semifinal oficial | Clasificado propagado y final creada |
| Consultar minitabla preparada por HTTP y confirmar clasificación | Tres empatados con PTS 6, DG 3 y GF 6 ordenados 3, 2, 1 por sus directos |
| Generar una liguilla, recargar tras igualdad persistente y confirmar otra | Cada generación requirió propuesta y confirmación propias |
| Consultar liguilla resuelta por HTTP y confirmar clasificación | PJ 2 y cifras regulares intactas aunque se jugaron seis adicionales |
| Editar cruces manuales y volver a previsualizar | La edición invalidó la propuesta anterior; se mostraron plazas invertidas |
| Distribución manual en otro campeonato y calendario con doble clic | Cuatro equipos en dos grupos, dos partidos y una sola auditoría de calendario |
| Usuario ajeno intenta abrir grupos privados | Mensaje de permiso, sin equipos, clasificación ni auditoría |
| Árbitro recarga después de revocación HTTP | 403 y retirada de los datos previamente visibles |
| Liga todos contra todos y cuadro de eliminación directa | Tabla y actas reales; avance de una llave directa desde React |

Las inscripciones, plantillas y asignaciones arbitrales se prepararon por HTTP,
no mediante las pantallas de catálogo. Tampoco se atribuye al navegador la
creación de los otros cinco campeonatos ni la oficialización de sus casos largos.
La final de la copa principal quedó en curso deliberadamente para el conflicto.
Los cruces manuales se previsualizaron y editaron; su generación final no se repitió.

## Preparaciones y comprobaciones por HTTP de red

`sprint57-http.mjs` conserva cookies por rol solo en el directorio privado,
obtiene CSRF y usa `fetch` contra Laravel en 8013. Once modos aprobados registran
**194 comprobaciones de estado HTTP**, además de aserciones de integridad.
Esta cifra no cuenta cada GET del navegador ni equivale a pruebas PHPUnit.

| Modo | Comprobaciones | Uso |
| --- | ---: | --- |
| `inscribir` | 20 | Preparar seis equipos, plantillas y árbitro de la liga creada en React |
| `preparar` | 67 | Calendarios y resultados de minitabla y liguilla regular |
| `restantes` | 5 | Leer calendario de React y oficializar solo el tercer grupo |
| `corregir-clasificacion` | 7 | Invalidar clasificación antes de generar y caducar la propuesta abierta |
| `corregir-cuadro` | 9 | Propagar un cambio antes del inicio, comparando IDs de llaves, partidos y participantes |
| `preparar-avance` | 4 | Oficializar semifinal para solicitar avance desde React |
| `seguridad` | 17 | 401, 403, 404, 409, 419, 422 y corrección incompatible |
| `liguilla-empate` | 9 | Primera liguilla adicional con tres empates oficiales |
| `liguilla-resolver` | 12 | Segunda liguilla con resultados definitivos y cifras regulares preservadas |
| `compatibilidad` | 41 | Liga de tres equipos, eliminación directa de cuatro y equipos para asignación manual |
| `revocar` | 3 | Revocación motivada y lectura/escritura del árbitro denegadas |

La corrección incompatible se intentó con la final en curso: 409, comparación
exacta de eventos antes/después, misma huella de clasificación y mismos IDs
del cuadro. El expediente quedó aprobado con `fecha_consumo = null`.
No hubo cambios parciales. La segunda generación eliminatoria también fue
rechazada; el verificador confirmó una sola fase posterior y auditoría íntegra.

## Respuestas tardías y diseño

`sprint57-servidor.php` permite retrasar una ruta GET real hasta 1500 ms mediante
un archivo privado. Se retardó la consulta de fases de la minitabla, se recargó
y se salió a Mis ligas mientras estaba pendiente. La respuesta llegó sin
reponer datos del contexto abandonado. La traza privada acredita tres retardos
de 1500 ms. Es latencia artificial sobre Laravel real, **no una respuesta API
simulada**. Después se desactivó el retardo. La suite de stores también cubre
respuestas fuera de orden con promesas simuladas.

Se revisó escritorio 1280 × 720 y un viewport de contenido **390 × 844** en
`frontend/tests/browser/grupos-real.html?liga=<id_ficticio>`. Ese archivo solo
contiene un iframe de la aplicación normal con su misma sesión/API. Se usó
porque el control de tamaño del navegador no cambió su ancho efectivo; las
dimensiones interiores del iframe sí se verificaron. No representa una prueba
en un teléfono físico ni emula sus gestos o teclado.

En móvil se consultaron tablas provisionales, calendario, desempates, auditoría
expandida y cuadro con selector de ronda. Ancho del documento 375 px dentro
del viewport de 390 px: sin desbordamiento horizontal del documento. Tablas y
rondas conservan sus zonas de desplazamiento; los botones de secciones se
alcanzan dentro de su navegación horizontal. Los nombres largos se ajustan.
Se conservan los colores, tipografía y logotipo oficiales. Las capturas solo
contienen datos ficticios y permanecen fuera del repositorio.

## Integridad y regresión

`sprint57-verificar.php` usa una transacción de solo lectura. Confirmó nueve
columnas de 000200, cuatro FK RESTRICT, tres restricciones únicas, JSON_VALID,
ausencia de tokens, procedencias coincidentes con los clasificados, partidos
con dos participantes distintos, generaciones con auditoría y ausencia de
duplicados tras el doble clic. Verifica que los once modos HTTP estén aprobados.

La comparación de la principal mediante `sprint52-principal.php` antes/después
fue idéntica en estructuras, registros, huellas y configuración: **28 tablas,
51 filas actuales**, incluidas las 19 migraciones. Se conservan los 50 registros
anteriores a 000200 y las tres ligas históricas. Todas las consultas a la
principal fueron de solo lectura; no se ejecutó ninguna migración allí.

| Validación | Resultado |
| --- | --- |
| Backend, kernel Laravel con SQLite en memoria | 188 pruebas, 4.489 aserciones |
| Frontend con servicios/promesas simulados | 47 Node + 193 Vitest = 240 aprobadas |
| HTTP real MariaDB + sesiones/CSRF | 194 comprobaciones en 11 modos |
| Navegador React con backend real | Recorridos de la tabla anterior observados |
| Pint de los tres archivos PHP nuevos | Aprobado |
| Lint frontend y build Vite | Aprobados |
| `node --check` y revisión de espacios | Aprobados |
| Concurrencia InnoDB anterior | Cuatro carreras de 5.4 referenciadas; **cero repetidas** |

Pint general se ejecutó y detectó formato previo en `config/services.php` y
herramientas locales ignoradas anteriores. `services.php` coincide con HEAD;
no se alteró por ser ajeno a esta integración. No se presenta Pint general
como aprobado. No se repitieron las 380 comprobaciones ni las carreras de 5.4.

No se detectaron fallos productivos. Durante la construcción del guion se
corrigieron supuestos de la herramienta: nombres `tabla`/`PJ`, `fecha_consumo`,
`motivo_revocacion`, expediente más reciente por ID, cuerpo completo de cruces
y orden de inicialización. Las peticiones inválidas fueron rechazadas por
Laravel; no se cambiaron contratos ni reglas deportivas para acomodar el ensayo.

## Reproducción segura

1. Confirmar `develop`, HEAD previsto y conservar cualquier cambio local. Exigir
   puertos 3317/8013/8014/5175 libres. Definir las rutas absolutas locales de
   `BEESOCC_PHP_BINARY` y `BEESOCC_MARIADB_INSTALL_BINARY`; no imprimir claves.
2. Ejecutar `sprint52-iniciar.ps1`: toma referencia de solo lectura de la
   principal y crea otro directorio vacío. Conservar su ruta privada como
   `$motor57`; no reutilizar el laboratorio de este informe.
3. Restringir la ACL de `$motor57` al usuario local y SYSTEM. Iniciar el binario
   oficial `mysqld.exe` oculto con `--no-defaults`, bind 127.0.0.1, puerto 3317,
   basedir de XAMPP y datadir, innodb-data-home-dir, innodb-log-group-home-dir y
   aria-log-dir-path apuntando **todos** a `$motor57/data`. Registrar su PID.
4. Definir `BEESOCC_MARIADB_CONFIG=$motor57/mariadb.json` y ejecutar
   `preparar.php`. Guardar la ruta `configuracion_temporal` devuelta, sin mostrar
   su contenido, como `BEESOCC_INTEGRAL_CONFIG`; `BEESOCC_INTEGRAL_DIR` debe ser
   su directorio. Ejecutar `sprint57-sembrar.php` una sola vez. Detener si falla
   identidad, permisos, versión, rutas físicas, inaccesibilidad o base vacía.
5. Con esas variables iniciar PHP oculto: `php -S 127.0.0.1:8013 <ruta absoluta
   de sprint57-servidor.php>`. Iniciar Vite oculto con `VITE_API_URL=/api`,
   `VITE_API_PROXY_TARGET=http://127.0.0.1:8013`, host 127.0.0.1, puerto 5175 y
   `--strictPort`. No editar `.env`. Conservar PIDs y logs privados.
6. Iniciar sesión en React con los usuarios de `sprint57-fixture.json`. La clave
   sintética se deriva en memoria con HMAC-SHA256 de `sprint57-login` usando
   APP_KEY del entorno aislado, como hace el cliente; nunca versionarla.
7. Reproducir en orden, intercalando navegador y modos del cliente:
   crear liga principal en React → `inscribir` → fase automática de tres grupos,
   calendario React → `preparar` → `restantes` → dos actas regulares, extra,
   penales, avance y clasificación React → primera vista de cruces React →
   `corregir-clasificacion` → confirmar propuesta caducada y recuperar en React →
   reconfirmar clasificación y generar cuadro React → `corregir-cuadro` →
   recargar React → `preparar-avance` → avanzar React → `seguridad`.
8. En liguilla: generar desde React → `liguilla-empate` → generar siguiente
   desde React → `liguilla-resolver` → clasificar y editar/previsualizar cruces
   manuales desde React. Consultar y clasificar la minitabla en React.
9. Ejecutar `compatibilidad`; consultar tabla de liga y avanzar llave directa
   desde React. En el campeonato manual distribuir cuatro equipos/dos grupos,
   confirmar y generar calendario con doble clic. Visitar como ajeno; ejecutar
   `revocar` y recargar como árbitro. Cada modo se invoca como
   `node backend/tests/Integral/sprint57-http.mjs <modo>` desde la raíz.
   El guion impide repetir un modo aprobado; ante fallo revisar el estado
   parcial del **laboratorio**, sin borrar datos ni reintentar escrituras a ciegas.
10. Para latencia, crear solo en el directorio privado
    `sprint57-retardo.json` con `ruta` igual a `/api/campeonatos/<mini>/fases` y
    `milisegundos: 1500`. Recargar y salir por Mis ligas durante la carga;
    consultar `sprint57-retardos.jsonl` y desactivar con `{}`. Revisar escritorio
    y el marco móvil, incluyendo navegación horizontal y selector de rondas.
11. Ejecutar `sprint57-verificar.php`, las suites (`php artisan test`,
    `npm test`, `npm run test:unit`), Pint, lint, build y revisión de diferencias.
    Exportar únicamente agregados permitidos; nunca copiar configuraciones o cookies.
12. En un proceso nuevo sin variables del entorno Laravel aislado, establecer
    `BEESOCC_INTEGRAL_DIR=$motor57` y ejecutar `sprint52-principal.php despues`.
    Detener ante cualquier diferencia. Al cerrar, validar comando/PID antes de
    detener PHP/Vite propios y usar `detener-mariadb.php` con la configuración
    del motor temporal. Conservar directorios; no eliminar bases ni históricos.

Evidencia pública agregada: `Evidencias/sprint57-evidencia.json`.
Al cierre se validaron los PID y comandos de PHP/Vite y se detuvieron únicamente
esos procesos; `detener-mariadb.php` comprobó el datadir antes de apagar el motor
aislado. Se conservaron los directorios y datos del ensayo, sin eliminar bases.
Pendientes: formato global preexistente; comprobación opcional en dispositivos
físicos. No quedan bloqueos funcionales observados de la integración de este alcance.
