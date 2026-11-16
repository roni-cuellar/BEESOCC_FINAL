import { useEffect, useState } from 'react'
import useAuthStore from '../stores/useAuthStore'
import correccionService from '../services/correccionService'
import { mensajeError } from '../services/api'
import { descripcionOperacion, fechaExpediente, permisosCorreccion } from '../utils/correcciones'
import EstadoVista from './EstadoVista'
import Confirmacion from './Confirmacion'
import OperacionesCorreccion from './OperacionesCorreccion'
import HistorialAuditoria from './HistorialAuditoria'

export function ExpedienteCorreccion({ correccion, partido, arbitral, usuarioId, bloqueado, eventos, plantillas, onResolver, onAplicar }) {
  const permisos = permisosCorreccion(partido, correccion, arbitral, usuarioId)
  return <details className="expediente-correccion">
    <summary><strong>Expediente #{correccion.id}</strong><span className={'estado correccion-' + correccion.estado}>{correccion.estado}</span></summary>
    <p className="motivo-expediente">{correccion.motivo}</p>
    <dl className="datos-expediente">
      <div><dt>Solicitante</dt><dd>Usuario #{correccion.solicitado_por_id}</dd></div>
      <div><dt>Alcance</dt><dd>{correccion.alcance === 'eventos' ? 'Eventos deportivos' : 'Resultado'}</dd></div>
      <div><dt>Responsable de la decisión</dt><dd>{correccion.autorizado_por_id ? 'Usuario #' + correccion.autorizado_por_id : 'Pendiente'}</dd></div>
      <div><dt>Resolución</dt><dd>{fechaExpediente(correccion.fecha_resolucion)}</dd></div>
      <div><dt>Aplicación</dt><dd>{fechaExpediente(correccion.fecha_consumo)}</dd></div>
    </dl>
    {permisos.resolver && <div className="acciones acciones-compactas"><button className="boton boton-verde" disabled={bloqueado} onClick={() => onResolver(correccion, 'aprobada')}>Aprobar solicitud</button><button className="boton boton-borde" disabled={bloqueado} onClick={() => onResolver(correccion, 'rechazada')}>Rechazar solicitud</button></div>}
    {permisos.aplicar && <OperacionesCorreccion partido={partido} eventos={eventos} plantillas={plantillas} bloqueado={bloqueado} onAplicar={operaciones => onAplicar(correccion, operaciones)} />}
    {arbitral && correccion.estado === 'aprobada' && !permisos.aplicar && <p className="nota">Solo el solicitante con asignación vigente puede aplicar una aprobación para eventos deportivos.</p>}
    {correccion.estado === 'consumida' && <p className="nota">Esta autorización ya fue utilizada. No puede aplicarse de nuevo.</p>}
    {correccion.estado === 'rechazada' && <p className="nota">Esta solicitud fue rechazada y no permite modificar el acta.</p>}
  </details>
}

export default function PanelCorrecciones({ partido, arbitral, eventos, plantillas, bloqueado, onOperacion, onAccesoDenegado }) {
  const usuarioId = useAuthStore(estado => estado.usuario?.id)
  const [correcciones, setCorrecciones] = useState([])
  const [historial, setHistorial] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [revision, setRevision] = useState(0)
  const [motivo, setMotivo] = useState('')
  const [confirmacion, setConfirmacion] = useState(null)
  const permisos = permisosCorreccion(partido, null, arbitral, usuarioId)
  useEffect(() => {
    let vigente = true
    async function cargar() {
      setCargando(true); setError(''); setCorrecciones([]); setHistorial([])
      try {
        const [solicitudes, auditoria] = await Promise.all([correccionService.listar(partido.id), correccionService.auditoria(partido.id)])
        if (vigente) { setCorrecciones(solicitudes); setHistorial(auditoria) }
      } catch (e) {
        if (!vigente) return
        if ([401, 403, 404].includes(e.response?.status)) onAccesoDenegado(e)
        else setError(mensajeError(e))
      } finally { if (vigente) setCargando(false) }
    }
    cargar()
    return () => { vigente = false }
    // La página desmonta este panel al actualizar el acta; evita conservar expedientes obsoletos.
  }, [partido.id, revision, onAccesoDenegado])
  function solicitar(e) {
    e.preventDefault()
    if (bloqueado || !motivo.trim()) return
    setConfirmacion({ titulo: 'Solicitar corrección', texto: motivo.trim(), accion: () => onOperacion(() => correccionService.solicitar(partido.id, motivo.trim()), 'Solicitud enviada al administrador.') })
  }
  function resolver(correccion, estado) {
    setConfirmacion({ titulo: estado === 'aprobada' ? 'Aprobar solicitud' : 'Rechazar solicitud',
      texto: `Expediente #${correccion.id}: ${correccion.motivo}. ${estado === 'aprobada' ? 'El solicitante podrá aplicar un único lote de corrección.' : 'La solicitud quedará cerrada sin permitir cambios.'}`,
      accion: () => onOperacion(() => correccionService.resolver(correccion.id, estado), 'Solicitud ' + estado + '.') })
  }
  function aplicar(correccion, operaciones) {
    setConfirmacion({ titulo: 'Aplicar corrección aprobada', texto: <><p>Expediente #{correccion.id}. Revisa el orden: Laravel validará y aplicará el lote completo. La autorización quedará consumida.</p><ol>{operaciones.map((operacion, i) => <li key={i}>{i + 1}. {descripcionOperacion(operacion)}</li>)}</ol></>,
      accion: () => onOperacion(() => correccionService.aplicar(correccion.id, operaciones), 'Corrección aplicada. Acta y marcador actualizados desde Laravel.') })
  }
  return <section className="panel-resultados" aria-label="Resultados oficiales y correcciones">
    <div className="panel"><h2>Expedientes de corrección</h2>
      <p className="nota">El administrador resuelve las solicitudes. El árbitro solicitante aplica cambios de eventos; las rectificaciones de resultado se aplican desde la resolución administrativa por el propietario. El partido permanece finalizado.</p>
      <EstadoVista cargando={cargando} error={error} reintentar={() => setRevision(v => v + 1)} />
      {!cargando && !error && <>
        {!correcciones.length && <p className="nota">No hay solicitudes para este partido.</p>}
        {correcciones.map(correccion => <ExpedienteCorreccion key={correccion.id} {...{ correccion, partido, arbitral, usuarioId, eventos, plantillas, bloqueado }} onResolver={resolver} onAplicar={aplicar} />)}
        {permisos.solicitar && <form onSubmit={solicitar} className="solicitud-correccion"><h3>Solicitar corrección de eventos</h3>
          <label>Motivo de la corrección<textarea required maxLength={1000} rows={3} value={motivo} disabled={bloqueado} onChange={e => setMotivo(e.target.value)} /></label>
          <button className="boton boton-borde" disabled={bloqueado || !motivo.trim()}>Revisar solicitud</button>
        </form>}
      </>}
      {!permisos.solicitar && arbitral && <p className="nota">Las solicitudes se habilitan únicamente para resultados deportivos oficiales finalizados.</p>}
    </div>
    {!cargando && !error && <HistorialAuditoria historial={historial} />}
    {confirmacion && <Confirmacion titulo={confirmacion.titulo} texto={confirmacion.texto} onConfirmar={confirmacion.accion} onCerrar={() => setConfirmacion(null)} />}
  </section>
}
