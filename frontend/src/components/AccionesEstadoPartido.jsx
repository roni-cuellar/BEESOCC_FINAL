import { accionesEstado } from '../utils/partidos'

function textoAccion(destino, estadoActual) {
  if (destino === 'en_curso') {
    return estadoActual === 'programado' ? 'Iniciar partido' : 'Reanudar'
  }

  if (destino === 'suspendido') return 'Suspender'
  if (destino === 'finalizado') return 'Finalizar'

  return 'Cancelar partido'
}

export default function AccionesEstadoPartido({
  estado,
  arbitral = false,
  bloqueado = false,
  onCambiar,
  omitirInicio = false,
}) {
  return accionesEstado(estado, arbitral)
    .filter((destino) => !(omitirInicio && estado === 'programado' && destino === 'en_curso'))
    .map((destino) => (
      <button
        key={destino}
        className="boton boton-borde"
        disabled={bloqueado}
        onClick={() => onCambiar(destino)}
      >
        {textoAccion(destino, estado)}
      </button>
    ))
}
