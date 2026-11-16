import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import partidoService from '../services/partidoService'
import { mensajeError } from '../services/api'
import ResultadoOficial from './ResultadoOficial'
function PartidoCard({ partido, ligaId, publica = false, arbitral = false }) {
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
    <ResultadoOficial partido={partido} />
    {publica && partido.tipo_resultado === 'administrativo' && partido.marcador_deportivo && <p className="nota">Marcador deportivo conservado: {partido.marcador_deportivo.local} – {partido.marcador_deportivo.visitante}</p>}
    {publica && partido.tipo_partido === 'desempate' && <p className="etiqueta">Partido adicional de desempate</p>}
    {!publica && error && <p className="nota" role="alert">{error}</p>}
    <p className="texto-suave">{partido.fecha_hora ? new Date(partido.fecha_hora).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) : 'Fecha por programar'} · {partido.lugar || 'Lugar por definir'}</p>
    {!publica && <Link className="enlace-texto" to={(arbitral ? '/mis-arbitrajes/' : '/ligas/') + ligaId + '/partidos/' + partido.id}>Ver partido y eventos</Link>}
  </article>
}
export default PartidoCard
