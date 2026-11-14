import { useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import partidoService from '../services/partidoService'
import arbitroService from '../services/arbitroService'
import useEquipoStore from '../stores/useEquipoStore'
import { mensajeError } from '../services/api'
import { nombreParticipante } from '../utils/partidos'
import AccionesEstadoPartido from '../components/AccionesEstadoPartido'
import EstadoVista from '../components/EstadoVista'
import Aviso from '../components/Aviso'
import Confirmacion from '../components/Confirmacion'
import Modal from '../components/Modal'
import PartidoForm from '../components/PartidoForm'
import EventoForm, { MinutoForm } from '../components/EventoForm'
import { useNavigate } from 'react-router-dom'

function PartidoDetallePage({ arbitral = false }) {
  const { ligaId, partidoId } = useParams()
  const navegar = useNavigate()
  const { inscripciones, cargar: cargarEquipos, error: errorEquipos } = useEquipoStore()
  const [partido, setPartido] = useState(null)
  const [eventos, setEventos] = useState([])
  const [marcador, setMarcador] = useState(null)
  const [plantillas, setPlantillas] = useState({})
  const [error, setError] = useState('')
  const [errorParcial, setErrorParcial] = useState('')
  const [cargando, setCargando] = useState(true)
  const [revision, setRevision] = useState(0)
  const [editar, setEditar] = useState(false)
  const [corregir, setCorregir] = useState(null)
  const [confirmacion, setConfirmacion] = useState(null)
  const [mensaje, setMensaje] = useState('')
  const [errorOperacion, setErrorOperacion] = useState('')
  const [operando, setOperando] = useState(false)
  const operacionPendiente = useRef(false)
  const accesoBloqueado = useRef(false)
  useEffect(() => { if (!arbitral) cargarEquipos(ligaId) }, [ligaId, cargarEquipos, arbitral])
  function bloquearAcceso(e) {
    accesoBloqueado.current = true
    setError(mensajeError(e)); setPartido(null); setEventos([]); setMarcador(null); setPlantillas({})
    setConfirmacion(null); setCorregir(null); setEditar(false); setMensaje('')
  }
  useEffect(() => {
    if (!arbitral) return
    let vigente = true
    let consultando = false
    async function comprobar() {
      if (consultando || accesoBloqueado.current) return
      consultando = true
      try { await arbitroService.partidos(ligaId) }
      catch (e) {
        if (vigente && [401, 403, 404].includes(e.response?.status)) bloquearAcceso(e)
      } finally { consultando = false }
    }
    window.addEventListener('focus', comprobar)
    const intervalo = window.setInterval(comprobar, 30000)
    return () => { vigente = false; window.removeEventListener('focus', comprobar); window.clearInterval(intervalo) }
  }, [ligaId, arbitral])
  useEffect(() => {
    let vigente = true
    async function consultar() {
      accesoBloqueado.current = false
      setCargando(true); setError(''); setErrorParcial(''); setPartido(null); setMarcador(null); setPlantillas({})
      try {
        const detalle = await partidoService.obtener(partidoId)
        if (String(detalle.campeonato_id) !== ligaId) throw new Error('liga_incorrecta')
        const [eventosRespuesta, marcadorRespuesta, listas] = await Promise.allSettled([
          partidoService.eventos(partidoId), partidoService.marcador(partidoId),
          arbitral ? arbitroService.plantillas(partidoId) : Promise.resolve({}),
        ])
        if (!vigente || accesoBloqueado.current) return
        const denegada = [eventosRespuesta, marcadorRespuesta, listas].find(r => r.status === 'rejected' && [401, 403, 404].includes(r.reason.response?.status))
        if (denegada) { bloquearAcceso(denegada.reason); return }
        if (eventosRespuesta.status === 'rejected') { setError(mensajeError(eventosRespuesta.reason)); return }
        const fallos = []
        if (listas.status === 'rejected') fallos.push(mensajeError(listas.reason))
        if (marcadorRespuesta.status === 'fulfilled') setMarcador(marcadorRespuesta.value)
        else fallos.push(mensajeError(marcadorRespuesta.reason))
        setPartido(detalle); setEventos(eventosRespuesta.value); setPlantillas(listas.status === 'fulfilled' ? listas.value : {}); setErrorParcial(fallos.join(' '))
      } catch (e) { if (vigente) setError(e.message === 'liga_incorrecta' ? 'Este partido no pertenece a la liga seleccionada.' : mensajeError(e)) }
      finally { if (vigente) setCargando(false) }
    }
    consultar()
    return () => { vigente = false }
  }, [ligaId, partidoId, revision, arbitral])
  function recargar(texto) { setMensaje(texto); setRevision(v => v + 1) }
  async function operar(accion, texto) {
    if (accesoBloqueado.current) throw { response: { status: 403 } }
    if (operacionPendiente.current) return
    operacionPendiente.current = true
    setOperando(true)
    setErrorOperacion(''); setMensaje('')
    try {
      await accion()
      if (!accesoBloqueado.current) { setCargando(true); recargar(texto) }
    } catch (e) {
      if ([401, 403, 404].includes(e.response?.status)) bloquearAcceso(e)
      else if (e.response?.status === 409) {
        setErrorOperacion(mensajeError(e)); setConfirmacion(null); setCorregir(null)
        setRevision(v => v + 1)
      }
      throw e
    } finally { operacionPendiente.current = false; setOperando(false) }
  }
  const editable = partido && !['finalizado', 'cancelado'].includes(partido.estado)
  const plantillasListas = partido?.participantes.length === 2 && partido.participantes.every(p => Boolean(plantillas[p.campeonato_equipo_id]))
  return <>
    <Link className="enlace-texto" to={(arbitral ? '/mis-arbitrajes/' : '/ligas/') + ligaId + '/partidos'}>Partidos</Link>
    <EstadoVista cargando={cargando} error={error} reintentar={() => setRevision(v => v + 1)} />
    {!cargando && !error && partido && <>
      <div className="titulo-pagina"><div><h1>Partido</h1><p className="texto-suave">Jornada {partido.jornada} · {partido.fecha_hora ? new Date(partido.fecha_hora).toLocaleString('es-CO') : 'Fecha por programar'} · {partido.lugar || 'Lugar por definir'}</p></div><span className={'estado estado-' + partido.estado}>{partido.estado.replaceAll('_', ' ')}</span></div>
      <Aviso error={errorOperacion || errorParcial || (!arbitral && errorEquipos)} exito={mensaje} />
      <div className="panel marcador-detalle"><div><span>LOCAL</span><strong>{marcador?.local.nombre || nombreParticipante(partido, 'local')}</strong></div><b>{marcador ? marcador.local.goles + ' – ' + marcador.visitante.goles : 'No disponible'}</b><div><span>VISITANTE</span><strong>{marcador?.visitante.nombre || nombreParticipante(partido, 'visitante')}</strong></div></div>
      <div className="acciones acciones-compactas">
        <button className="boton boton-borde" disabled={operando} onClick={() => setRevision(v => v + 1)}>Actualizar acta</button>
        {!arbitral && editable && <button className="boton boton-borde" onClick={() => setEditar(true)}>Editar partido</button>}
        <AccionesEstadoPartido estado={partido.estado} arbitral={arbitral} bloqueado={operando} onCambiar={estado => setConfirmacion({ titulo: 'Cambiar estado del partido', texto: '¿Cambiar de ' + partido.estado.replaceAll('_', ' ') + ' a ' + estado.replaceAll('_', ' ') + '? Los estados finalizado y cancelado son definitivos.', accion: () => operar(() => partidoService.actualizar(partidoId, { estado }), 'Estado actualizado.') })} />
        {!arbitral && <button className="boton-texto peligro" onClick={() => setConfirmacion({ titulo: 'Eliminar partido', texto: '¿Eliminar el partido? Solo es posible si no tiene eventos.', accion: async () => { await partidoService.eliminar(partidoId); navegar('/ligas/' + ligaId + '/partidos') } })}>Eliminar partido</button>}
      </div>
      <div className={arbitral ? 'detalle-eventos' : 'mt-6'}>
        <div className="panel"><h2>Historial de eventos</h2>{!eventos.length && <p className="nota">No hay eventos registrados.</p>}
          <ol className="lista-eventos">{eventos.map(e => <li key={e.id}><span className="minuto-evento">{e.minuto}′</span><div><strong>{e.tipo} · {e.plantilla.jugador.nombre} {e.plantilla.jugador.apellido}</strong><p>{e.tipo === 'autogol' ? 'Beneficia a ' : ''}{e.equipo_partido.campeonato_equipo.equipo.nombre}{e.gol_relacionado_id ? ' · Gol relacionado #' + e.gol_relacionado_id : ''}</p>
            {arbitral && partido.estado === 'en_curso' && <div className="acciones acciones-compactas"><button className="boton-texto" disabled={operando} onClick={() => setCorregir(e)}>Corregir minuto</button><button className="boton-texto peligro" disabled={operando} onClick={() => setConfirmacion({ titulo: 'Eliminar evento', texto: '¿Eliminar este evento? Para borrar un gol, elimina primero su asistencia.', accion: () => operar(() => partidoService.eliminarEvento(e.id), 'Evento eliminado.') })}>Eliminar evento</button></div>}</div></li>)}</ol>
        </div>
        {arbitral && (partido.estado === 'en_curso' && plantillasListas ? <EventoForm bloqueado={operando} partido={partido} eventos={eventos} plantillas={plantillas} onGuardar={datos => operar(() => partidoService.crearEvento(partidoId, datos), 'Evento registrado correctamente.')} />
          : <div className="panel"><h2>Registro de eventos</h2><p className="nota">{partido.estado === 'en_curso' ? 'No se pudieron cargar las plantillas.' : 'Los eventos se habilitan cuando el partido está en curso.'}</p></div>)}
      </div>
      {editar && <Modal titulo="Editar partido" onCerrar={() => setEditar(false)}><PartidoForm partido={partido} inscripciones={inscripciones} permitirEquipos={partido.estado === 'programado' && eventos.length === 0} onCancelar={() => setEditar(false)} onGuardar={async datos => { await partidoService.actualizar(partidoId, datos); setEditar(false); recargar('Partido actualizado.') }} /></Modal>}
      {corregir && <Modal bloqueado={operando} titulo="Corregir minuto del evento" onCerrar={() => setCorregir(null)}><MinutoForm evento={corregir} onGuardar={async minuto => { await operar(() => partidoService.actualizarEvento(corregir.id, minuto), 'Minuto corregido.'); setCorregir(null) }} /></Modal>}
    </>}
    {confirmacion && <Confirmacion titulo={confirmacion.titulo} texto={confirmacion.texto} onConfirmar={confirmacion.accion} onCerrar={() => setConfirmacion(null)} />}
  </>
}
export default PartidoDetallePage
