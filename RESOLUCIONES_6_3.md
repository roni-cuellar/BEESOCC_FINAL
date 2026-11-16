# Sprint 6.3 — Resoluciones deportivas administrativas

Fecha: 16 de noviembre de 2026. Rama: `develop`.

## 1. Protección del cierre 6.2

Se revisaron las ocho rutas pendientes contra su inventario externo y cada SHA-256, incluida la copia respaldada. Coincidieron selección, contenido y HEAD. Se revisaron diferencias, archivos privados y patrones de secretos; el índice estaba vacío. Se utilizó `git add --` con las ocho rutas del inventario, sin agregar otras, y `git diff --cached --check`.

Único commit autorizado creado: **`3059179864c66816d4cfead8573cb46a103d9f52`**, `test: cerrar integracion del ciclo y ensayar migracion 000300`. Sus padres locales permanecen sin reescritura. No se hizo push ni se cambió `main`.

000300 ya estaba aplicada antes de este sprint. Por consultas de solo lectura se verificó que la FK de `plantillas.campeonato_equipo_id` continúa apuntando a `campeonato_equipos.id` y que el índice único compuesto sobre inscripción y dorsal vigente conserva la cobertura necesaria. No se reconstruyó el índice auxiliar simple ni se ejecutó DDL sobre la principal. El informe de preparación 6.2 se conserva como evidencia histórica, no como estado actual de la migración.

## 2. Reglas y contratos

Referencias revisadas: MER consolidado, especialmente 5.2/5.3 e invariantes de retiro; Manual Técnico consolidado; D02/DP-03 de `AUDITORIA_CIERRE_6_0.md`; `INTEGRIDAD_6_2.md`; servicios reales de marcador, clasificación, correcciones y avance. Donde el manual anterior atribuye la resolución 3–0 al árbitro, prevalece la instrucción expresa de este sprint: **solo el administrador propietario decide el administrativo**. El árbitro conserva la oficialización deportiva. No se modificaron los documentos maestros.

| Operación privada | Contrato |
| --- | --- |
| `POST /api/partidos/{id}/resultado-administrativo` | `beneficiado_id`, `sancionado_id`, `motivo`; `autorizacion_id` adicional cuando el partido ya es oficial |
| `POST /api/inscripciones-equipos/{id}/decision-administrativa` | `tipo: retiro` o `descalificacion`, `motivo` |
| `POST /api/partidos/{id}/correcciones` | Se reutiliza con `alcance: resultado` y motivo, exclusivamente por el propietario |
| `PATCH /api/correcciones/{id}/autorizar` | Aprobación/rechazo explícitos por el propietario, como en el sistema existente |

Se conservan autenticación Sanctum, cookies, CSRF y Policies. Los endpoints nuevos están dentro del grupo autenticado existente. No se crean roles, formatos deportivos ni mecanismos de tokens alternativos.

### Resultado 3–0

`ResolucionAdministrativaService` bloquea primero el campeonato, después el partido y la autorización cuando corresponde. Revalida propietario, campeonato en curso, participantes distintos y pertenecientes al partido, beneficiado activo y motivo obligatorio. Un resultado pendiente puede resolverse desde programado, en curso o suspendido. No se convierte un histórico finalizado sin oficialización ni un cancelado mediante esta operación ordinaria.

El beneficiado recibe 3 y el sancionado 0 mediante `MarcadorService`, sin insertar goles, asistencias u otros eventos. El acta y sus eventos reales permanecen; la API devuelve también `marcador_deportivo`, mostrado separadamente en React. Cada partido mantiene un único resultado vigente; las versiones anteriores, responsables, motivos, fechas y datos de autorización quedan en la auditoría inmutable existente. No se agregó un segundo sistema de actas.

Un resultado oficial anterior exige un expediente aprobado, de alcance `resultado`, del mismo partido y aplicable por su solicitante propietario. La aplicación y consumo son transaccionales. Se rechazan duplicados, autorizaciones consumidas, beneficiados inactivos y propagaciones incompatibles. Si una corrección de eventos ya está aprobada, se exige aplicarla antes de convertir el resultado, para no inutilizar esa autorización. No se aprueban nuevos cambios de eventos sobre un administrativo.

La rectificación reutiliza la propagación de llaves y procedencias de grupos. Antes de iniciar el destino puede actualizar participantes conservando IDs. Si el destino ya inició, se rechaza sin cambiar resultado, participantes o auditoría y sin consumir la aprobación. No se habilita corrección extraordinaria de tandas ni reapertura de campeonatos finalizados.

### Retiro y descalificación

Se mantienen separados los conceptos:

- **Retiro voluntario:** inscripción `retirado`, motivo y fecha, con auditoría.
- **Descalificación:** inscripción `descalificado`, motivo y fecha, con auditoría.
- **Victoria administrativa:** decisión explícita por partido. No se infiere de una baja ni cambia por sí sola el estado de inscripción.

El endpoint administrativo permite la inactividad durante el campeonato en curso; no reutiliza la baja ordinaria bloqueada en 6.2. No elimina inscripciones, jugadores, eventos o partidos. Audita antes/después de la inscripción y sus participaciones de fase. Los encuentros pendientes requieren su decisión 3–0 separada; no se reescriben automáticamente los ya disputados.

En ida/vuelta, después de un retiro o descalificación, el rival válido avanza solo cuando los partidos requeridos estén oficialmente resueltos y exista una decisión administrativa explícita a su favor contra el inactivo. Esa decisión prevalece sobre el global deportivo anterior según el MER. La prueba C12 conserva una ida 4–0 y clasifica al rival tras la descalificación y el 3–0 de vuelta, sin borrar los cuatro eventos de ida. La resolución de la llave se etiqueta `administrativo`.

### Grupos y clasificación ya utilizada

Durante grupos, una inactividad motivada no borra puntos o resultados previos. Si un inactivo ocupa un cupo, la clasificación queda bloqueada: no se repesca automáticamente al siguiente por nombre, ID ni posición. El retiro de un no clasificado puede invalidar una firma previa y exigir nueva confirmación sin cambiar los candidatos válidos.

Si la eliminatoria derivada ya fue confirmada, la sanción posterior afecta a la participación eliminatoria actual y conserva la pertenencia histórica a grupos. La lectura reconstruye el estado de inscripción usado por esa fase mediante la auditoría anterior a la sanción y posterior a la generación del cuadro. Se usa el orden de registros auditados bajo el bloqueo común, sin inventar precisión temporal de los DATE históricos.

Así se conserva la huella y la clasificación de origen ya utilizadas, incluso cuando hubo partidos extra. La consulta histórica de esos desempates no vuelve a juzgar su elegibilidad con una sanción posterior; toda operación real de avance conserva la validación actual obligatoria. Se probaron clasificación histórica vigente, sanción en la final derivada, 3–0 del rival, avance y finalización correcta del campeonato.

## 3. DP-03 y decisiones extraordinarias pendientes

Cuando ambos participantes están inhabilitados, `ganador()` responde 409 antes de escoger un equipo. La segunda inactividad registra un bloqueo auditado `bloquear_doble_inactividad` en las llaves pendientes afectadas. La llave conserva sus plazas/procedencias y no recibe un ganador ficticio. También se rechaza beneficiar mediante 3–0 a un participante inactivo.

**Propuesta para aprobación deportiva:** abrir una resolución extraordinaria que identifique llave, ambas inscripciones, sanciones y destino afectado; declarar explícitamente si la llave queda sin clasificado; decidir por separado la vacante de la ronda siguiente y sus efectos en el calendario. La autoridad propuesta es el propietario, con motivo, autorización específica y antes/después completos. No se convierte esa vacante en un descanso de siembra ni se introduce un tercero sin una regla aprobada. Si hay destinos iniciados, la propuesta debe contemplar ese caso antes de permitir cambios.

Quedan pendientes de aprobación el efecto concreto de una llave sin clasificado y las vacantes de cupos oficiales antes de confirmar su cuadro. No se implementa una elección de sancionados ni un reemplazo arbitrario para aparentar que esos casos están resueltos. La sanción posterior a un cuadro de grupos ya confirmado sí se implementó conforme al retiro ordinario de eliminatorias descrito arriba.

DP-07 permanece intacta: elegibilidad DATE inclusiva, sin conversión de fechas ni recálculo de históricos.

## 4. Estadísticas, ciclo y React

Tablas de liga, tablas/minitablas de grupos, desempates, clasificación y avance usan el marcador administrativo oficial. El ciclo admite tanto oficiales deportivos como administrativos completos y sigue exigiendo campeón elegible, sin expedientes pendientes ni empates sin resolver.

Las estadísticas de equipo usan el 3–0 efectivo y no lo suman otra vez a los goles deportivos conservados de ese mismo partido. Las estadísticas individuales no reciben goles/asistencias administrativos; los eventos reales anteriores conservan el régimen de conteo existente. Un administrativo sin eventos da cero goles individuales. Las tarjetas y faltas reales tampoco se inventan ni se eliminan.

React reutiliza las pantallas de partido, equipo, cuadro, confirmación, expedientes y auditoría. Se incorporó `ResolucionAdministrativa` para seleccionar expresamente ambos participantes y motivo, solicitar un expediente de resultado y elegir una aprobación vigente antes de rectificar. El formulario no preselecciona ganadores. `InactivarEquipo` distingue retiro de descalificación. Los controles son privados del propietario en campeonato en curso; el modo arbitral no monta el formulario administrativo. Laravel sigue decidiendo resultado, elegibilidad, minitablas y clasificados.

La consulta pública conserva su lista explícita de campos: no se incorporan motivos disciplinarios, autorizaciones o responsables a espectadores. No se amplió el catálogo público ni se implementaron otros pendientes del plan de cierre.

## 5. Migración 000400

Archivo: `backend/database/migrations/2026_11_16_000400_add_resoluciones_administrativas.php`.

SHA-256: `fa1c7e536f44ee9c812b6c7a82de926d8285af71b7eaca2d1c4f2c09c3dbda9c`.

| Tabla | Columnas nuevas anulables |
| --- | --- |
| `partidos` | `administrativo_beneficiado_id`, `administrativo_sancionado_id`, `administrativo_por_id`, `motivo_administrativo` |
| `campeonato_equipos` | `motivo_inactividad`, `inactivado_at` |

No se crean tablas ni se cambia el tipo de estados existentes, que ya son VARCHAR. Hay tres FK RESTRICT nuevas: dos a inscripciones y una al usuario responsable. No se rellenan columnas antiguas ni se oficializan históricos. La migración se detiene antes del DDL si encuentra administrativos previos sin este contrato, para exigir revisión en vez de inventar motivos o responsables.

Se ensayó exclusivamente en MariaDB aislada: seis columnas anulables, tres FK RESTRICT y conservación de las huellas/cantidades de todos los campos anteriores. El ensayo incluye partido histórico ficticio finalizado sin oficialización y plantilla cerrada anterior: se conservaron sus valores. La migración de tokens se omitió.

**000400 no se aplicó a `beesocc_v2`.** No se modificó ninguna migración anterior, no se repitió 000300 en la principal y no se tocaron propietarios o históricos reales. El despliegue de 000400 requerirá preparación y autorización separadas.

## 6. Pruebas y reproducción

| Capa | Resultado |
| --- | --- |
| SQLite / kernel Laravel | **211 pruebas, 5.234 aserciones**, aprobadas |
| Nuevas regresiones administrativas | Diez pruebas, incluidos tres formatos, 4–0 de ida, doble inactividad, permisos/motivo, estadísticas, autorización consumible, conservación de IDs, rechazo con final iniciada, fallo de auditoría y clasificación histórica tras sanción |
| Frontend | **257 pruebas:** 210 Vitest + 47 Node; controles renderizados, contratos de servicios, permisos visuales y regresión de cuadro |
| MariaDB 10.4.32 aislada | **Cinco carreras nuevas aprobadas** y validación estructural/histórica |
| Calidad | Pint sobre PHP versionado y nuevos revisados, lint, build y diferencias aprobados; sin dependencias nuevas |

Las carreras usan procesos PHP/Laravel y conexiones independientes, una barrera en el campeonato y un observador InnoDB del propio laboratorio. Se identifican exactamente las dos conexiones en `INNODB_LOCK_WAITS`, se libera la barrera y se verifica 200/409, una auditoría de éxito, ausencia de eventos ficticios y conservación de participantes/filas. Casos: dos resultados, retiro frente a descalificación, administrativo frente a oficialización arbitral, doble consumo de rectificación y clasificación oficial frente a descalificación. No se repitieron las ocho carreras 6.2 ni las suites HTTP históricas.

La quinta carrera requirió refrescar el modelo de fase del fixture antes de obtener su firma: el objeto de preparación aún conservaba el cupo previo a la confirmación. Se acreditó después con firma válida y ambas esperas reales. No se interpretó el intento fallido como una carrera aprobada ni se cambió una regla deportiva para corregirlo.

No se ejecutaron recorridos nuevos de navegador ni HTTP de red en 6.3: las pruebas de endpoints son del kernel SQLite, las de React usan adaptador/renderizado estático y las concurrentes ejecutan servicios/controladores reales sobre MariaDB desde CLI. Los recorridos reales anteriores de 6.2 son antecedentes, no evidencia atribuida a esta interfaz nueva.

Para reproducir:

1. Usar `sprint33-iniciar-copia.ps1` y `preparar.php` para una instancia independiente 3317, datadir/redo/InnoDB/Aria separados y cuenta limitada. Aplicar ACL privada al directorio del motor y configuración; nunca seleccionar la principal para el ensayo.
2. Con `BEESOCC_INTEGRAL_CONFIG` del laboratorio, ejecutar una vez `sprint63-sembrar.php`. Exige base vacía y verifica identidad/permisos/rutas y rechazo de acceso a principal. Prepara datos anteriores y aplica 000400 únicamente allí, comprobando conservación e integridad. Se reutiliza una instancia y cuentas limitadas; no se copian datos reales.
3. Definir `BEESOCC_MARIADB_CONFIG` con el observador privado del mismo motor. Ejecutar `sprint63-carreras.php ejecutar` y luego `sprint63-carreras.php ejecutar_grupos`. Cada resultado final aprobado está protegido contra sobrescritura. Los ejecutores rechazan una configuración de aislamiento ausente con código 1, sin escrituras ni exposición de configuración. Revisar archivos privados de resultado, además del código de salida; no publicar logs, configuraciones o sesiones.
4. Backend: `php artisan test --compact` con SQLite `:memory:` del proyecto. Pint debe recibir la lista explícita de PHP de `git ls-files` y nuevos revisados, excluyendo `privadas-locales`.
5. Frontend: `npm run test:unit`, `npm test`, `npm run lint`, `npm run build`. Terminar con `git diff --check`.

Evidencia sanitizada: `backend/tests/Evidencias/sprint63-evidencia.json`. No contiene credenciales, SQL, sesiones, nombres reales ni rutas privadas. El motor y sus dos bases ficticias se conservan; solo se detiene la instancia temporal propia.

## 7. Principal, respaldo y estado final

La principal se consultó únicamente en modo lectura. La instantánea anterior/posterior coincide íntegramente: **28 tablas, 55 registros de referencia, mismos valores y estructuras, incluidas sesiones**. Mantiene sus 20 migraciones anteriores a 6.3; 000400 y sus columnas están ausentes. Se verificó nuevamente la cobertura de la FK de plantillas sin DDL correctivo.

Se conserva respaldo externo del código revisado de 6.3 con inventario SHA-256 y ACL privada. El puntero queda ignorado; no se incorporan archivos privados a Git. Los cambios de este sprint, informe, pruebas y evidencia quedan sin stage ni commit. `develop` termina en `3059179`, cinco commits locales por delante de `origin/develop`, sin push. No se inició otro sprint.
