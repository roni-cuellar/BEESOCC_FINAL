# Sprint 4.5 — Integración real de eliminación directa

La interfaz de Sprint 4.4 se validó en navegador contra Laravel Sanctum y
MariaDB 10.4.32 reales. El cuadro de seis equipos, la tanda y el avance se
confirmaron desde React. Una corrección aprobada aplicada desde el acta cambió
el clasificado y actualizó la final conservando sus identificadores.
Trabajo en `develop`, sin commits ni push.

## Aislamiento comprobado

Se reutilizaron los procedimientos de Sprints 3.5 y 4.2: `preparar.php`,
`entorno.php`, `servidor.php`, `detener-mariadb.php` y la inicialización física
nueva del Sprint 3.3. No se copiaron archivos de InnoDB de la principal activa.

- Motor: MariaDB 10.4.32, `127.0.0.1:3317`.
- Base nueva: `BASE_LABORATORIO`.
- Cuenta: `CUENTA_LIMITADA_LABORATORIO`, limitada a esa base.
- Datos, redo, InnoDB y Aria: `[DIRECTORIO_LOCAL]`.
- Laravel: `127.0.0.1:8013`, mediante el router que comprueba base, cuenta y las
  cuatro rutas físicas antes de cada petición.
- React: puerto 5175; proxy temporal exclusivamente a 8013, sin modificar `.env`.
- Tres orígenes `.localhost` para aislar las cookies de propietario, árbitro y ajeno.

Antes de migrar o sembrar se verificaron servidor/puerto, base vacía, cuenta,
SHOW GRANTS y rechazo del acceso a `beesocc_v2`. El motor separado ni siquiera
contiene esa base. Solo entonces se creó el esquema del laboratorio y se
sembraron tres usuarios, seis equipos/jugadores y tres campeonatos ficticios.
Las credenciales aleatorias del motor/cuenta permanecen en la carpeta temporal,
fuera del repositorio. La clave conocida de usuarios ficticios es exclusiva del ensayo.

## Evidencia diferenciada

### Navegador con backend real

Sin sustituir Axios ni simular respuestas:

1. Login real del propietario; creación de fase, seis siembras explícitas,
   configuración de tres rondas y previsualización de cruces con dos descansos.
2. Confirmación real con doble clic; una sola generación y auditoría. El cuadro
   muestra procedencias, descansos sin partidos ficticios y rondas conectadas.
3. Login real del árbitro vigente; acceso desde Mis arbitrajes, inicio del partido,
   registro de un gol con doble clic y oficialización 1–0 desde el acta.
4. Consulta de semifinal ida/vuelta: 1–0 en la ida y 1–0 en la vuelta. Esos
   resultados se prepararon mediante HTTP real; el cuadro muestra ambas actas.
5. Solicitud de avance desde React rechazada con 409 por empate global;
   recarga y cierre de confirmación obsoleta sin clasificar manualmente.
6. Certificación arbitral 4–3 con motivo y método prórroga/penales, seguida del
   avance administrativo desde React. La final aparece con sus participantes.
7. Escenario separado de corrección: solicitud/aprobación mediante HTTP real;
   preparación y aplicación del lote desde React con el árbitro solicitante.
   El 2–1 pasa a 0–1 y el cuadro cambia de Águilas a Cóndores al actualizar.
   Se conserva el ID 10 de la final y los IDs 19/20 de sus participantes.
8. Auditoría real: responsables, fechas, motivo y valores antes/después de la
   propagación. La autorización queda consumida y no admite una segunda aplicación.
9. Tabla de todos contra todos real: tres partidos y tres puntos para el ganador,
   sin añadir fases a la liga.
10. Sin sesión: redirección a login; ajeno: 403 sin datos privados; recurso
    inexistente: 404. Tras revocar al árbitro ficticio mediante HTTP, actualizar
    su cuadro elimina tanto el cuadro como la auditoría y presenta el 403.

Móvil real en iframe de **390 × 844**, siguiendo el procedimiento de 3.5:
documento de 375 px, panel de 316 px y contenido de tres rondas de 940 px dentro
del desplazamiento horizontal. No se desborda el documento. Se comprobaron
filtro por ronda, resultados ida/vuelta, tanda y lectura de clasificados.
El archivo `frontend/tests/browser/integral-eliminacion-movil.html` carga la
aplicación y sesión reales; no contiene adaptadores ni datos simulados.
El marco presupone la copa ficticia ID 1 de este laboratorio.

Registro UI: `Integral/sprint45-navegador.json`. Es evidencia de observación,
no un runner de navegador autónomo. Las capturas se guardaron fuera del repositorio.

### HTTP real y consultas de dominio

**109 comprobaciones HTTP reales**, con cookies y CSRF de Sanctum:

| Etapa | Comprobaciones |
| --- | ---: |
| Permisos, errores, cuadro React y preparación de semifinal | 42 |
| Cierre deportivo, preparación de corrección y liga compatible | 43 |
| Consumo único y propagación después de la corrección React | 12 |
| Revocación y denegaciones posteriores | 12 |

Incluyen 401/403/404/409/422 y rechazo CSRF 419; confirmación adulterada rechazada
y recuperación con la correcta; generación duplicada rechazada; serie incompleta,
empate sin tanda, totales de penales iguales y perfiles sin autorización.
Los 422 se comprobaron por HTTP; no se atribuyen a formularios que los evitan
mediante validación nativa y controles deshabilitados.

**17 verificaciones de dominio** adicionales: siete llaves/siete partidos reales,
dos descansos, resultados oficiales, generación/tanda/gol únicos tras doble clic,
consumo único, IDs conservados, grupos vacíos y todos contra todos sin fases.
Las **409 peticiones capturadas** son tráfico total de navegador y HTTP, no 409
pruebas independientes; todas identifican exclusivamente la base del laboratorio.
No se repitieron las carreras costosas del Sprint 4.2: no cambió su lógica transaccional.

### Pruebas automatizadas de regresión

- Backend: **142 pruebas, 2.830 aserciones**, aprobadas en SQLite `:memory:`.
- Frontend: **196 pruebas**, 149 Vitest y 47 Node, aprobadas. Son pruebas de
  contratos simulados, estados y representación; no se contabilizan como HTTP real.
- Lint, build y `git diff --check` aprobados.
- Pint aprobado sobre los cinco archivos PHP del alcance 4.5: controlador,
  prueba de regresión y las tres herramientas PHP nuevas.

## Errores reales corregidos

1. `/mis-arbitrajes` no incluía `campeonato.formato`: impedía que las tarjetas
   React mostraran “Cuadro y penales”. Se añadió ese único campo al select
   de `AsignacionArbitroController`, sin ampliar permisos ni exponer propietario.
   `ConsultaArbitrosTest` verifica formato, privacidad y denegación administrativa.
   El enlace corregido se verificó contra Laravel y se utilizó para abrir el cuadro.
2. `LigaCard` mostraba `eliminacion_directa` literalmente. Se presenta
   “Eliminación directa”; se agregó una prueba de regresión frontend.
3. En móvil, nombres largos comprimían “Clasificado” y partían el tanteo de la
   tanda. `eliminacion.css` conserva ambas piezas en una línea y permite envolver
   el nombre del equipo. Se verificaron los estilos efectivos y la captura real.

No se modificaron reglas deportivas, migraciones, sesiones, credenciales reales,
datos históricos, DP-07 o grupos. No se añadieron dependencias.

## Principal y cierre

`sprint45-principal.php` tomó huellas lógicas de datos/estructura y hashes de
configuración/migración, usando una sesión de solo lectura. La comparación final
es exacta: **27 tablas y 50 registros**, iguales antes y después, incluyendo
los históricos y el registro de migración ya añadido en 4.3. Esta es la referencia
actual; las 21 tablas/49 registros correspondían al estado anterior a esa migración.
La principal no recibió DDL ni DML durante el ensayo.

Laravel, Vite y la instancia MariaDB temporal quedaron detenidos. Directorios,
base y evidencias se conservaron, sin borrar bases anteriores. La principal de
XAMPP en 3306 no se detuvo. Sin bloqueos ni pendientes de este alcance;
grupos, DP-07, retiro/descalificación y edición de tandas permanecen excluidos.

Herramientas nuevas: `Integral/sprint45-iniciar.ps1`, `sprint45-sembrar.php`,
`sprint45-principal.php`, `sprint45-http.mjs` (modos secuenciales dependientes del
recorrido React), `sprint45-verificar.php` y sus evidencias JSON.
No se deben volver a ejecutar las etapas sobre la misma fixture ya consumida.
