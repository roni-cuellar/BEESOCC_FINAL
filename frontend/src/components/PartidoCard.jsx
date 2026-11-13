import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import partidoService from '../services/partidoService'
import { mensajeError } from '../services/api'
function PartidoCard({ partido, ligaId, publica = false }) {
  const [marcador, setMarcador] = useState(null)
  const [error, setError] = useState('')
  useEffect(() => {
    if (publica) return
    let vigente = true
    partidoService.marcador(partido.id).then(datos => { if (vigente) setMarcador(datos) })
      .catch(e => { if (vigente) setError(mensajeError(e)) })
    return () => { vigente = false }
  }, [partido.id, partido.updated_at, publica])
  const participanteLocal = partido.participantes?.find(p => p.rol === 'local')
  const participanteVisitante = partido.participantes?.find(p => p.rol === 'visitante')
  const local = (publica ? participanteLocal?.equipo?.nombre : participanteLocal?.campeonato_equipo?.equipo?.nombre) || 'Local no definido'
  const visitante = (publica ? participanteVisitante?.equipo?.nombre : participanteVisitante?.campeonato_equipo?.equipo?.nombre) || 'Visitante no definido'
  const resultado = publica ? (participanteLocal && participanteVisitante ? `${participanteLocal.goles} – ${participanteVisitante.goles}` : '—') : (marcador ? marcador.local.goles + ' – ' + marcador.visitante.goles : '—')
  return <article className="panel partido-card">
    <div className="fila-entre"><span className="etiqueta">JORNADA {partido.jornada}</span><span className={'estado estado-' + partido.estado}>{partido.estado.replaceAll('_', ' ')}</span></div>
    <div className="partido-equipos"><strong>{local}</strong><span className="partido-marcador">{resultado}</span><strong>{visitante}</strong></div>
    {!publica && error && <p className="nota" role="alert">{error}</p>}
    <p className="texto-suave">{partido.fecha_hora ? new Date(partido.fecha_hora).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) : 'Fecha por programar'} · {partido.lugar || 'Lugar por definir'}</p>
    {!publica && <Link className="enlace-texto" to={'/ligas/' + ligaId + '/partidos/' + partido.id}>Ver partido y eventos</Link>}
  </article>
}
export default PartidoCard
