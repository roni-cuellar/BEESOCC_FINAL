# Sprint 2.3C — Validación integral real

14 de noviembre de 2026, America/Bogota. Rama `develop`, sin commits ni push.

## Resultado y aislamiento

**Flujo integral aprobado en la ejecución definitiva**, utilizando React/Vite,
Laravel Sanctum, cookies y MariaDB 10.4.32 reales. No se utilizaron los adaptadores
simulados de 2.3B para estas pruebas.

- Base definitiva: `beesocc_23c_test_20261114_223814_c6e2ea82`.
- Instancia nueva en `127.0.0.1:3317`, con directorio de datos temporal propio.
- Se comprobó que la base y la cuenta no existían antes de crearlas.
- Cuenta de Laravel limitada exclusivamente a esa base; sin permisos sobre la
  principal. Se verificaron base activa, usuario y rutas de datos/InnoDB/redo/Aria
  antes de migrar y antes de cada petición HTTP.
- El migrador solo vio el alias `mysql` de pruebas. No se modificaron migraciones.
- Backend separado en puerto 8013; Vite separado en 5175 con proxy explícito.
  Subdominios `beesocc23c-admin.localhost`, `beesocc23c-arbitro.localhost` y
  `beesocc23c-ajeno.localhost`, para separar cookies de las sesiones habituales.
- Entorno HTTP `integral`, no `testing`: CSRF no estuvo omitido. Peticiones sin
  token, incluso con sesión autenticada, devolvieron 419; login desde React pasó.
- No se arrancó la instancia principal. Sus **44 archivos**, incluidos archivos
  deportivos, tablespace/redo compartidos, `my.ini` y `.env`, conservaron tamaño
  y SHA-256 frente a la huella inicial y a la del segundo ensayo. El inventario
  de archivos de `beesocc_v2` también permaneció idéntico.

La instancia temporal se cerró ordenadamente, se volvió a arrancar y conservó
el partido finalizado y los seis eventos. InnoDB cerró y arrancó con LSN 1784820
sin las advertencias del intento anterior. Finalmente se detuvieron MariaDB,
PHP y Vite de pruebas. Se conservaron los directorios temporales, sin borrar
ninguna base preexistente.

## Pruebas integrales

Escenario ficticio: organizador, dos árbitros, usuario sin autorización, un
campeonato, dos equipos, cuatro jugadores/plantillas y un partido programado.
La asignación se creó desde la pantalla administrativa, no mediante un bypass.

| Comprobación | Resultado real |
| --- | --- |
| Login del administrador y asignación de Ana | Aprobado desde React |
| Login arbitral → Mis arbitrajes → partido → iniciar | Aprobado desde React |
| Gol, autogol, asistencia, amarilla, roja y falta | Persistidos; un evento de cada tipo |
| Marcador y estadísticas | 2–0; goleadora Ana: 1; asistencia y sanciones de Camila: 1 cada una |
| Doble clic al registrar el primer gol | Un solo POST 201 y una sola fila; protección en la interfaz |
| Corregir minuto | Gol corregido de 12 a 13 y persistido |
| Eliminar autogol | Cinco eventos y marcador 1–0; al registrar otro, seis y 2–0 |
| Suspender y reanudar | Estados persistidos; sin formulario mientras está suspendido |
| Revocar a Ana con su sesión abierta | Siguiente operación: 403; retirada del acta en React |
| Cookie obtenida antes de revocar | `/usuario` siguió en 200; lectura, alta, edición, borrado y estado: 403 |
| Asignar a Bruno, login y finalizar | Aprobado desde React; acta de solo lectura |
| Escrituras y reapertura tras finalizar | 409, sin alterar los seis eventos |
| Administrador sin permiso de acta | 403 en escritura; sí pudo editar lugar y consultar estadísticas |
| Usuario ajeno | Mis arbitrajes vacío y URL directa rechazada sin exponer el acta |
| Invitado, ID inexistente y datos inválidos | 401, 404 y 422, respectivamente |
| Estadísticas y tabla después de finalizar | API y pantallas reales; local 2 goles, 3 puntos |
| Responsive | Emulación 390 × 844; consulta del acta y controles; sin desbordamiento horizontal |
| Reinicio limpio de la instancia definitiva | Persistencia aprobada; seis eventos, estado finalizado |

Los casos HTTP complementarios usan `fetch` real, conservan cookies y envían
el token XSRF. **30 comprobaciones HTTP aprobadas**, separadas de las pruebas
unitarias. La evidencia definitiva incluye 254 peticiones registradas sin
respuestas 5xx; los 401/403/404/409/419/422 de casos negativos son resultados
esperados. Ver `Integral/evidencia-http-20261114.json`.

## Error corregido

Al editar solamente el lugar, `PartidoForm` convertía nuevamente el campo
`datetime-local` (precisión de minutos) a ISO y truncaba los segundos originales.
Ejemplo observado: `22:17:42` pasaba a `22:17:00`.

Ahora conserva la fecha original exacta cuando el usuario no cambia su campo.
Una edición explícita de fecha o su eliminación siguen funcionando. Se añadieron
tres pruebas de regresión y se repitió el guardado desde React sobre MariaDB:
los segundos `42` se conservaron. No se corrigieron datos históricos.

## Incidencias del laboratorio

La instancia principal del puerto 3306 no estaba activa. Se utilizó el MariaDB
ya instalado, con archivos temporales nuevos; no se instalaron librerías.

El primer ensayo utilizó `beesocc_23c_test_20261114_221650_28c44123`. Su flujo
funcionó, pero el proceso MariaDB dejó de estar activo y una repetición HTTP
recibió 500. Al reiniciarlo aparecieron advertencias de LSN inconsistente.
No se determinó una causa definitiva ni se intentó reparar sus tablas. Ese
ensayo quedó descartado como evidencia final y su instancia fue detenida.

Se preparó desde cero la segunda instancia, fijando rutas absolutas del motor
y manteniéndolo en una sesión de ejecución controlada. Se repitieron login,
asignaciones, eventos, corrección, eliminación, estados, revocación, usuarios
ajenos, estadísticas y las 30 comprobaciones HTTP. El reinicio ordenado final
también pasó. Ninguna incidencia afectó la instancia ni los archivos principales.

El inicializador de Laravel también necesitó reconocer el prefijo absoluto
`C:` para sus cachés temporales en Windows. La corrección está solo en los
scripts del laboratorio, sin cambiar la configuración habitual de la aplicación.

## Archivos intervenidos en este sprint

- `frontend/src/components/PartidoForm.jsx`: utiliza la fecha preservada.
- `frontend/src/utils/partidos.js`: función sencilla `fechaParaGuardar`.
- `frontend/tests/unit/componentes.test.jsx`: tres regresiones del formulario.
- `frontend/tests/integral-http.mjs`: verificaciones HTTP reales y cookie anterior
  a la revocación; no se incorpora al build ni a los tests simulados.
- `backend/tests/Integral/`: inicializador PowerShell, preparación de base/cuenta,
  entorno protegido, fixtures Eloquent, servidor HTTP, verificadores de escenario
  y archivos principales, cierre verificado de MariaDB y evidencia JSON.
- Este informe. No se modificó código productivo de Laravel ni rutas/migraciones.

## Verificación automatizada final

| Comando | Resultado |
| --- | --- |
| `php vendor/bin/phpunit` | 106 pruebas, 1.988 aserciones; SQLite `:memory:` |
| `npm run test:unit` | 74 pruebas aprobadas |
| `npm test` | 47 pruebas de servicios aprobadas |
| `npm run lint` | Aprobado |
| `npm run build` | Aprobado |
| Laravel Pint sobre `tests/Integral` | Aprobado |

Frontend suma ahora **121 pruebas**, incluidas las tres nuevas. Las 30
comprobaciones HTTP reales se contabilizan aparte. Los resultados anteriores
de 2.3B con adaptadores simulados no se presentan como prueba integral.

## Reproducción y pendientes

Los scripts de `Integral/` mantienen credenciales y cachés en `%TEMP%`, fuera
del repositorio. `iniciar-mariadb.ps1` solo inicializa un directorio nuevo: el
servidor debe ejecutarse con `--no-defaults`, puerto 3317 y las cuatro rutas
absolutas dentro de ese directorio. No ejecutar `sembrar.php` sobre una base
ya poblada: se detiene si encuentra tablas. Las fases del script HTTP requieren
el orden activo → revocado → finalizado; no reinicializan ni borran datos.

Quedan pendientes un teléfono físico y una prueba concurrente específica de
revocación/escritura simultáneas: el ensayo comprobó revocación confirmada seguida
de operaciones con una sesión anterior, no una carrera paralela. DP-07 sigue
abierta y no se cambió su criterio. Resultados oficiales y auditoría avanzada
permanecen fuera del alcance de este sprint.
