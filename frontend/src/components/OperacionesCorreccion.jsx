import { useState } from 'react'
import EventoForm from './EventoForm'
import { descripcionOperacion } from '../utils/correcciones'

export default function OperacionesCorreccion({
  partido,
  eventos,
  plantillas,
  bloqueado,
  onAplicar,
}) {
  const [operaciones, setOperaciones] = useState([])
  const [accion, setAccion] = useState('corregir_minuto')
  const [eventoId, setEventoId] = useState('')
  const [minuto, setMinuto] = useState(0)
  const limite = operaciones.length >= 100
  function agregar(operacion) {
    if (bloqueado || limite) return
    setOperaciones((actual) => [...actual, operacion])
  }
  function agregarCambio(e) {
    e.preventDefault()
    if (!eventos.some((evento) => String(evento.id) === eventoId)) return
    agregar({
      accion,
      evento_id: Number(eventoId),
      ...(accion === 'corregir_minuto' ? { minuto: Number(minuto) } : {}),
    })
    setEventoId('')
  }
  const plantillasListas = partido.participantes.every((p) =>
    Boolean(plantillas[p.campeonato_equipo_id]),
  )
  return (
    <div className="operaciones-correccion">
      <p className="nota">
        Prepara todas las operaciones antes de aplicar. La aprobación se consume una sola vez y
        Laravel confirma el lote completo. Para eliminar un gol, agrega primero la eliminación de su
        asistencia.
      </p>
      <label>
        Operación
        <select disabled={bloqueado} value={accion} onChange={(e) => setAccion(e.target.value)}>
          <option value="corregir_minuto">Corregir minuto</option>
          <option value="eliminar">Eliminar evento</option>
          <option value="registrar">Registrar evento</option>
        </select>
      </label>
      {accion === 'registrar' ? (
        plantillasListas ? (
          <EventoForm
            partido={partido}
            eventos={eventos}
            plantillas={plantillas}
            bloqueado={bloqueado || limite}
            titulo="Preparar evento para la corrección"
            textoBoton="Agregar al lote"
            onGuardar={async (datos) => agregar({ accion: 'registrar', datos })}
          />
        ) : (
          <p role="alert">
            No se pudieron cargar las plantillas. Actualiza el acta antes de registrar eventos.
          </p>
        )
      ) : (
        <form onSubmit={agregarCambio}>
          <fieldset className="contents" disabled={bloqueado || limite}>
            <label>
              Evento del partido
              <select
                required
                value={eventoId}
                onChange={(e) => {
                  setEventoId(e.target.value)
                  setMinuto(
                    eventos.find((evento) => String(evento.id) === e.target.value)?.minuto ?? 0,
                  )
                }}
              >
                <option value="">Selecciona un evento</option>
                {eventos.map((evento) => (
                  <option key={evento.id} value={evento.id}>
                    #{evento.id} · {evento.tipo} · {evento.plantilla.jugador.nombre}{' '}
                    {evento.plantilla.jugador.apellido} · {evento.minuto}′
                  </option>
                ))}
              </select>
            </label>
            {accion === 'corregir_minuto' && (
              <label>
                Minuto corregido
                <input
                  type="number"
                  required
                  min="0"
                  max="150"
                  step="1"
                  value={minuto}
                  onChange={(e) => setMinuto(e.target.value)}
                />
              </label>
            )}
            <button className="boton boton-borde">Agregar al lote</button>
          </fieldset>
        </form>
      )}
      <h4>Operaciones preparadas ({operaciones.length}/100)</h4>
      {!operaciones.length && (
        <p className="nota">Todavía no hay operaciones. Agregarlas no modifica el acta.</p>
      )}
      <ol className="lote-correccion">
        {operaciones.map((operacion, indice) => (
          <li key={indice}>
            <span>
              {indice + 1}. {descripcionOperacion(operacion)}
            </span>
            <button
              className="boton-texto peligro"
              disabled={bloqueado}
              onClick={() => setOperaciones((actual) => actual.filter((_, i) => i !== indice))}
            >
              Quitar operación {indice + 1}
            </button>
          </li>
        ))}
      </ol>
      <button
        className="boton boton-verde"
        disabled={bloqueado || !operaciones.length}
        onClick={() => onAplicar(operaciones)}
      >
        Revisar y aplicar corrección
      </button>
    </div>
  )
}
