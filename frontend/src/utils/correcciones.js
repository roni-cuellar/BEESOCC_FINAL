export function esResultadoOficial(partido) {
  return partido.estado === 'finalizado' && Boolean(partido.oficializado_at)
}

// Estos controles orientan la interfaz; Laravel vuelve a autorizar cada operación.
export function permisosCorreccion(partido, correccion, arbitral, usuarioId) {
  const deportivo = esResultadoOficial(partido) && partido.tipo_resultado === 'deportivo'
  return {
    solicitar: deportivo && arbitral,
    resolver:
      esResultadoOficial(partido) &&
      !arbitral &&
      (deportivo || correccion?.alcance === 'resultado') &&
      correccion?.estado === 'solicitada',
    aplicar:
      deportivo &&
      arbitral &&
      usuarioId != null &&
      correccion?.estado === 'aprobada' &&
      correccion.alcance === 'eventos' &&
      String(correccion.solicitado_por_id) === String(usuarioId),
  }
}

export function fechaExpediente(fecha) {
  return fecha ? new Date(fecha).toLocaleString('es-CO') : 'Sin registrar'
}

export function descripcionOperacion(operacion) {
  if (operacion.accion === 'registrar')
    return `Registrar ${operacion.datos.tipo} en el minuto ${operacion.datos.minuto} · jugador de plantilla #${operacion.datos.plantilla_id}`
  if (operacion.accion === 'corregir_minuto')
    return `Evento #${operacion.evento_id}: cambiar al minuto ${operacion.minuto}`
  return `Eliminar evento #${operacion.evento_id}`
}
