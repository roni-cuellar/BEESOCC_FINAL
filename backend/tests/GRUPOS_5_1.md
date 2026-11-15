# Sprint 5.1: backend de grupos

## Alcance y modelo

Se admite `grupos_eliminacion` al crear campeonatos. Se reutilizan las tablas
`fases`, `grupos`, `fase_equipos`, `partidos` y la auditoría existente.
No hay migraciones nuevas ni modificaciones a migraciones anteriores.
La creación de fase utiliza el endpoint existente de fases con `tipo: grupos`.
Los otros formatos conservan sus generadores y servicios.

## Contrato privado

Todas las rutas tienen el prefijo `/api`, Sanctum y autorización por propietario
o árbitro vigente. El árbitro consulta y conserva sus permisos deportivos;
la configuración y clasificación corresponden exclusivamente al propietario.

| Método | Ruta | Uso |
| --- | --- | --- |
| POST | `/fases/{fase}/previsualizar-grupos` | Distribución sin persistencia |
| POST | `/fases/{fase}/confirmar-grupos` | Confirma exactamente la vista firmada |
| POST | `/fases/{fase}/generar-calendario-grupos` | Una vuelta por grupo |
| GET | `/grupos/{grupo}/tabla?vista=oficial` | Tabla oficial; por defecto provisional |
| GET | `/fases/{fase}/clasificacion-grupos` | Candidatos, bloqueos y confirmación |
| POST | `/fases/{fase}/clasificar-grupos` | Registra la clasificación auditada |

La distribución recibe `metodo` (`manual` o `automatico`), `cantidad_grupos` y
`clasificados_por_grupo`. La manual añade `grupos`, una lista de listas de IDs
de **inscripciones**, no de equipos. Deben aparecer todos los inscritos activos
exactamente una vez. La automática sortea y equilibra los tamaños.
Confirmar requiere esos mismos datos, los grupos devueltos y `confirmacion`;
se omite `fase_id` del cuerpo. No se repite el sorteo al confirmar.
Las escrituras usan transacciones y bloqueo del campeonato antes de la fase.
La distribución confirmada congela nuevas inscripciones; no hay redistribución.

El calendario no recibe equipos ni vueltas: genera pares únicos de cada grupo,
jornadas y descansos sin partidos ficticios. Los encuentros reutilizan arbitraje,
eventos, oficialización y correcciones existentes. No se permite reasignar sus
participantes, cambiar sus jornadas ni eliminarlos individualmente.

## Reglas documentadas y límites

Se aplican 3/1/0 puntos y desempates por puntos, diferencia y goles a favor.
El enfrentamiento directo se utiliza únicamente cuando quedan dos empatados.
Un empate absoluto informa `partido_extra_pendiente`; los empates múltiples
informan `empate_multiple_pendiente_DP01_DP05`, sin minitablas ni desempate por ID.
No se genera todavía el partido extra. DP-07 no cambia.
La tabla informa resultados completos y posiciones definitivas separadamente;
ninguna clasificación se confirma con partidos incompletos, resultados sin
oficializar, inscritos retirados o desempates pendientes, incluso fuera del cupo.
No hay resultados administrativos automáticos ni reglas nuevas de descalificación.

La clasificación exige cupo uniforme, vuelve a calcular las tablas oficiales y
registra una instantánea auditada con huella, inscripción, fase-equipo y origen
`grupo_puesto` (grupo y posición). No cambia los estados de participación de los
equipos ni crea plazas o cruces: la asignación a `llave_plazas` queda bloqueada
hasta aprobar cruces y tratamiento de correcciones posteriores. El generador de
eliminación directa existente no se modifica ni se usa con todos los inscritos
para simular una clasificación de grupos.

Una corrección deportiva cambia la huella e invalida la clasificación existente.
Una corrección de minuto sin efecto deportivo conserva su vigencia.
Si hay rondas de una eliminatoria posterior, la corrección deportiva se rechaza
atómicamente con 409 y no consume la autorización. Esta protección conservadora
se mantiene hasta aprobar la propagación. El historial usa la ruta de auditoría
de fases existente.

## Verificación y límites del laboratorio

`tests/Feature/GruposTest.php` verifica configuración, distribución, firmas,
duplicados, descansos, grupos desiguales, calendario, tablas, permisos, resultados
oficiales, clasificación, correcciones y compatibilidad.
Las pruebas HTTP de Laravel ejecutan su kernel con SQLite en memoria y los
resguardos de `BackendTestCase`; no equivalen a HTTP de red, navegador real ni
integración MariaDB. No se accedió a `beesocc_v2` para este sprint.
Queda pendiente validar aisladamente MariaDB y la interfaz en sprints posteriores.
