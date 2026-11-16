export const nombreEquipoFase = (fase, id) => fase.equipos?.find(e => String(e.id) === String(id))?.inscripcion?.equipo?.nombre || 'Equipo por confirmar'

export function nombrePlaza(fase, plaza) {
  if (plaza.fase_equipo_id) return nombreEquipoFase(fase, plaza.fase_equipo_id)
  if (plaza.origen_tipo === 'bye') return 'Descanso'
  const ronda = fase.rondas?.find(r => r.llaves.some(l => l.id === plaza.origen_llave_id))
  const origen = ronda?.llaves.find(l => l.id === plaza.origen_llave_id)
  return origen ? `Ganador de ${ronda.nombre} · llave ${origen.numero}` : 'Clasificado pendiente'
}

// Filtro visual; la autorización y la resolución deportiva siempre son de Laravel.
export function partidosOficiales(llave, ronda) {
  return llave.partidos?.length === (ronda.modalidad === 'ida_vuelta' ? 2 : 1)
    && llave.partidos.every(p => p.estado === 'finalizado' && p.oficializado_at && ['deportivo', 'administrativo'].includes(p.tipo_resultado))
}

export function validarSiembra(siembra, activos) {
  return activos.length >= 2 && siembra.length === activos.length && siembra.every(id => activos.some(i => i.id === Number(id)))
    && new Set(siembra.map(Number)).size === activos.length
}

export function datosCuadro(siembra, rondas) {
  return { siembra: siembra.map(Number), rondas: rondas.map(({ modalidad, desempate }) => ({ modalidad, desempate })) }
}
