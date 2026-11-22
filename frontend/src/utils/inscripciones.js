export function jugadoresDisponibles(jugadores, plantillas) {
  const inscritos = new Set(
    plantillas.filter((p) => !p.fecha_salida).map((p) => String(p.jugador_id)),
  )
  return jugadores.filter((j) => !inscritos.has(String(j.id)))
}

export const nombreCompleto = (jugador) =>
  [jugador.nombre, jugador.apellido].filter(Boolean).join(' ')

function normalizar(texto) {
  return texto.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase().trim()
}

export function buscarJugadores(jugadores, consulta) {
  const palabras = normalizar(consulta).split(/\s+/).filter(Boolean)
  if (!palabras.length) return jugadores
  return jugadores.filter((jugador) => {
    const nombre = normalizar(nombreCompleto(jugador))
    return palabras.every((palabra) => nombre.includes(palabra))
  })
}
