export const nombreInscripcion = (fase, inscripciones, id) => inscripciones.find(i => String(i.id) === String(id))?.equipo?.nombre
  || fase?.equipos?.find(e => String(e.campeonato_equipo_id) === String(id))?.inscripcion?.equipo?.nombre || `Equipo inscrito #${id}`

export function nombreOrigen(origen, fase, clasificacion, inscripciones) {
  if (origen == null) return 'Descanso'
  const candidato = clasificacion?.candidatos.find(c => `${c.origen_grupo_id}:${c.origen_puesto}` === origen)
  if (!candidato) return 'Procedencia por confirmar'
  const grupo = fase.grupos.find(g => g.id === candidato.origen_grupo_id)
  return `${grupo?.codigo || 'Grupo'} · puesto ${candidato.origen_puesto} · ${nombreInscripcion(fase, inscripciones, candidato.campeonato_equipo_id)}`
}

// Solo valida la forma del formulario; Laravel valida la distribución deportiva.
export function datosDistribucion(metodo, cantidad, cupo, asignaciones, activos) {
  const cantidad_grupos = Number(cantidad), clasificados_por_grupo = Number(cupo)
  if (!Number.isInteger(cantidad_grupos) || cantidad_grupos < 1 || cantidad_grupos > activos.length
    || !Number.isInteger(clasificados_por_grupo) || clasificados_por_grupo < 1 || clasificados_por_grupo > 255 || cantidad_grupos * clasificados_por_grupo < 2) return null
  const datos = { metodo, cantidad_grupos, clasificados_por_grupo }
  if (metodo === 'automatico') return datos
  if (activos.some(i => !asignaciones[i.id] || Number(asignaciones[i.id]) > cantidad_grupos)) return null
  const grupos = Array.from({ length: cantidad_grupos }, (_, g) => activos.filter(i => Number(asignaciones[i.id]) === g + 1).map(i => i.id))
  return grupos.every(g => g.length >= clasificados_por_grupo) ? { ...datos, grupos } : null
}
