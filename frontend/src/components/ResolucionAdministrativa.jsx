import { useEffect, useState } from 'react'
import administrativo from '../services/administrativoService'
import correcciones from '../services/correccionService'
import useAuthStore from '../stores/useAuthStore'
import Confirmacion from './Confirmacion'
import Aviso from './Aviso'
import { mensajeError } from '../services/api'

export default function ResolucionAdministrativa({ partido, habilitado, bloqueado, onOperacion }) {
  const usuarioId = useAuthStore(e => e.usuario?.id)
  const [beneficiado, setBeneficiado] = useState('')
  const [sancionado, setSancionado] = useState('')
  const [motivo, setMotivo] = useState('')
  const [autorizacion, setAutorizacion] = useState('')
  const [aprobadas, setAprobadas] = useState([])
  const [error, setError] = useState('')
  const [confirmar, setConfirmar] = useState(null)
  useEffect(() => {
    let vigente = true
    if (habilitado && partido.oficializado_at) correcciones.listar(partido.id)
      .then(datos => { if (vigente) setAprobadas(datos.filter(c => c.alcance === 'resultado' && c.estado === 'aprobada' && String(c.solicitado_por_id) === String(usuarioId))) })
      .catch(e => { if (vigente) setError(mensajeError(e)) })
    return () => { vigente = false }
  }, [partido.id, partido.oficializado_at, habilitado, usuarioId])
  if (!habilitado || (!partido.oficializado_at && !['programado', 'en_curso', 'suspendido'].includes(partido.estado))) return null
  const participantes = partido.participantes || []
  function revisar(e) {
    e.preventDefault()
    const datos = { beneficiado_id: Number(beneficiado), sancionado_id: Number(sancionado), motivo: motivo.trim(), ...(partido.oficializado_at ? { autorizacion_id: Number(autorizacion) } : {}) }
    setConfirmar({ titulo: 'Confirmar resultado administrativo 3–0', texto: 'Se conservarán eventos y marcador deportivo. Laravel validará participantes, permisos, autorización y propagación antes de confirmar.', accion: () => onOperacion(() => administrativo.resultado(partido.id, datos), 'Resultado administrativo oficial registrado.') })
  }
  return <section className="panel" aria-label="Resolución administrativa"><h2>Resolución administrativa 3–0</h2>
    <p className="nota">Decisión del administrador propietario. No genera goles ni asistencias individuales. Un resultado oficial requiere una autorización específica y consumible.</p>
    <Aviso error={error} />
    <form onSubmit={revisar}><fieldset className="contents" disabled={bloqueado || !!confirmar}>
      <div className="dos-columnas">{[['Equipo beneficiado', beneficiado, setBeneficiado], ['Equipo sancionado', sancionado, setSancionado]].map(([texto, valor, cambiar]) => <label key={texto}>{texto}<select required value={valor} onChange={e => cambiar(e.target.value)}><option value="">Selecciona explícitamente un equipo</option>{participantes.map(p => <option key={p.id} value={p.campeonato_equipo_id}>{p.campeonato_equipo?.equipo?.nombre || `Inscripción #${p.campeonato_equipo_id}`}</option>)}</select></label>)}</div>
      <label>Motivo administrativo<textarea required maxLength={1000} value={motivo} onChange={e => setMotivo(e.target.value)} /></label>
      {partido.oficializado_at && <><label>Autorización de rectificación aprobada<select required value={autorizacion} onChange={e => setAutorizacion(e.target.value)}><option value="">Selecciona el expediente aprobado</option>{aprobadas.map(c => <option key={c.id} value={c.id}>#{c.id} · {c.motivo}</option>)}</select></label>
        <button type="button" className="boton boton-borde" disabled={!motivo.trim()} onClick={() => setConfirmar({ titulo: 'Solicitar rectificación administrativa', texto: motivo.trim(), accion: () => onOperacion(() => administrativo.solicitarRectificacion(partido.id, motivo.trim()), 'Solicitud creada. Revísala y autorízala en los expedientes antes de aplicarla.') })}>Solicitar rectificación de resultado</button></>}
      <button className="boton boton-verde" disabled={!motivo.trim() || !beneficiado || !sancionado || beneficiado === sancionado || (partido.oficializado_at && !autorizacion)}>Revisar decisión 3–0</button>
    </fieldset></form>
    {confirmar && <Confirmacion {...confirmar} onConfirmar={confirmar.accion} onCerrar={() => setConfirmar(null)} />}
  </section>
}

export function InactivarEquipo({ inscripcion, onGuardar }) {
  const [tipo, setTipo] = useState('')
  const [motivo, setMotivo] = useState('')
  const [confirmar, setConfirmar] = useState(false)
  return <><p className="nota">Conserva resultados e historial. Los partidos pendientes requieren decisiones 3–0 separadas; una doble inactividad bloquea el avance.</p>
    <form onSubmit={e => { e.preventDefault(); setConfirmar(true) }}><fieldset className="contents" disabled={confirmar}>
      <label>Tipo de decisión<select required value={tipo} onChange={e => setTipo(e.target.value)}><option value="">Selecciona una decisión</option><option value="retiro">Retiro voluntario</option><option value="descalificacion">Descalificación administrativa</option></select></label>
      <label>Motivo de la decisión<textarea required maxLength={1000} value={motivo} onChange={e => setMotivo(e.target.value)} /></label>
      <button className="boton boton-verde" disabled={!tipo || !motivo.trim()}>Revisar decisión</button>
    </fieldset></form>
    {confirmar && <Confirmacion titulo="Confirmar inactividad del equipo" texto={`${inscripcion.equipo.nombre}: ${tipo === 'retiro' ? 'retiro voluntario' : 'descalificación'}. ${motivo.trim()}`} onConfirmar={() => onGuardar({ tipo, motivo: motivo.trim() })} onCerrar={() => setConfirmar(false)} />}
  </>
}
