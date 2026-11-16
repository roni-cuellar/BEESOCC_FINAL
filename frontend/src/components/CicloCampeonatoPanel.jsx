import { useEffect, useRef, useState } from 'react'
import ligaService from '../services/ligaService'
import useLigaStore from '../stores/useLigaStore'
import Aviso from './Aviso'
import Confirmacion from './Confirmacion'
import HistorialAuditoria from './HistorialAuditoria'
import { mensajeError } from '../services/api'

export default function CicloCampeonatoPanel({ liga }) {
  const [datos, setDatos] = useState(null)
  const [historial, setHistorial] = useState([])
  const [error, setError] = useState('')
  const [mensaje, setMensaje] = useState('')
  const [ocupado, setOcupado] = useState(false)
  const [confirmar, setConfirmar] = useState(null)
  const enviando = useRef(false)
  useEffect(() => {
    let vigente = true
    Promise.all([ligaService.configuracion(liga.id), ligaService.auditoriaCiclo(liga.id)])
      .then(([r, h]) => { if (vigente) { setDatos({ modalidad: r.configuracion.modalidad || '', minimo_jugadores: r.configuracion.minimo_jugadores ?? '' }); setHistorial(h) } })
      .catch(e => { if (vigente) setError(mensajeError(e)) })
    return () => { vigente = false }
  }, [liga.id])
  async function guardar(e) {
    e.preventDefault()
    if (enviando.current) return
    enviando.current = true; setOcupado(true); setError(''); setMensaje('')
    try {
      await ligaService.requisitosInicio(liga.id, { modalidad: datos.modalidad.trim(), minimo_jugadores: Number(datos.minimo_jugadores) })
      setMensaje('Requisitos guardados. El inicio verificará todas las plantillas y el calendario.')
      setHistorial(await ligaService.auditoriaCiclo(liga.id))
    } catch (e) { setError(mensajeError(e)) }
    finally { enviando.current = false; setOcupado(false) }
  }
  async function cambiarEstado() {
    const accion = confirmar
    try {
      await ligaService[accion](liga.id)
    } catch (e) {
      if (e.response?.status === 409) await useLigaStore.getState().cargarLiga(liga.id, { conservar: true })
      throw e
    }
    await useLigaStore.getState().cargarLiga(liga.id)
  }
  return <section className="panel mb-7" aria-label="Ciclo del campeonato">
    <h2>Ciclo del campeonato</h2>
    <Aviso error={error} exito={mensaje} />
    {liga.estado === 'planificacion' && <>
      <p className="nota">Define el mínimo de tu reglamento. No se asigna un número por modalidad automáticamente. Al iniciar se cierran las plantillas ordinarias.</p>
      {datos && <form onSubmit={guardar}><fieldset className="contents" disabled={ocupado}>
        <div className="dos-columnas"><label>Modalidad del campeonato<input required maxLength={50} value={datos.modalidad} onChange={e => setDatos({ ...datos, modalidad: e.target.value })} /></label>
          <label>Mínimo de jugadores elegibles por equipo<input required type="number" min="1" max="99" value={datos.minimo_jugadores} onChange={e => setDatos({ ...datos, minimo_jugadores: e.target.value })} /></label></div>
        <button className="boton boton-borde" disabled={!datos.modalidad.trim() || ocupado}>Guardar requisitos de inicio</button>
      </fieldset></form>}
      <button className="boton boton-verde mt-4" disabled={!datos || ocupado || !!error} onClick={() => setConfirmar('iniciar')}>Iniciar campeonato</button>
    </>}
    {liga.estado === 'en_curso' && <><p className="nota">Plantillas ordinarias cerradas. Completa las actas, desempates y clasificaciones antes de finalizar.</p><button className="boton boton-verde" onClick={() => setConfirmar('finalizar')}>Finalizar campeonato</button></>}
    {liga.estado === 'finalizado' && <p className="nota">Campeonato finalizado. Se conserva el historial y no se permiten modificaciones deportivas ordinarias.</p>}
    <details className="mt-4"><summary>Consultar auditoría del ciclo</summary><HistorialAuditoria historial={historial} contexto="ciclo del campeonato" /></details>
    {confirmar && <Confirmacion titulo={confirmar === 'iniciar' ? 'Iniciar campeonato y cerrar plantillas' : 'Finalizar campeonato'} texto={confirmar === 'iniciar' ? 'Se verificarán configuración, mínimo, equipos, plantillas, árbitro y estructura deportiva. No habrá transferencias ordinarias después.' : 'Se exigirán resultados oficiales completos y campeón resuelto. No se habilita reapertura extraordinaria.'} onConfirmar={cambiarEstado} onCerrar={() => setConfirmar(null)} />}
  </section>
}
