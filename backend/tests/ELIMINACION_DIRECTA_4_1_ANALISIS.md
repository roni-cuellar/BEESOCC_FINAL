# Sprint 4.1 — Comparación previa del modelo

Análisis sobre `develop`, antes de modificar código deportivo o migraciones.
Estado posterior: decisiones confirmadas por el usuario e implementadas; consultar
`ELIMINACION_DIRECTA_4_1.md` y el reglamento aprobado en `ELIMINACION_DIRECTA_4_1_REGLAS_PROPUESTAS.md`.
Referencias: MER consolidado, diccionarios de fases/rondas/llaves, R12–R27,
I20–I27, C13–C16, DP-02 y anexo III; Manual Técnico RF-27, D-20–D-25, P-02 y P-04.

## Comparación con el backend

| Modelo aprobado | Implementación actual | Ampliación necesaria |
| --- | --- | --- |
| Campeonato con formato de eliminación | Columna `formato` VARCHAR; modelo y API solo permiten todos contra todos | Habilitar el nuevo formato sin transformar campeonatos anteriores |
| `fases` | No existe | Campeonato, orden, nombre, tipo, estado; unicidad campeonato/orden |
| `fase_equipos` | No existe | Fase, inscripción, siembra opcional y estado; unicidad fase/inscripción |
| `rondas` | No existe | Fase, número, nombre, modalidad, desempate y estado; unicidad fase/número |
| `llaves` | No existe | Ronda, número, estado, ganador de fase, método y penales; unicidad ronda/número |
| `llave_plazas` | No existe | Dos posiciones, participante y procedencia; unicidad llave/posición |
| Ubicación de `partidos` | Solo campeonato y jornada | FK opcionales de fase/llave y número de serie; preservar IDs y registros anteriores |
| Resultados oficiales y auditoría | Implementados | Reutilizar `MarcadorService`, cierre oficial, actor/motivo/antes/después y transacciones |

El MER contiene además `grupo_id` y `origen_grupo_id`, FK hacia `grupos`.
La fase de grupos está excluida del sprint. Es necesario distinguir una eventual
tabla de soporte de FK de la implementación funcional de grupos; no crear FK
sin destino ni sustituir silenciosamente el modelo por identificadores sin relación.
Los nombres del Manual son orientativos; el diccionario detallado del MER identifica
al ganador como `ganador_fase_equipo_id`, no como un segundo equipo o marcador.

## Reglas expresas que pueden reutilizarse

- Un partido único o dos partidos ida/vuelta según cada ronda, sin gol visitante.
- El marcador deportivo deriva de eventos; los penales de tanda pertenecen a la llave.
- Solo resultados oficiales válidos pueden resolver la llave; un empate no identifica ganador.
- Dos plazas por llave; un descanso no genera un partido ficticio.
- Una inscripción no ocupa dos llaves de la misma ronda; fase, llave y partido pertenecen al mismo campeonato.
- Administración organiza; el árbitro vigente registra eventos y oficializa.
- No borrar partidos, enfrentamientos ni auditoría al avanzar.

## Decisiones solicitadas antes de implementar

1. DP-02/P-02 no define siembra ni asignación objetiva de descansos automáticos.
   Se propone iniciar con cruces manuales y cantidad potencia de dos, bloqueando
   empates y resoluciones de retiro/descalificación todavía no implementadas.
2. No se define cómo una corrección posterior que cambia al ganador modifica
   clasificados, plazas y partidos descendientes. Se propone bloquear la aplicación
   tras clasificar hasta disponer de un procedimiento auditado aprobado.
3. Fases, rondas, llaves y participantes tienen estado VARCHAR, sin catálogo completo.
   Se solicitaron estados mínimos explícitos, sin dar por aprobados valores nuevos.

En la etapa inicial de este análisis se detuvo la implementación para solicitar
las decisiones indicadas. Las respuestas posteriores habilitaron el trabajo
documentado en el informe de implementación. No se accedió a MySQL ni se modificó React.
