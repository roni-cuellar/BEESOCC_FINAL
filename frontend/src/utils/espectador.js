export function ultimosResultados(partidos) {
  const fecha = (p) => Date.parse(p.oficializado_at || p.fecha_hora) || 0
  return partidos
    .filter((p) => p.estado === 'finalizado')
    .sort((a, b) => fecha(b) - fecha(a) || b.id - a.id)
    .slice(0, 3)
}
