# Sprint 5.3 — Grupos y eliminación

Implementación del 15 de noviembre de 2026 en `develop`. Laravel calcula los
resultados y la clasificación; React conserva su implementación anterior.

## Preservación e inventario recibido

Al iniciar había 19 archivos pendientes de 5.3 (13 modificados y seis nuevos),
no los 30 archivos de 5.1/5.2 mencionados en la solicitud. Los avances anteriores
ya estaban registrados en `de400c5` (5.1), `5703d3b` (5.2), `06ec485` y
`caad50c` (documentación y protección del laboratorio). Por ello no se crearon
dos commits adicionales con contenido repetido ni se reescribió el historial.
Se conservó fuera del repositorio un bundle Git de todas las referencias y una
copia de los 19 archivos pendientes, antes de modificar la implementación.
El temporal inicial del sandbox no persistió entre ejecuciones; el respaldo
durable externo conserva la captura inicial `4892834` (los mismos 19 archivos),
un ZIP de esos archivos, el bundle verificado y una copia del estado final.
Se encuentra en la raíz externa autorizada de visualizaciones, carpeta
`respaldo-sprint53`, fuera del repositorio y sin credenciales.
Los archivos de entorno, rutas temporales, cuentas y credenciales permanecen
ignorados. No se realizó push ni se ejecutaron migraciones sobre `beesocc_v2`.

## Fuentes y decisiones posteriores aprobadas

Se consultaron los documentos originales en `docs/`:

| Fuente original | Implementación y decisión posterior |
| --- | --- |
| MER: fases, grupos, fase_equipos, rondas, llaves y llave_plazas | Se reutilizan las entidades y la procedencia `grupo_puesto`; no existe un segundo motor de eliminación. |
| Manual RF-27 y D-21 | Orden: puntos, DG, GF, enfrentamiento directo y desempate adicional. |
| MER DP-01 y DP-05; Manual P-01 | La autorización expresa de 5.3 cierra estos puntos para este alcance: minitabla con puntos/DG/GF y reevaluación del subconjunto empatado; liguilla adicional de una vuelta si persiste igualdad múltiple. |
| Manual D-19 y D-20 | Cupo uniforme, cruces manuales o automáticos, vista previa completa y confirmación firmada. |
| Manual D-23 y D-24; MER DP-02 y Manual P-02 | Evitar cruces del mismo grupo cuando sea posible; descansos por mejor puesto y sorteo entre puestos equivalentes, sin comparar puntos de grupos diferentes. |
| Manual D-22 y P-04; eliminación 4.1/4.2 | El partido extra reutiliza llave, acta oficial, penales certificados por árbitro vigente y avance administrativo. |
| Auditoría y correcciones oficiales existentes | Propagación transaccional solo hacia partidos programados sin eventos ni oficialización; conservar IDs y autorización aprobada si se rechaza. |

El análisis 5.3 mencionado no estaba disponible como archivo independiente en
el repositorio. Se usa como autorización posterior la solicitud adjunta del
usuario, sin atribuir esas ampliaciones al MER ni al Manual originales.
Los informes 5.1 y 5.2 se conservan como registro histórico de sus límites.
DP-07, nuevas descalificaciones y resultados administrativos 3–0 siguen fuera
del alcance. Los nombres e IDs nunca deciden posiciones deportivas; los IDs
identifican pertenencias, expedientes y partidos.

## Desempates y estadísticas

`OrdenGruposService` separa bloques por puntos/DG/GF y aplica enfrentamientos
directos completos. Si la minitabla separa parcialmente, recalcula solo los
equipos todavía empatados. Las posiciones no resueltas son `null`.

`DesempateGrupoService` persiste un expediente por grupo, contexto y bloque.
Dos equipos reciben partido extra; tres o más reciben calendario de una vuelta.
Una nueva liguilla exige otra previsualización y confirmación administrativa:
nunca se crean ciclos automáticos. Los bloques independientes usan la misma
huella regular, de modo que resolver uno no invalida otro expediente vigente.
Las correcciones sí invalidan contextos deportivos cambiados; si un expediente
obsoleto ya tiene actividad, se rechaza íntegramente la corrección.

Solo los partidos regulares alimentan PJ, puntos y goles de la tabla de grupos.
Los goles reales de los partidos extra alimentan las estadísticas individuales
existentes del campeonato. La tanda queda en la llave y no suma goles.
No hay clasificación definitiva mientras falten resultados oficiales,
elegibilidad o cualquier desempate, incluso fuera del cupo.

## Conexión, confirmaciones y correcciones

`ClasificacionGruposService` conserva candidatos y procedencias auditadas.
`CrucesGruposService` utiliza exclusivamente esa clasificación vigente para
crear una fase posterior y delega la construcción al generador existente.
La vista incluye clasificados, todas las rondas, cruces, descansos, sorteo,
choques inevitables y firmas. Al confirmar se vuelven a comprobar las fuentes,
configuración y firmas bajo bloqueo del campeonato y transacción.

Las restricciones únicas impiden repetir la fase posterior o un expediente.
Una corrección invalida la confirmación anterior aunque conserve el clasificado
si cambió el marcador deportivo. Con cuadro generado, se actualizan las plazas
afectadas y se propagan descansos hacia rondas posteriores programadas.
Los intercambios dentro de una misma llave cambian los roles de sus participantes
sin eliminarlos ni violar la unicidad. Toda la operación revierte si afecta
actividad deportiva o falla la auditoría; no se consume la corrección aprobada.

## Modelo y API

La única migración adicional es
`2026_11_15_000200_create_desempates_grupos.php`: crea `grupo_desempates`,
agrega `fase_origen_id` único y `clasificacion_huella` a fases y enlaza
partidos mediante `grupo_desempate_id`. No modifica migraciones anteriores.
El modelo nuevo es `GrupoDesempate`; se amplían Fase, Partido y LlavePlaza.
Los controladores siguen siendo GrupoController y FaseController.

Todas las rutas tienen prefijo `/api`, Sanctum y Policies existentes:

| Método | Ruta | Uso |
| --- | --- | --- |
| GET | `/grupos/{grupo}/desempates` | Consultar expedientes autorizados |
| POST | `/grupos/{grupo}/previsualizar-desempate` | Recibe `clave`, devuelve expediente propuesto y firma |
| POST | `/grupos/{grupo}/generar-desempate` | Recibe `clave` y `confirmacion` |
| POST | `/fases/{fase}/previsualizar-eliminacion` | Recibe método y rondas; permite editar cruces con sorteo firmado |
| POST | `/fases/{fase}/generar-eliminacion` | Confirma rondas, método, sorteo, cruces y firmas |

Se reutilizan tabla de grupo, clasificación, acta/eventos/finalización,
correcciones, auditoría de fases y `/llaves/{llave}/penales` y `/avanzar`.
Administración configura, confirma y avanza; solo el árbitro vigente certifica
el acta y los penales. La lectura mantiene los permisos existentes.

## Verificación

| Comprobación | Resultado |
| --- | --- |
| Backend completo, SQLite en memoria | 188 pruebas, 4.489 aserciones |
| Casos 5.3, MariaDB aislada | 18 pruebas, 733 aserciones |
| Frontend, servicios Node | 47 pruebas |
| Frontend, Vitest | 149 pruebas |
| Regresión del ejecutor de integración 5.2 | 5 pruebas |
| Pint, lint, build y diff sin errores de espacio | Aprobados |

Se cubren minitablas de cuatro equipos, subconjuntos, liguillas repetidas,
partido extra, penales, separación estadística, procedencias, cupos, grupos
desiguales, cruces manuales y automáticos, sorteos, firmas caducadas, duplicados,
permisos, correcciones con descansos y rechazo atómico con auditoría fallida.
Se corrigió la prueba histórica de eliminación para crear únicamente el esquema
anterior a su migración objetivo; las migraciones posteriores no pertenecen a
esa instantánea histórica. La suite completa comprueba los formatos anteriores.

SQLite es el único destino de la suite predeterminada. Para MariaDB se ejecuta
explícitamente `tests/Integral/GruposEliminacionMariaDb.php`, con
`BEESOCC_TEST_MARIADB_CONFIG` apuntando al `entorno.json` privado generado por
`Integral/preparar.php` para una base nueva y vacía. El ejecutor exige testing
sin caché, puerto 3317, cuenta exclusiva, nombre temporal, permisos acotados y
las cuatro rutas físicas aisladas antes de crear el esquema. Cada caso revierte
sus datos; otra ejecución requiere otra base vacía. No hay borrado de bases.
La configuración administrativa y el motor se preparan conforme al laboratorio
documentado en 5.2, con directorio nuevo y `--no-defaults`.
El motor temporal se detuvo al finalizar, validando su directorio; se conservaron
los datos y las configuraciones privadas del laboratorio fuera del seguimiento Git.

Estas pruebas usan el kernel HTTP de Laravel con motores reales; no acreditan
navegador, HTTP de red ni nuevas carreras concurrentes del Sprint 5.3. La interfaz
de grupos queda para un sprint posterior, según el alcance autorizado.
