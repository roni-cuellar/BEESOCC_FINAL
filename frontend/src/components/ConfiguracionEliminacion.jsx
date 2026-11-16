import { useState } from 'react'
import { Campo, Boton } from './ui'
import { datosCuadro, validarSiembra } from '../utils/eliminacion'

export default function ConfiguracionEliminacion({
  inscripciones,
  bloqueado,
  onCambiar,
  onPrevisualizar,
}) {
  const activos = inscripciones.filter((i) => i.estado === 'activo')
  const cantidad = Math.max(1, Math.ceil(Math.log2(Math.max(2, activos.length))))
  const [siembra, setSiembra] = useState(() => activos.map(() => ''))
  const [rondas, setRondas] = useState(() =>
    Array.from({ length: cantidad }, () => ({ modalidad: 'unico', desempate: 'penales' })),
  )
  function cambiarSiembra(indice, valor) {
    onCambiar()
    setSiembra((s) => s.map((id, i) => (i === indice ? valor : id)))
  }
  function cambiarRonda(indice, campo, valor) {
    onCambiar()
    setRondas((rs) => rs.map((r, i) => (i === indice ? { ...r, [campo]: valor } : r)))
  }
  return (
    <form
      className="panel"
      onSubmit={(e) => {
        e.preventDefault()
        if (validarSiembra(siembra, activos)) onPrevisualizar(datosCuadro(siembra, rondas))
      }}
    >
      <h2>Siembra y rondas</h2>
      <p className="nota">
        Selecciona explícitamente todos los equipos activos en orden de siembra. Laravel asignará
        los descansos y los cruces; los mejores puestos reciben descanso cuando corresponde.
      </p>
      {activos.length < 2 && (
        <p role="status">Inscribe al menos dos equipos activos para preparar el cuadro.</p>
      )}
      <fieldset disabled={bloqueado} className="contents">
        <div className="eliminacion-campos">
          {activos.map((inscripcion, indice) => (
            <Campo
              key={inscripcion.id}
              as="select"
              etiqueta={'Siembra ' + (indice + 1)}
              required
              value={siembra[indice]}
              onChange={(e) => cambiarSiembra(indice, e.target.value)}
            >
              <option value="">Selecciona un equipo</option>
              {activos.map((i) => (
                <option
                  key={i.id}
                  value={i.id}
                  disabled={siembra.some((id, p) => p !== indice && Number(id) === i.id)}
                >
                  {i.equipo.nombre}
                </option>
              ))}
            </Campo>
          ))}
        </div>
        <p className="nota">
          Configura las {cantidad} ronda(s) completas antes de confirmar. No se aplica gol
          visitante.
        </p>
        {rondas.map((ronda, indice) => (
          <div key={indice} className="eliminacion-ronda-form">
            <h3>{indice === cantidad - 1 ? 'Final' : 'Ronda ' + (indice + 1)}</h3>
            <div className="eliminacion-campos">
              <Campo
                as="select"
                etiqueta={'Modalidad de ronda ' + (indice + 1)}
                value={ronda.modalidad}
                onChange={(e) => cambiarRonda(indice, 'modalidad', e.target.value)}
              >
                <option value="unico">Partido único (ida)</option>
                <option value="ida_vuelta">Ida y vuelta</option>
              </Campo>
              <Campo
                as="select"
                etiqueta={'Desempate de ronda ' + (indice + 1)}
                value={ronda.desempate}
                onChange={(e) => cambiarRonda(indice, 'desempate', e.target.value)}
              >
                <option value="penales">Penales</option>
                <option value="prorroga_penales">Prórroga y penales</option>
              </Campo>
            </div>
          </div>
        ))}
        <Boton type="submit" disabled={!validarSiembra(siembra, activos) || bloqueado}>
          Previsualizar llaves
        </Boton>
      </fieldset>
    </form>
  )
}

export function PreviaEliminacion({ previa, inscripciones, onConfirmar, bloqueado }) {
  return (
    <section className="panel eliminacion-previa" aria-label="Vista previa de llaves">
      <p className="etiqueta">ANTES DE GENERAR</p>
      <h2>Revisa los cruces</h2>
      <p className="nota">
        Esta vista procede de Laravel. Todavía no se han generado partidos. Confirma solo si el
        orden y las rondas son correctos.
      </p>
      <ol className="eliminacion-cruces">
        {previa.cruces.map((cruce) => (
          <li key={cruce.numero}>
            <strong>Llave {cruce.numero}</strong>
            {cruce.inscripciones.map((id, i) => (
              <span key={i}>
                {id == null
                  ? 'Descanso'
                  : inscripciones.find((e) => e.id === id)?.equipo.nombre ||
                    'Equipo inscrito #' + id}
                <small>Siembra {cruce.siembras[i]}</small>
              </span>
            ))}
          </li>
        ))}
      </ol>
      <ul>
        {previa.rondas.map((r, i) => (
          <li key={i}>
            Ronda {i + 1}: {r.modalidad === 'unico' ? 'partido único' : 'ida y vuelta'} ·{' '}
            {r.desempate === 'penales' ? 'penales' : 'prórroga y penales'}
          </li>
        ))}
      </ul>
      <Boton disabled={bloqueado} onClick={onConfirmar}>
        Confirmar generación del cuadro
      </Boton>
    </section>
  )
}
