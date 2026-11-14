import { accionesEstado } from '../utils/partidos'

export default function AccionesEstadoPartido({ estado, arbitral = false, bloqueado = false, onCambiar }) {
  return accionesEstado(estado, arbitral).map(destino => <button key={destino} className="boton boton-borde" disabled={bloqueado} onClick={() => onCambiar(destino)}>
    {destino === 'en_curso' ? (estado === 'programado' ? 'Iniciar partido' : 'Reanudar') : destino === 'suspendido' ? 'Suspender' : destino === 'finalizado' ? 'Finalizar' : 'Cancelar partido'}
  </button>)
}
