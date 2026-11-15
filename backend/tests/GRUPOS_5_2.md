# Sprint 5.2 — Validación real de grupos

Trabajo en `develop`, sin commits ni push. No se cambió código productivo
respecto al Sprint 5.1, React, migraciones ni reglas deportivas.

## Aislamiento y preservación

Se reutilizaron `preparar.php`, `entorno.php`, `servidor.php` y
`detener-mariadb.php` de los laboratorios anteriores. Instancia nueva de MariaDB
10.4.32 en `127.0.0.1:3317`, sin cargar la configuración global del motor.
Datos, InnoDB, redo y Aria quedaron en un directorio temporal nuevo.
La cuenta Laravel solo tiene permisos en la base ficticia nueva; se comprobó
el rechazo de lectura de `beesocc_v2` y de autenticación en el puerto 3306.
Cada petición comprueba base, cuenta y las cuatro rutas físicas.
La cuenta administrativa temporal se usa exclusivamente para observar InnoDB.

La principal se consultó mediante una transacción de solo lectura antes y después:
**27 tablas y 50 registros**, con estructuras y huellas de todas las filas iguales.
También permanecieron iguales `.env`, configuración de pruebas y migración de
eliminación. Las referencias privadas, credenciales, cookies y fixtures están
fuera de Git; la evidencia publicable solo contiene resultados agregados.
Al finalizar se cerraron ambos servidores HTTP propios y el motor temporal
mediante cierre validado; sus directorios, base y evidencias se conservaron.

## Pruebas diferenciadas

| Tipo | Resultado |
| --- | --- |
| HTTP real: grupos | 133 comprobaciones |
| HTTP real: compatibilidad liga/eliminación | 57 comprobaciones |
| HTTP real: correcciones y revocación | 16 comprobaciones |
| HTTP real: carreras y su preparación | 37 comprobaciones |
| Total HTTP real aprobado | **243 comprobaciones** |
| Laravel con SQLite `:memory:` | **170 pruebas, 3.756 aserciones** |
| Frontend existente, con adaptadores simulados | **196 pruebas** (149 + 47) |
| Regresión aislada del runner | **5 pruebas Node** |
| Pint, lint, build y `git diff --check` | Aprobados |

Los casos de red utilizan sesiones, cookies, CSRF y Sanctum reales, sin simular
respuestas. Cubren distribución manual, sorteo 3/2/2, cinco grupos, calendario,
descansos, resultados oficiales, tablas, clasificación, auditoría y permisos.
Se validaron 401/403/404/409/419/422. No son pruebas de navegador ni de React real.
Después de la interrupción se conservaron los 133 casos aprobados sin repetirlos.

### Concurrencia específica de grupos

Dos servidores PHP independientes (8013 y 8014), dos sesiones del mismo propietario
y una barrera que retiene el registro del campeonato. El observador acredita
**dos conexiones distintas en LOCK WAIT** contra el bloqueador concreto antes
de liberarlo. No se considera suficiente recibir respuestas distintas.

| Operación simultánea | Respuestas | Estado final |
| --- | --- | --- |
| Confirmar distribución | 201 / 409 | Dos grupos, cuatro miembros únicos |
| Generar calendario | 201 / 409 | Dos partidos, cuatro participantes |
| Registrar clasificación | 200 / 409 | Una clasificación vigente |

Hay una auditoría por operación. El verificador de lectura revisó todas las fases
de grupos del laboratorio: pertenencia válida, pares únicos, dos participantes
por partido, jornadas sin repetir equipos y ausencia de rondas eliminatorias.
Los intentos interrumpidos quedan en sus campeonatos ficticios separados;
no se borraron datos ni se sobrescribieron sus pruebas de barrera.

## Incidencias del runner

Los logins repetidos alcanzaron el límite real 429. El cliente ahora respeta
`Retry-After` (1–60 segundos), permite un único reintento de login y conserva
una segunda sesión para las tres carreras. Nunca reintenta escrituras deportivas
ni modifica el limitador o su caché. Las sesiones antiguas vivían en memoria del
proceso terminado; se crearon sesiones nuevas para reanudar.

La observación a 50 ms no acreditaba las esperas: retenía la instantánea inicial
de metadatos. Con observaciones separadas 250 ms se acreditaron las tres carreras.
La barrera identifica al bloqueador por conexión, conserva diagnóstico privado
y termina con código distinto de cero ante un fallo. La regresión del runner
rechaza acreditaciones incompletas, conexiones repetidas y reintentos ilimitados.
No se encontró un defecto funcional que requiriera modificar Laravel productivo.

## Reproducción segura

1. Definir rutas absolutas `BEESOCC_PHP_BINARY` y
   `BEESOCC_MARIADB_INSTALL_BINARY`; ejecutar `Integral/sprint52-iniciar.ps1`.
   Este toma la referencia principal de solo lectura y crea un directorio nuevo.
2. Iniciar `mysqld` con `--no-defaults`, `--bind-address=127.0.0.1`, puerto 3317,
   `--basedir` del motor y las cuatro rutas absolutas dentro del directorio nuevo:
   `--datadir`, `--innodb-data-home-dir`, `--innodb-log-group-home-dir`,
   `--aria-log-dir-path`. No reutilizar un directorio con datos.
3. Definir `BEESOCC_MARIADB_CONFIG` con el `mariadb.json` temporal; ejecutar
   `preparar.php`. Definir `BEESOCC_INTEGRAL_CONFIG` con el `entorno.json` devuelto
   y `BEESOCC_INTEGRAL_DIR` con su carpeta; ejecutar `sprint52-sembrar.php` una vez.
4. En dos procesos con ese entorno iniciar PHP `-S 127.0.0.1:8013` y
   `-S 127.0.0.1:8014`, usando la ruta absoluta de `Integral/servidor.php` como router.
5. Ejecutar `node backend/tests/Integral/sprint52-http.mjs` con modos `grupos`,
   `compatibilidad`, `correcciones` y `carreras`, en ese orden. Cada escenario
   nuevo conserva su fixture; las carreras usan campeonatos nuevos por intento.
6. Para reanudar, ejecutar primero `sprint52-revalidar.php`: exige los resultados
   aprobados y su estado, además de comprobar identidad, permisos y rutas.
   Ejecutar solo los modos pendientes, sin repetir los casos aprobados.
7. Ejecutar `sprint52-verificar.php`. Para comparar la principal, usar el entorno
   original de desarrollo, `BEESOCC_INTEGRAL_DIR` apuntando a la carpeta del motor
   y `sprint52-principal.php despues`. Nunca usar esa conexión para migrar.
8. Ejecutar `node --test backend/tests/Integral/sprint52-runner.test.mjs`;
   la suite Laravel requiere SQLite en memoria. Cerrar solo los procesos HTTP
   propios y detener el motor mediante `detener-mariadb.php`, que valida su ruta.

Quedan pendientes la interfaz de grupos y los cruces aprobados para eliminación.
DP-01, DP-05 y DP-07 no se resolvieron. No se generan partidos extra,
descalificaciones ni resultados administrativos.
