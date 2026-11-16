import { useState } from 'react'
import { Boton, Campo } from '../ui'
import { nombreOrigen } from '../../utils/grupos'

export default function CrucesGrupos({
  fase,
  clasificacion,
  inscripciones,
  previa,
  bloqueado,
  onCambiar,
  onPrevisualizar,
  onConfirmar,
}) {
  const [metodo, setMetodo] = useState('automatico')
  const [rondas, setRondas] = useState(() =>
    Array.from({ length: Math.ceil(Math.log2(clasificacion.candidatos.length)) }, () => ({
      modalidad: 'unico',
      desempate: 'penales',
    })),
  )
  const [borrador, setBorrador] = useState(null)
  const editable = previa || borrador
  const nombre = (origen) => nombreOrigen(origen, fase, clasificacion, inscripciones)
  function cambiarRonda(i, campo, valor) {
    if (editable) setBorrador(structuredClone(editable))
    onCambiar()
    setRondas((rs) => rs.map((r, n) => (n === i ? { ...r, [campo]: valor } : r)))
  }
  function cambiarCruce(i, p, valor) {
    onCambiar()
    setBorrador({
      ...editable,
      cruces: editable.cruces.map((par, n) =>
        n === i ? par.map((o, j) => (j === p ? valor || null : o)) : par,
      ),
    })
  }
  function previsualizar(e) {
    e.preventDefault()
    onPrevisualizar({
      metodo,
      rondas,
      ...(metodo === 'manual' && editable
        ? {
            sorteo: editable.sorteo,
            confirmacion_sorteo: editable.confirmacion_sorteo,
            cruces: editable.cruces,
          }
        : {}),
    })
  }
  return (
    <section className="panel">
      <p className="etiqueta">SOLO CLASIFICADOS OFICIALES</p>
      <h2>Prepara los cruces</h2>
      <p className="nota">
        Los mejores puestos tienen prioridad para los descansos. Entre puestos equivalentes se
        conserva el sorteo mostrado; no se comparan puntos de grupos diferentes.
      </p>
      <form onSubmit={previsualizar}>
        <fieldset disabled={bloqueado} className="contents">
          <Campo
            as="select"
            etiqueta="Método de cruces"
            value={metodo}
            onChange={(e) => {
              onCambiar()
              setMetodo(e.target.value)
              setBorrador(null)
            }}
          >
            <option value="automatico">Cruces automáticos</option>
            <option value="manual">Cruces manuales</option>
          </Campo>
          {rondas.map((r, i) => (
            <div className="eliminacion-ronda-form" key={i}>
              <h3>Ronda {i + 1}</h3>
              <div className="grupos-campos">
                <Campo
                  as="select"
                  etiqueta={`Modalidad de ronda ${i + 1}`}
                  value={r.modalidad}
                  onChange={(e) => cambiarRonda(i, 'modalidad', e.target.value)}
                >
                  <option value="unico">Partido único</option>
                  <option value="ida_vuelta">Ida y vuelta</option>
                </Campo>
                <Campo
                  as="select"
                  etiqueta={`Desempate de ronda ${i + 1}`}
                  value={r.desempate}
                  onChange={(e) => cambiarRonda(i, 'desempate', e.target.value)}
                >
                  <option value="penales">Penales</option>
                  <option value="prorroga_penales">Prórroga y penales</option>
                </Campo>
              </div>
            </div>
          ))}
          {metodo === 'manual' && editable && (
            <>
              <h3>Ajustar plazas de primera ronda</h3>
              <p className="nota">
                Revisa de nuevo la propuesta después de editar. Se validarán repeticiones,
                procedencias, descansos y cruces del mismo grupo.
              </p>
              {editable.cruces.map((par, i) => (
                <div className="grupos-campos" key={i}>
                  {par.map((origen, p) => (
                    <Campo
                      as="select"
                      key={p}
                      etiqueta={`Llave ${i + 1}, plaza ${p + 1}`}
                      value={origen ?? ''}
                      onChange={(e) => cambiarCruce(i, p, e.target.value)}
                    >
                      <option value="">Descanso</option>
                      {editable.sorteo.map((o) => (
                        <option key={o} value={o}>
                          {nombre(o)}
                        </option>
                      ))}
                    </Campo>
                  ))}
                </div>
              ))}
            </>
          )}
          <Boton type="submit" disabled={bloqueado}>
            {borrador && !previa ? 'Revisar cambios en los cruces' : 'Previsualizar cruces'}
          </Boton>
        </fieldset>
      </form>
      {previa && (
        <section
          className="grupos-previa grupos-previa-cruces"
          aria-label="Previsualización de eliminación"
        >
          <h3>Revisa el cuadro completo</h3>
          <ol className="eliminacion-cruces">
            {previa.cruces.map((par, i) => (
              <li key={i}>
                <strong>Llave {i + 1}</strong>
                {par.map((o, p) => (
                  <span key={p}>{nombre(o)}</span>
                ))}
              </li>
            ))}
          </ol>
          <details>
            <summary>Ver sorteo y prioridad de descansos</summary>
            <ol>
              {previa.sorteo.map((o) => (
                <li key={o}>{nombre(o)}</li>
              ))}
            </ol>
          </details>
          <p className="nota">
            Descansos:{' '}
            {previa.descansos.length ? previa.descansos.map(nombre).join(' · ') : 'Ninguno'}
          </p>
          <p className="nota">
            Cruces inevitables del mismo grupo: {previa.cruces_mismo_grupo_inevitables}.
          </p>
          <ul>
            {previa.rondas.map((r, i) => (
              <li key={i}>
                Ronda {i + 1}: {r.modalidad === 'unico' ? 'partido único' : 'ida y vuelta'} ·{' '}
                {r.desempate === 'penales' ? 'penales' : 'prórroga y penales'}
              </li>
            ))}
          </ul>
          <Boton disabled={bloqueado} onClick={onConfirmar}>
            Confirmar sorteo y generar eliminación
          </Boton>
        </section>
      )}
    </section>
  )
}
