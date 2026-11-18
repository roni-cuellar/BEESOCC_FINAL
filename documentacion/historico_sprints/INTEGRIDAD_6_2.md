# Sprint 6.2 — Integridad de plantillas y ciclo del campeonato

Fecha de cierre: 16 de noviembre de 2026. Rama: `develop`.
Base de trabajo: `5580f9e0462baa130746c07533c0a878746d89ec`, commit local exclusivo de 6.1 ya existente al retomar. No se recreó ni se hicieron commits o push de 6.2. Los documentos maestros permanecen sin cambios.

## 1. Alcance y decisiones

Se continúa el trabajo interrumpido, conservando los cambios previos. Se atienden I01 y F02 de `AUDITORIA_CIERRE_6_0.md`, el mínimo configurable y el análisis residual de DP-07. Se conserva el régimen deportivo de los informes 5.3–5.7 y de `ESTABILIZACION_6_1.md`. No se implementan descalificaciones, 3–0, reapertura extraordinaria ni funciones públicas.

`IntegridadCampeonatoService` centraliza el primer bloqueo exclusivo: **campeonato → registros dependientes**. Todas sus llamadas de escritura están dentro de transacciones. Plantillas, inscripciones, configuración, calendario, actas, árbitros, correcciones, grupos, clasificación y avance comparten ese primer bloqueo. Las autorizaciones previas no bloqueantes se conservan y las operaciones reconsultan las filas que van a modificar bajo el bloqueo.

Este orden serializa operaciones del mismo campeonato, incluida la primera alta cuando aún no hay plantilla. No se bloquea toda la base ni se afirma que desaparezca cualquier posible interbloqueo externo. Las ocho carreras focales terminaron sin interbloqueos ni duplicados.

## 2. Plantillas y retiros

- Dos altas concurrentes no pueden inscribir al mismo jugador en dos equipos del campeonato ni ocupar el mismo dorsal activo del equipo.
- Alta, cambio de dorsal, baja y eliminación comparten transacción y bloqueo del campeonato. Una inscripción cerrada conserva su historial y no se reactiva.
- El índice físico impide dorsales activos duplicados incluso con una inserción SQL directa. La exclusión del mismo jugador entre equipos se garantiza en los contratos Laravel mediante el bloqueo común; no se añade una columna redundante ni se afirma una restricción SQL entre tablas.
- Desde el inicio se rechazan altas, bajas, transferencias y cambios de dorsal ordinarios. El registro de eventos adquiere el mismo bloqueo antes de consultar la plantilla.
- Un retiro que altere una distribución, cuadro o participación en calendario confirmado se rechaza. Si el retiro se confirma primero, la generación valida otra vez los inscritos y no deja un calendario inválido.
- La fecha de un partido con eventos no se puede cambiar mediante edición organizativa, pues podría invalidar la elegibilidad registrada.
- Las operaciones de plantilla y cambio de estado de inscripción se auditan en la misma transacción. Un rechazo no agrega auditoría de éxito.

## 3. Ciclo y contratos

Se reutiliza `campeonatos.estado`: `planificacion → en_curso → finalizado`. No se inventan otros estados. Las nuevas fechas son evidencia del momento de transición; los históricos no se rellenan automáticamente.

| Contrato privado | Propósito |
| --- | --- |
| `PATCH /api/campeonatos/{id}/requisitos-inicio` | Modalidad y mínimo explícitos durante planificación, incluso con calendario ya preparado |
| `POST /api/campeonatos/{id}/iniciar` | Verificar requisitos y cerrar plantillas ordinarias |
| `POST /api/campeonatos/{id}/finalizar` | Verificar resultados, resolución deportiva y campeón |
| `GET /api/campeonatos/{id}/auditoria-ciclo` | Consulta de auditoría exclusiva del propietario |

Se mantienen middleware de autenticación Sanctum, cookies y CSRF existentes. Las Policies restringen las transiciones al propietario; ser árbitro no concede esos permisos. No se aceptan estado o campeón enviados por React.

**Inicio:** exige modalidad no vacía, mínimo configurado, al menos dos equipos activos, propiedad válida de equipos y jugadores, plantillas elegibles suficientes, ausencia de jugadores/dorsales elegibles repetidos y árbitro vigente distinto del administrador. Requiere calendario completo de liga, distribución y calendario completo de grupos, o cuadro eliminatorio confirmado con sus inscritos. No admite partidos ya iniciados para convertir automáticamente un campeonato legado.

**Finalización:** exige campeonato en curso, todos los partidos finalizados y oficializados como deportivos, sin correcciones solicitadas o aprobadas pendientes. En grupos exige clasificación oficial vigente y eliminación resuelta; en eliminación directa, final resuelta. En liga conserva los criterios aprobados de puntos, diferencia y goles. Si el primer puesto sigue empatado en todos esos criterios, responde 409: nombre o ID no determinan el campeón. La inscripción campeona se deriva del resultado y se conserva en la auditoría; no se agrega otro sistema de campeones.

Después del cierre se bloquean las operaciones deportivas ordinarias, incluidas actas y correcciones. No se habilita reapertura. Los rechazos conservan fechas, estados y autorizaciones pendientes.

React incorpora `CicloCampeonatoPanel` al dashboard privado, reutiliza confirmación con protección de doble envío y el historial existente. Configura mínimos, solicita las transiciones y recarga el campeonato después del éxito o un conflicto 409. Laravel conserva toda la decisión deportiva. Se agregan etiquetas de auditoría legibles.

## 4. Mínimo y DP-07

Cada campeonato declara su modalidad y un mínimo entre 1 y 99, compatible con el rango de dorsales existente. El rango técnico no establece un mínimo deportivo: no se asignan 5, 7 u 11 automáticamente. El administrador debe indicar el número aprobado para su modalidad y reglamento. Un valor ausente bloquea el inicio.

**DP-07 no cambia:** se conservan columnas `DATE` y vigencia inclusiva del día de ingreso y salida. Los eventos siguen usando la fecha del partido, o el momento actual cuando no está programada. El inicio verifica elegibilidad para la fecha de inicio. Una baja y un nuevo ingreso el mismo día pueden producir dos inscripciones elegibles según ese criterio: el inicio rechaza el solapamiento en vez de escoger una arbitrariamente. Liberar un dorsal SQL al cerrar la inscripción no redefine su elegibilidad histórica.

**Propuesta pendiente de aprobación:** para operaciones futuras, registrar instantes de ingreso/salida con zona `America/Bogota`, considerar intervalos `[ingreso, salida)` y exigir fecha/hora de referencia para partidos. Deben acordarse también los partidos sin programación y las fechas históricas cuya hora se desconoce. Antes de migrar precisión se requiere decisión escrita, inventario y ensayo específico. No convertir fechas históricas a medianoche ni aplicar retroactivamente esa propuesta. Tampoco se incorporan excepciones de transferencia después del inicio.

## 5. Migración nueva

`backend/database/migrations/2026_11_15_000300_add_ciclo_y_minimos_campeonato.php`:

| Tabla | Adición |
| --- | --- |
| `campeonatos` | `iniciado_at`, `finalizado_at`, ambas anulables |
| `configuracion_campeonato` | `modalidad`, `minimo_jugadores`, ambos anulables |
| `plantillas` | `dorsal_vigente` virtual y anulable; único `plantillas_dorsal_vigente_unique` sobre inscripción y dorsal vigente |

Antes de ejecutar DDL se comprueban dorsales activos duplicados y se detiene la migración si existen. La columna virtual devuelve el dorsal cuando `fecha_salida IS NULL`; una inscripción cerrada devuelve NULL. No se actualizan dorsales, vigencias ni otros valores históricos. El campo técnico está oculto en el modelo/API.

Validada en SQLite y MariaDB 10.4.32 aislada: cinco columnas, índice único ordenado, rechazo SQL 1062 sin inserción parcial y conservación de todos los campos originales de una plantilla ficticia cerrada anterior a 000300. No se modificaron migraciones anteriores. **000300 no se aplicó a `beesocc_v2` y requiere un despliegue autorizado y preparado por separado.** Tampoco se ejecutó la migración pendiente de tokens Sanctum.

## 6. Pruebas y evidencia

| Tipo | Resultado y límite |
| --- | --- |
| Backend / kernel Laravel / SQLite | **201 pruebas, 4.811 aserciones**, aprobadas. Incluye nueve pruebas de ciclo: los tres formatos, permisos, mínimos, árbitro, cierre, elegibilidad futura, índice físico, conflicto inclusivo del mismo día, fallo de auditoría con rollback, fecha con eventos y corrección pendiente sin consumir |
| Frontend con adaptador controlado y renderizado estático | **251 pruebas**: 204 Vitest + 47 Node, aprobadas. Incluye contratos y visibilidad del ciclo. No equivale a navegador con API real |
| Concurrencia MariaDB / procesos Laravel independientes | **8 carreras aprobadas**, dos conexiones identificadas en `INNODB_LOCK_WAITS` contra el bloqueo concreto del campeonato; resultado, unicidad y auditoría verificados |
| Esquema MariaDB y datos ficticios | Columnas, índice, histórico y exclusión de tokens aprobados |
| Pint | Todos los PHP versionados y nuevos revisados aprobados; se excluyen herramientas privadas ignoradas |
| Lint y build frontend | Aprobados |
| HTTP de red real y navegador | No se repitieron en 6.2; los 194 controles y recorridos 5.7 son antecedentes, no validación de estas nuevas transiciones |

Carreras ejecutadas: mismo jugador en dos equipos; dos jugadores para un dorsal; alta contra baja; dos cambios hacia el mismo dorsal; baja contra evento; retiro contra calendario; inicio contra alta; retiro contra avance eliminatorio. Los casos preparan datos ficticios, y los workers invocan controladores/servicios Laravel reales desde CLI: no son solicitudes HTTP ni simulaciones de MariaDB. El caso de avance prepara un resultado oficial ficticio antes de correr las operaciones.

`backend/tests/Evidencias/sprint62-evidencia.json` conserva resultados agregados sin cuentas, contraseñas, sesiones, IDs privados o rutas del equipo. Los logs, fixtures, conexiones internas y configuraciones permanecen fuera de Git.

Al retomar se corrigieron expectativas antiguas que permitían retirar o editar plantillas con estructura o competición iniciada. Los fixtures históricos de estadísticas se preparan directamente en la base SQLite de prueba; el ciclo nuevo se prueba por sus endpoints del kernel. Esa preparación no habilita un atajo productivo. El ejecutor de concurrencia también se corrigió: asignación de propietario mediante relación protegida y filtrado de `INNODB_TRX` por las conexiones exactas del caso, evitando metadatos brevemente obsoletos. Se repitieron únicamente las carreras cuya acreditación quedó afectada.

## 7. Reproducción segura

1. Revisar `git status`, conservar los archivos y usar exclusivamente `develop`. No ejecutar `migrate` sin destino explícitamente aislado.
2. Seguir `backend/tests/Integral/sprint52-iniciar.ps1`, `preparar.php` y `entorno.php`, como en 5.4: instancia independiente en 3317, cuatro rutas físicas de MariaDB dentro del directorio nuevo y cuenta limitada a una base ficticia. Los punteros privados de 6.2 permiten localizar el laboratorio conservado, sin publicar sus valores. No reutilizar el script de siembra sobre una base poblada: se detiene si encuentra tablas.
3. Para un ensayo nuevo autorizado, con esa instancia comprobada y `BEESOCC_INTEGRAL_CONFIG` apuntando a su configuración limitada, ejecutar una vez `sprint62-sembrar.php`. Aplica migraciones del laboratorio sin tokens, prepara un histórico ficticio y aplica 000300 solamente allí. Verifica rechazo de la cuenta contra la principal y ausencia de permisos globales.
4. Definir `BEESOCC_MARIADB_CONFIG` con la configuración privada de esa misma instancia y ejecutar una vez `sprint62-carreras.php ejecutar`. Se niega a sobrescribir una evidencia final existente. Cada worker y barrera usan conexiones distintas; las esperas tienen límite de 20 segundos. Un fallo debe detener la revisión, sin reparación automática de datos.
5. Ejecutar `sprint62-verificar.php`; exigir evidencia `ok`, además de código de salida correcto. Revisar solo agregados. Detener exclusivamente esa instancia con `detener-mariadb.php`, que comprueba puerto y directorio físico. Conservar sus datos y evidencia; no borrar laboratorios anteriores.
6. En `backend`: `php artisan test --compact`. Para Pint, pasar la lista explícita obtenida con `git ls-files '*.php'` más los nuevos PHP revisados, y ejecutar `vendor/bin/pint --test` sobre esa lista. En `frontend`: `npm run test:unit`, `npm test`, `npm run lint`, `npm run build`. Finalizar con `git diff --check`.

Las rutas absolutas de PHP/XAMPP se suministran localmente; no se publican configuraciones privadas ni se instala ninguna dependencia nueva.

## 8. Principal, protección y pendientes

No se ejecutaron escrituras ni migraciones sobre `beesocc_v2`. El laboratorio rechazó leerla y autenticarse en 3306 con su cuenta limitada. La revisión final de la principal se realizó con transacción de solo lectura y comparó la referencia anterior al sprint: **28 tablas; estructuras, datos deportivos y archivos de configuración idénticos; 000300 y sus columnas ausentes**.

**Excepción verificada:** cambió la cantidad y huella de `sessions` frente a la referencia tomada antes de la interrupción. No se atribuye un origen con la evidencia disponible ni se afirma igualdad total de la base. No se eliminaron sesiones, no se sustituyó la referencia y no se intentó corregir esa diferencia. El diagnóstico completo queda privado. Si se exige una instantánea total idéntica para entrega, debe revisarse esa actividad por separado.

Los respaldos e inventarios externos de 6.1 se conservan; `5580f9e` permanece como HEAD. Se conserva además una copia externa de los archivos revisados de 6.2 con inventario SHA-256, sin SQL, credenciales, cookies, sesiones ni bases locales. Su ubicación queda en un puntero ignorado.

Pendientes explícitos: decisión definitiva DP-07; autorización y preparación independiente para aplicar 000300 en la principal; recorrido de navegador con backend real de los nuevos botones del ciclo antes de despliegue; aclarar la diferencia de sesiones si se necesita integridad total de la instantánea. No hay fallo funcional pendiente en las pruebas ejecutadas. No se inicia 6.3.

Git al cierre: cambios 6.2 sin stage ni commit, `develop` tres commits locales por delante de `origin/develop`. No hay push ni modificación de `main`. La lista exacta de cambios se obtiene con `git status --short`; código, pruebas, herramientas del laboratorio y este informe quedan para revisión y autorización posterior.
