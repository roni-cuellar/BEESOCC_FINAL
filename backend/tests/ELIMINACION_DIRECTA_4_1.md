# Sprint 4.1 — Eliminación directa en Laravel

Reglas aprobadas expresamente en la conversación, conservadas en
`ELIMINACION_DIRECTA_4_1_REGLAS_PROPUESTAS.md`. Sin cambios en React ni MySQL.

## Implementación

- Formato `eliminacion_directa`; una fase eliminatoria y todas sus rondas configuradas antes de generar.
- Siembra explícita con todos los inscritos activos, vista previa y confirmación HMAC de ese contenido.
- Cuadro a potencia de dos, descansos para mejores siembras sin partidos ficticios y origen de plazas conservado.
- Uno o dos partidos por llave; marcador y global derivados de `MarcadorService`, sin gol visitante.
- Penales totales certificados por el árbitro vigente, con motivo, sin goles ni estadísticas ficticios.
- Avance administrativo solo con resultados oficiales; campeonato bloqueado en transacción y únicos físicos contra duplicados.
- Auditoría privada de fases, llaves, tandas, procedencias y cambios de participantes.
- Corrección aprobada: recalcula ganador y propaga hacia partidos programados sin eventos, conservando IDs.
  Si cambia una ronda iniciada/cerrada, deja empate sin resolver o afecta una tanda, revierte todo sin consumir autorización.
- Organización del partido limitada a fecha/lugar; no permite reasignar plazas, borrar partidos del cuadro ni insertar encuentros sueltos.
- Todos contra todos conserva sus flujos. La tabla por puntos no se aplica a eliminación: se consulta el cuadro.

Migración nueva, **pendiente en la principal**:
`database/migrations/2026_11_15_000100_create_estructura_eliminacion.php`.
Crea fases, fase_equipos, rondas, llaves y llave_plazas. Incluye `grupos` solo como soporte
vacío de las FK descritas en el MER; no hay endpoints ni generación funcional de grupos.
Agrega fase/grupo/llave/serie y tipo regular a partidos; no rellena relaciones de históricos.
CHECK de posición en MariaDB; posición 1/2 garantizada por el servicio en ambos motores.

Archivos principales: cinco modelos nuevos, FaseController, FasePolicy/LlavePolicy,
SiembraEliminacionService, GeneracionEliminacionService, AvanceEliminacionService y
AuditoriaEliminacionService; ajustes en Campeonato/Partido, Policies y controladores existentes,
CorreccionPartidoService, TablaPosicionesService y rutas.

## API privada con Sanctum y Policies

| Método | Ruta bajo `/api` | Uso |
| --- | --- | --- |
| GET / POST | `/campeonatos/{campeonato}/fases` | Consultar / crear fase |
| GET | `/fases/{fase}` | Cuadro, participantes y partidos |
| GET | `/fases/{fase}/auditoria` | Bitácora privada del cuadro |
| POST | `/fases/{fase}/previsualizar-llaves` | Vista previa sin crear encuentros |
| POST | `/fases/{fase}/generar-llaves` | Confirmación administrativa |
| POST | `/llaves/{llave}/penales` | Certificación arbitral de la tanda |
| POST | `/llaves/{llave}/avanzar` | Clasificación calculada por Laravel |

Crear fase: `nombre`, `tipo=eliminacion`. Previsualizar: `siembra` como lista ordenada
de IDs de inscripciones y `rondas` como lista de `{modalidad, desempate}`.
Generar envía esos mismos datos más `confirmacion` devuelta en la vista previa.
Penales recibe `penales_plaza_1`, `penales_plaza_2` y `motivo`; avanzar no recibe ganador.
Las rutas existentes del acta y del expediente de corrección se reutilizan.
Lectura del cuadro: propietario y árbitro vigente; configuración/avance: propietario;
tanda: árbitro vigente. Usuarios ajenos y asignaciones revocadas no acceden a auditoría.

## Verificación y pendientes

**SQLite en memoria: 141 pruebas, 2.824 aserciones**, incluidas 16 pruebas nuevas.
Cubren byes con tres/seis equipos, final completa, ida/vuelta empatada sin gol visitante,
penales, resultados no oficiales, IDs ajenos, duplicados, permisos/revocación,
corrección propagada y rechazada atómicamente, y migración sobre un partido histórico de copia.
**Frontend: 152 pruebas; lint y build aprobados. Pint y revisión de diferencias aprobados.**
No se atribuyen a esta migración las comprobaciones HTTP/MariaDB de sprints anteriores.
Pendiente: DDL, CHECK y concurrencia reales en MariaDB aislada; luego respaldo y autorización
antes de migrar la principal. Después conectar React. Retiros/descalificaciones y corrección
de tandas siguen bloqueados según el alcance aprobado. Grupos y DP-07 no se implementaron.
Rama `develop`, cambios sin commit; sin push ni dependencias nuevas.
