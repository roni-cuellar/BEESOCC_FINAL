import { useState } from 'react'
import { Boton, Campo } from '../ui'
import { datosDistribucion, nombreInscripcion } from '../../utils/grupos'

export default function ConfiguracionGrupos({ inscripciones, bloqueado, onCambiar, onPrevisualizar }) {
  const activos = inscripciones.filter(i => i.estado === 'activo')
  const [metodo, setMetodo] = useState('automatico')
  const [cantidad, setCantidad] = useState('2')
  const [cupo, setCupo] = useState('1')
  const [asignaciones, setAsignaciones] = useState({})
  const cambiar = accion => { onCambiar(); accion() }
  const datos = datosDistribucion(metodo, cantidad, cupo, asignaciones, activos)
  return <form className="panel" onSubmit={e => { e.preventDefault(); if (datos) onPrevisualizar(datos) }}>
    <p className="etiqueta">PREPARA LA COMPETICIÓN</p><h2>Distribución de grupos</h2>
    <p className="nota">Elige un cupo uniforme de clasificados. Revisa la propuesta antes de confirmar: la distribución quedará fijada.</p>
    <fieldset disabled={bloqueado} className="contents"><div className="grupos-campos">
      <Campo as="select" etiqueta="Distribución" value={metodo} onChange={e => cambiar(() => setMetodo(e.target.value))}><option value="automatico">Sorteo automático</option><option value="manual">Asignación manual</option></Campo>
      <Campo etiqueta="Cantidad de grupos" type="number" min="1" max={activos.length || 1} step="1" required value={cantidad} onChange={e => cambiar(() => { setCantidad(e.target.value); setAsignaciones({}) })} />
      <Campo etiqueta="Clasificados por grupo" type="number" min="1" max="255" step="1" required value={cupo} onChange={e => cambiar(() => setCupo(e.target.value))} />
    </div>
      {metodo === 'manual' && <div className="grupos-asignaciones">{activos.map(i => <Campo key={i.id} as="select" etiqueta={i.equipo.nombre} value={asignaciones[i.id] || ''} required onChange={e => cambiar(() => setAsignaciones(a => ({ ...a, [i.id]: e.target.value })))}><option value="">Selecciona su grupo</option>{Array.from({ length: Math.min(activos.length, Math.max(0, Number(cantidad) || 0)) }, (_, g) => <option key={g} value={g + 1}>Grupo {g + 1}</option>)}</Campo>)}</div>}
      {activos.length < 2 && <p className="nota">Inscribe al menos dos equipos activos para continuar.</p>}
      <Boton type="submit" disabled={bloqueado || !datos}>Previsualizar distribución</Boton>
    </fieldset>
  </form>
}

export function PreviaDistribucion({ vista, inscripciones, bloqueado, onConfirmar }) {
  return <section className="panel grupos-previa" aria-label="Previsualización de distribución"><p className="etiqueta">REVISA ANTES DE CONFIRMAR</p><h2>Así quedarán los grupos</h2><p className="nota">{vista.metodo === 'manual' ? 'Asignación manual' : 'Sorteo automático'} · {vista.clasificados_por_grupo} clasificado(s) por grupo. Confirmar conserva exactamente esta propuesta.</p>
    <div className="grupos-rejilla">{vista.grupos.map((ids, i) => <article key={i}><h3>Grupo {i + 1}</h3><ul>{ids.map(id => <li key={id}>{nombreInscripcion(null, inscripciones, id)}</li>)}</ul></article>)}</div>
    <Boton disabled={bloqueado} onClick={onConfirmar}>Confirmar distribución</Boton>
  </section>
}
