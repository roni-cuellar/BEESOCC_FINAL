export const equipoFase = (fase, id) =>
  fase.equipos?.find((e) => String(e.id) === String(id))?.inscripcion?.equipo

export const nombreEquipoFase = (fase, id) => equipoFase(fase, id)?.nombre || 'Equipo por confirmar'

export function nombreRondaCuadro(ronda, restantes, mostrarFinal = true) {
  const nombres = ['Final', 'Semifinales', 'Cuartos de final', 'Octavos de final']
  return mostrarFinal && ronda.llaves.length === 2 ** restantes && /^Ronda \d+$/i.test(ronda.nombre)
    ? nombres[restantes] || ronda.nombre
    : ronda.nombre
}

export function llaveFinalCuadro(fase) {
  const ronda = fase.rondas?.at(-1)
  return ronda?.llaves.length === 1 && nombreRondaCuadro(ronda, 0).toLowerCase() === 'final'
    ? ronda.llaves[0]
    : null
}

export function campeonEnCuadro(fase, campeon) {
  const final = llaveFinalCuadro(fase)
  const ganador = final?.estado === 'resuelta' && equipoFase(fase, final.ganador_fase_equipo_id)
  return Boolean(campeon && ganador?.id != null && String(ganador.id) === String(campeon.id))
}

export function conexionesCuadro(rondas) {
  const origenes = new Map()
  rondas.forEach((ronda, indice) => {
    ronda.llaves.forEach((llave) => origenes.set(String(llave.id), { indice, llave }))
  })

  return rondas.flatMap((ronda, indice) =>
    ronda.llaves.flatMap((destino) =>
      destino.plazas.flatMap((plaza) => {
        if (plaza.origen_tipo !== 'ganador_llave') return []
        const origen = origenes.get(String(plaza.origen_llave_id))
        if (!origen || origen.indice >= indice) return []
        return [
          {
            origen: origen.llave.id,
            destino: destino.id,
            plaza: plaza.id,
            confirmada:
              origen.llave.estado === 'resuelta' &&
              plaza.fase_equipo_id != null &&
              String(plaza.fase_equipo_id) === String(origen.llave.ganador_fase_equipo_id),
          },
        ]
      }),
    ),
  )
}

export function nombrePlaza(fase, plaza) {
  if (plaza.fase_equipo_id) return nombreEquipoFase(fase, plaza.fase_equipo_id)
  if (plaza.origen_tipo === 'bye') return 'Descanso'
  const ronda = fase.rondas?.find((r) => r.llaves.some((l) => l.id === plaza.origen_llave_id))
  const origen = ronda?.llaves.find((l) => l.id === plaza.origen_llave_id)
  return origen ? `Ganador de ${ronda.nombre} · llave ${origen.numero}` : 'Clasificado pendiente'
}

// Filtro visual; la autorización y la resolución deportiva siempre son de Laravel.
export function partidosOficiales(llave, ronda) {
  return (
    llave.partidos?.length === (ronda.modalidad === 'ida_vuelta' ? 2 : 1) &&
    llave.partidos.every(
      (p) =>
        p.estado === 'finalizado' &&
        p.oficializado_at &&
        ['deportivo', 'administrativo'].includes(p.tipo_resultado),
    )
  )
}

export function validarSiembra(siembra, activos) {
  return (
    activos.length >= 2 &&
    siembra.length === activos.length &&
    siembra.every((id) => activos.some((i) => i.id === Number(id))) &&
    new Set(siembra.map(Number)).size === activos.length
  )
}

export function datosCuadro(siembra, rondas) {
  return {
    siembra: siembra.map(Number),
    rondas: rondas.map(({ modalidad, desempate }) => ({ modalidad, desempate })),
  }
}
