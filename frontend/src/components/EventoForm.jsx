import { useRef, useState } from 'react'
import Aviso from './Aviso'
import { mensajeError } from '../services/api'
import { tiposEvento, jugadoresDelEvento, golesParaAsistencia } from '../utils/eventos'

function EventoForm({ partido, plantillas, eventos, onGuardar, bloqueado = false }) {
  const [datos, setDatos] = useState({ tipo: 'gol', equipo_partido_id: '', plantilla_id: '', minuto: 0, gol_relacionado_id: '' })
  const [error, setError] = useState('')
  const [guardando, setGuardando] = useState(false)
  const enviando = useRef(false)
  const participante = partido.participantes.find(p => String(p.id) === String(datos.equipo_partido_id))
  const opciones = jugadoresDelEvento(partido, plantillas, datos.tipo, datos.equipo_partido_id)
  const plantilla = opciones.find(p => String(p.id) === String(datos.plantilla_id))
  const goles = golesParaAsistencia(eventos, datos.equipo_partido_id, plantilla?.jugador_id)
  function cambiar(e) {
    const { name, value } = e.target
    setDatos(actual => ({ ...actual, [name]: value, ...(name === 'tipo' || name === 'equipo_partido_id' ? { plantilla_id: '', gol_relacionado_id: '' } : {}), ...(name === 'plantilla_id' ? { gol_relacionado_id: '' } : {}) }))
  }
  async function guardar(e) {
    e.preventDefault()
    if (enviando.current || bloqueado) return
    enviando.current = true
    setGuardando(true); setError('')
    try {
      await onGuardar({ tipo: datos.tipo, equipo_partido_id: Number(datos.equipo_partido_id), plantilla_id: Number(datos.plantilla_id), minuto: Number(datos.minuto), gol_relacionado_id: datos.tipo === 'asistencia' ? Number(datos.gol_relacionado_id) : null })
      setDatos({ ...datos, plantilla_id: '', gol_relacionado_id: '' })
    } catch (e) { setError(mensajeError(e)) }
    finally { enviando.current = false; setGuardando(false) }
  }
  return <form onSubmit={guardar} className="panel" aria-busy={guardando || undefined}><h2>Registrar evento</h2><Aviso error={error} />
    <fieldset className="contents" disabled={guardando || bloqueado}>
    <div className="dos-columnas"><label>Tipo de evento<select name="tipo" value={datos.tipo} onChange={cambiar}>{Object.entries(tiposEvento).map(([tipo, titulo]) => <option key={tipo} value={tipo}>{titulo}</option>)}</select></label><label>Minuto<input required min="0" max="150" step="1" type="number" name="minuto" value={datos.minuto} onChange={cambiar} /></label></div>
    <label>{datos.tipo === 'autogol' ? 'Equipo beneficiado por el autogol' : 'Equipo participante'}<select name="equipo_partido_id" required value={datos.equipo_partido_id} onChange={cambiar}><option value="">Selecciona un participante</option>{partido.participantes.map(p => <option key={p.id} value={p.id}>{p.campeonato_equipo.equipo.nombre} · {p.rol}</option>)}</select></label>
    {datos.tipo === 'autogol' && <p className="aviso aviso-info">Selecciona al jugador rival que cometió el autogol.</p>}
    <label>{datos.tipo === 'autogol' ? 'Jugador rival que cometió el autogol' : 'Jugador'}<select name="plantilla_id" required disabled={!participante} value={datos.plantilla_id} onChange={cambiar}><option value="">Selecciona un jugador activo</option>{opciones.map(p => <option key={p.id} value={p.id}>#{p.dorsal} · {p.jugador.nombre} {p.jugador.apellido}</option>)}</select></label>
    {participante && !opciones.length && <p className="nota">No hay jugadores activos en esta plantilla.</p>}
    {datos.tipo === 'asistencia' && <label>Gol relacionado<select name="gol_relacionado_id" required disabled={!plantilla} value={datos.gol_relacionado_id} onChange={cambiar}><option value="">Selecciona un gol válido sin asistencia</option>{goles.map(g => <option key={g.id} value={g.id}>Gol #{g.id} · {g.plantilla.jugador.nombre} {g.plantilla.jugador.apellido} · minuto {g.minuto}</option>)}</select></label>}
    <button className="boton boton-verde" disabled={guardando || bloqueado}>{guardando ? 'Registrando…' : 'Registrar evento'}</button>
    </fieldset>
    
  </form>
}
export function MinutoForm({ evento, onGuardar }) {
  const enviando = useRef(false)
  const [minuto, setMinuto] = useState(evento.minuto)
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState('')
  async function guardar(e) {
    e.preventDefault()
    if (enviando.current) return
    enviando.current = true
    setGuardando(true); setError('')
    try { await onGuardar(minuto) } catch(e) { setError(mensajeError(e)) }
    finally { enviando.current = false; setGuardando(false) }
  }
  return <form onSubmit={guardar}><Aviso error={error} /><label>Nuevo minuto<input type="number" min="0" max="150" step="1" required value={minuto} onChange={e => setMinuto(e.target.value)} /></label><button className="boton boton-verde" disabled={guardando}>{guardando ? 'Guardando…' : 'Corregir minuto'}</button></form>
}
export default EventoForm
