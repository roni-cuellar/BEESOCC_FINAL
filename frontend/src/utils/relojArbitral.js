export function segundosDelReloj(panel, ahora) {
  const adicionales = panel.en_marcha ? Math.floor(Math.max(0, ahora - panel.recibidoEn) / 1000) : 0
  return Math.min(panel.duracion_minutos * 60, panel.segundos + adicionales)
}

export function tiempoVisible(segundos) {
  const minutos = Math.floor(segundos / 60)
  return [minutos, segundos % 60].map((parte) => String(parte).padStart(2, '0')).join(':')
}
