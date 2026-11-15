# Sprint 4.1 — Reglas aprobadas

Estado: reglamento aprobado expresamente por el usuario en esta conversación.
No autoriza migraciones sobre MySQL principal.
El usuario aprobó los estados mínimos: fase/ronda `planificacion`, `en_curso`,
`finalizada`; llave `pendiente`, `resuelta`; participante de fase `activo`.
Los apartados siguientes fueron confirmados y se implementan en el backend del Sprint 4.1.

## 1. Siembra, enfrentamientos y descansos

- El administrador indica un orden explícito de todas las inscripciones activas.
  No se infiere mérito deportivo por ID, fecha de inscripción o posición histórica.
- El orden constituye la siembra 1 a N, sin repetir equipos ni omitir inscritos activos.
- El cuadro tiene la menor potencia de dos mayor o igual a N; requiere al menos dos equipos.
- Orden estándar de plazas: para ocho, 1–8, 4–5, 2–7, 3–6; para cuatro, 1–4, 2–3.
  Las posiciones de siembra mayores que N son descansos. Así reciben descanso
  las mejores siembras, sin sorteo oculto, partidos ficticios ni goles inventados.
- Ejemplos: seis equipos producen 1–descanso, 4–5, 2–descanso, 3–6;
  cinco producen 1–descanso, 4–5, 2–descanso, 3–descanso.
- La vista previa muestra cruces y procedencias. La confirmación conserva ese orden
  y lo audita; se rechaza una segunda generación. No se edita organizativamente el cuadro confirmado;
  las correcciones autorizadas siguen el apartado 3.
- Cada ronda declara `unico` o `ida_vuelta`; plaza 1 es local en el primer partido,
  plaza 2 en la vuelta. Solo se generan partidos cuando ambas plazas están resueltas.

## 2. Empates y resolución arbitral

- La clasificación deportiva requiere todos los partidos de la llave finalizados y oficiales.
- Partido único: gana quien tenga más goles; ida/vuelta: se suman los goles
  de cada inscripción, independientemente de su localía. No existe gol visitante.
- Cada ronda elige `penales` o `prorroga_penales` antes de iniciar.
- En el segundo caso, los goles de la prórroga se registran como eventos reales
  en el mismo partido antes de oficializar, dentro del rango de minuto ya admitido.
  No se crean goles extra ni partidos para representar la prórroga.
- Si el marcador definitivo sigue empatado, el árbitro vigente registra únicamente
  los dos totales de la tanda en `llaves.penales_plaza_1/2`, enteros de 0 a 255,
  distintos, junto con un motivo. No se guarda detalle por lanzamiento.
- El árbitro certifica mediante ese motivo que se cumplió el método configurado.
  Laravel no deduce que se disputó una prórroga a partir de la ausencia de goles.
- La tanda solo resuelve una llave empatada y oficial; no suma goles al partido,
  goleadores, asistencias ni estadísticas. Se auditan responsable, fecha y antes/después.
- El avance lo confirma el administrador; Laravel calcula el ganador y actualiza
  las plazas derivadas de forma transaccional. Un segundo avance no duplica nada.
- Retiro, descalificación y doble descalificación bloquean esta resolución deportiva;
  su procedimiento administrativo y los resultados 3–0 no se amplían en este sprint.
- La corrección de una tanda ya registrada requiere un procedimiento específico:
  el expediente existente solo permite eventos. No se habilita una edición directa.

## 3. Correcciones después del avance

- Se conserva el expediente actual: motivo arbitral, aprobación administrativa,
  aplicación exclusiva del solicitante vigente, consumo único y auditoría.
- Antes de confirmar el lote, Laravel recalcula la llave afectada usando los eventos
  corregidos y los resultados oficiales. No modifica partidos anteriores ni su fecha oficial.
- Si el ganador no cambia, se conserva la clasificación y se audita la corrección.
- Si cambia, se revisan las plazas y partidos descendientes afectados.
  Se permite actualizar únicamente partidos `programado`, sin eventos ni oficialización;
  se conservan sus IDs y se auditan los participantes anteriores y posteriores.
- Los descansos derivados se recalculan y se propaga el nuevo participante sin crear partidos ficticios.
- Si la corrección deja un empate sin resolución, requiere rehacer una tanda o afecta
  un descendiente iniciado, suspendido, cancelado o finalizado, se rechaza todo el lote:
  sin cambios parciales, sin consumir la autorización y sin reabrir ni borrar partidos.
- Esas situaciones necesitan un procedimiento posterior de revisión administrativa;
  este sprint no incluye anulación de encuentros ya iniciados ni corrección de tandas.
- No se cambian silenciosamente solicitudes anteriores, responsables ni bitácoras.

DP-07, fase de grupos, publicación de solo oficiales y migración de históricos quedan fuera de alcance.
