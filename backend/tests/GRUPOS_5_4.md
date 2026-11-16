# Sprint 5.4 — Validación integral real de grupos y eliminación

Validación del 15 de noviembre de 2026 en `develop`, sin commits ni push.
Se conservaron los 23 archivos pendientes recibidos de 5.3. Este sprint agrega
solo cuatro ejecutores de laboratorio, este informe y evidencia agregada;
no modifica Laravel productivo, migraciones, configuración ni React.

## Referencias y alcance

Se consultaron `GRUPOS_5_3.md`, el MER consolidado y el Manual Técnico en `docs/`.
La prueba conserva las decisiones posteriores aprobadas: orden puntos/DG/GF,
minitabla y reevaluación de subconjuntos (DP-01/DP-05 y P-01), desempates extra
con confirmación, procedencias `grupo_puesto`, descansos por puestos y sorteo,
evitar mismo grupo cuando sea posible y correcciones transaccionales.
Se mantienen D-21/D-24 y las reglas de penales y auditoría ya implementadas.
No se resuelve DP-07 ni se incorporan descalificaciones o resultados 3–0.

## Aislamiento y preservación

Se reutilizaron el inicializador 5.2, `preparar.php`, `entorno.php`,
`servidor.php`, el cliente seguro y el cierre validado de los laboratorios 4.5/5.2.
Se creó una instancia nueva **MariaDB 10.4.32**, puerto 3317, con `--no-defaults`,
datos, InnoDB, redo y Aria en un directorio temporal nuevo. La cuenta de Laravel
solo tiene permisos en una base nueva, vacía y ficticia. Se comprobaron identidad,
permisos y las cuatro rutas físicas antes de escribir y antes de cada petición.
El lector administrativo de bloqueos se conectó exclusivamente a esa instancia.
La cuenta temporal no puede leer `beesocc_v2` ni autenticarse en el servidor 3306.

La principal se observó mediante transacciones de solo lectura, antes, después
de las pruebas y después del cierre. Permanecieron iguales sus **27 tablas y
50 registros**, con comparación de estructuras y huellas de todas las filas.
También permanecieron iguales los archivos de configuración y migración
incluidos por el verificador original. No se creó la tabla de tokens en la
principal. No se migró ni modificó `beesocc_v2`.

Se cerraron únicamente los dos servidores PHP propios, identificados por PID,
ejecutable, dirección y router; el motor temporal se detuvo validando su datadir.
Datos, configuraciones privadas y trazas se conservaron fuera del seguimiento
Git. La evidencia publicable no contiene cookies, claves ni datos personales.

## Resultados diferenciados

| Tipo | Resultado aprobado |
| --- | --- |
| HTTP de red: grupos, desempates, cruces, correcciones y permisos | 220 comprobaciones |
| HTTP de red: preparación y verificaciones de carreras nuevas | 103 comprobaciones |
| HTTP de red: compatibilidad liga y eliminación directa | 57 comprobaciones |
| Total de comprobaciones HTTP registradas | **380** |
| Carreras reales con barrera InnoDB | **4**, dos conexiones distintas acreditadas en cada una |
| Suite backend: kernel Laravel y SQLite `:memory:` | **188 pruebas, 4.489 aserciones** |
| Frontend con adaptadores simulados | **196 pruebas**: 47 Node + 149 Vitest |
| Regresión del cliente/barrera seguro | **5 pruebas Node** |
| Pint, lint, build y diferencias sin errores de espacio | Aprobados |

Las 18 pruebas/733 aserciones MariaDB de 5.3 son antecedentes, no una nueva
ejecución acreditada en 5.4. Este sprint valida MariaDB mediante HTTP de red real,
con Laravel, sesiones de base de datos, cookies, CSRF y Sanctum reales. La suite
SQLite utiliza el kernel HTTP dentro del proceso y no cuenta como red ni como
prueba concurrente. No se hicieron pruebas de navegador ni de interfaz de grupos.

### Cobertura funcional

Se crearon grupos, calendarios y actas oficiales exclusivamente por API. Se
resolvieron empate de dos equipos por partido extra y tanda certificada, y un
empate múltiple por minitabla: tres equipos con 6 puntos, DG 3 y GF 6 se ordenan
por sus directos. Otra competición generó una liguilla, terminó otra vez empatada
y exigió nueva vista previa y confirmación para la siguiente; no hubo ciclos.
Los goles adicionales aparecen en goleadores sin alterar cifras regulares;
los penales no suman goles. Se verificaron clasificación oficial, cuadro,
procedencias, sorteo firmado, descansos y cruces manuales/automáticos.

Las correcciones válidas cambiaron participantes programados conservando IDs
de llave, partido y participantes. Una corrección incompatible con la final
iniciada devolvió 409: la huella deportiva, los valores del evento y la
autorización aprobada sin fecha de consumo permanecieron intactos. Se verificó
auditoría de cada generación y ausencia de auditorías duplicadas. También se
comprobaron 401/403/404/409/419/422 y separación de poderes entre árbitro,
administración y usuario ajeno. La compatibilidad cubre calendario de liga,
cuadro directo de seis equipos, descansos, ida/vuelta, empate global y penales.

### Concurrencia observada

Dos servidores PHP independientes, puertos 8013 y 8014, sesiones distintas,
una conexión bloqueadora y un observador administrativo del laboratorio.
La barrera identifica en `INNODB_LOCK_WAITS` dos conexiones distintas esperando
al bloqueador concreto antes de liberar el registro del campeonato.
Las respuestas HTTP por sí solas nunca se aceptan como prueba de concurrencia.

| Carrera nueva | Respuestas observadas | Estado validado |
| --- | --- | --- |
| Dos generaciones del mismo desempate confirmado | 201 / 409 | Un expediente, sin partidos ni auditoría duplicados |
| Dos generaciones de eliminación desde grupos | 201 / 409 | Una fase posterior, cuadro completo y una auditoría |
| Clasificación contra corrección oficial | 200 / 200 | Se registró y luego invalidó la clasificación; corrección consumida una vez |
| Generación contra corrección oficial | 201 / 200 | Se generó y luego actualizó el cuadro programado; procedencias y clasificados vigentes |

Estos son los órdenes observados, no una afirmación de haber agotado todos los
órdenes posibles del planificador. Se verificaron cinco cuadros conectados y
su clasificación vigente, unicidad, pertenencia y partidos completos.
No se repitieron carreras de distribución o calendario de 5.2.

## Migración 000200 y datos históricos

`sprint54-sembrar.php` exige una base vacía, aplica primero el esquema anterior
sin `personal_access_tokens`, inserta un histórico ficticio y aplica únicamente
después `2026_11_15_000200_create_desempates_grupos.php`. Compara todos los valores
del partido histórico; solo aparece la nueva columna nullable sin inferir datos.

El verificador de solo lectura confirmó nueve columnas, cuatro FK a las tablas
esperadas con borrado `RESTRICT`, tres restricciones únicas (fase de origen,
grupo/clave y llave del expediente) y `JSON_VALID` para inscripciones.
Ni la tabla de tokens ni su entrada en `migrations` existen en el laboratorio.
Las migraciones originales no se modificaron. La suite SQLite reconstruye su
esquema efímero habitual; eso no ejecuta migraciones en ninguna base persistente.

## Incidencias y límites

No se detectaron errores productivos que requirieran modificar 5.3. El primer
fixture de carrera tenía dos equipos y un solo cupo total; Laravel lo rechazó
correctamente con 422 antes de crear grupos. Se corrigió el fixture a dos cupos
y se crearon escenarios nuevos; los anteriores se conservaron.
El limitador real de login devolvió 429: se respetó `Retry-After` con el cliente
existente, sin modificar límites ni reintentar escrituras deportivas.
Pint corrigió importaciones y formato del nuevo verificador; su revisión final
pasó. No hay fallos pendientes de esta validación. React y DP-07 siguen fuera
del alcance; no se afirma haber validado interfaz ni todos los intercalados.

## Reproducción segura

1. Definir `BEESOCC_PHP_BINARY` y `BEESOCC_MARIADB_INSTALL_BINARY` con rutas
   absolutas. Ejecutar `Integral/sprint52-iniciar.ps1`, que comprueba puertos,
   toma la referencia principal de solo lectura e inicializa otro datadir nuevo.
   Conservar su ruta por separado; nunca reutilizar directorios con datos.
2. Iniciar el motor con `--no-defaults`, bind 127.0.0.1, puerto 3317 y las cuatro
   rutas físicas absolutas en ese datadir, según `GRUPOS_5_2.md`.
3. Definir `BEESOCC_MARIADB_CONFIG`; ejecutar `Integral/preparar.php`. Definir
   `BEESOCC_INTEGRAL_CONFIG` con el entorno privado devuelto y
   `BEESOCC_INTEGRAL_DIR` con su carpeta. Ejecutar `sprint54-sembrar.php` una vez.
4. Iniciar dos PHP independientes con el entorno anterior y el router absoluto
   `Integral/servidor.php`, en 8013/8014. Mantener ocultas las ventanas y guardar
   sus PID. El router vuelve a comprobar el aislamiento en cada petición.
5. Ejecutar `node tests/Integral/sprint54-http.mjs` con modos `funcional`,
   `carreras` y `compatibilidad`. Cada modo aprobado conserva su evidencia y
   rechaza una repetición accidental. Para otro ensayo usar otra base vacía.
6. Ejecutar `php tests/Integral/sprint54-verificar.php`. En el entorno original
   de desarrollo, sin inyectar variables DB del laboratorio, establecer
   `BEESOCC_INTEGRAL_DIR` al directorio del motor y ejecutar
   `sprint52-principal.php despues` para comparar la referencia original.
7. Ejecutar las suites habituales, Pint, lint, build y `git diff --check`.
   Cerrar solo los servidores propios y usar `detener-mariadb.php`, que valida
   el datadir. Repetir la comparación principal de solo lectura tras el cierre.

Evidencia revisada: `Evidencias/sprint54-evidencia.json`. Los reportes detallados,
diagnósticos InnoDB, sesiones, configuraciones y fixtures permanecen privados.
