import { useState } from 'react'
import { Boton, Campo } from './ui'
import { nombrePlaza } from '../utils/eliminacion'

export default function PenalesForm({ fase, llave, ronda, onConfirmar, bloqueado }) {
  const [datos, setDatos] = useState({ penales_plaza_1: '', penales_plaza_2: '', motivo: '' })
  const validos = ['penales_plaza_1', 'penales_plaza_2'].every(c => datos[c] !== '' && Number.isInteger(Number(datos[c])) && Number(datos[c]) >= 0 && Number(datos[c]) <= 255)
    && Number(datos.penales_plaza_1) !== Number(datos.penales_plaza_2) && Boolean(datos.motivo.trim())
  return <form className="panel" onSubmit={e => { e.preventDefault(); if (validos) onConfirmar({ penales_plaza_1: Number(datos.penales_plaza_1), penales_plaza_2: Number(datos.penales_plaza_2), motivo: datos.motivo.trim() }) }}>
    <h2>Certificar tanda · {ronda.nombre} · llave {llave.numero}</h2>
    <p className="nota">Método: {ronda.desempate === 'penales' ? 'penales' : 'prórroga y penales'}. Certifica que se cumplió el método configurado. Laravel verificará el empate oficial. La tanda no suma goles ni estadísticas y no se edita después.</p>
    <fieldset disabled={bloqueado} className="contents">
      <div className="eliminacion-campos">{[1, 2].map(posicion => <Campo key={posicion} etiqueta={'Penales · ' + nombrePlaza(fase, llave.plazas.find(p => p.posicion === posicion))} type="number" min="0" max="255" step="1" required value={datos['penales_plaza_' + posicion]} onChange={e => setDatos({ ...datos, ['penales_plaza_' + posicion]: e.target.value })} />)}</div>
      <Campo as="textarea" etiqueta="Motivo y certificación del árbitro" required maxLength={1000} value={datos.motivo} onChange={e => setDatos({ ...datos, motivo: e.target.value })} />
      <Boton type="submit" disabled={!validos || bloqueado}>Revisar certificación de penales</Boton>
    </fieldset>
  </form>
}
