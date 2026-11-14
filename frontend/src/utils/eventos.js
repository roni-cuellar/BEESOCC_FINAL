export const tiposEvento = {
  gol: 'Gol', autogol: 'Autogol', asistencia: 'Asistencia',
  amarilla: 'Tarjeta amarilla', roja: 'Tarjeta roja', falta: 'Falta',
}

export function jugadoresDelEvento(partido, plantillas, tipo, participanteId) {
  const participante = partido.participantes.find(p => String(p.id) === String(participanteId))
  if (!participante) return []
  const equipo = tipo === 'autogol' ? partido.participantes.find(p => p.id !== participante.id) : participante
  const fecha = (partido.fecha_hora || new Date().toISOString()).slice(0, 10)
  // Se conserva el criterio del backend; el cierre del mismo día (DP-07) sigue pendiente.
  return (plantillas[equipo?.campeonato_equipo_id] || []).filter(p =>
    p.fecha_ingreso?.slice(0, 10) <= fecha && (!p.fecha_salida || p.fecha_salida.slice(0, 10) >= fecha))
}

export function golesParaAsistencia(eventos, participanteId, jugadorId) {
  return eventos.filter(e => e.tipo === 'gol' && String(e.equipo_partido_id) === String(participanteId)
    && String(e.plantilla.jugador_id) !== String(jugadorId)
    && !eventos.some(a => a.tipo === 'asistencia' && String(a.gol_relacionado_id) === String(e.id)))
}
