import { useEffect, useState } from 'react'
import ImagenEntidad from './ImagenEntidad'
import Aviso from './Aviso'
import { Boton, Campo, Tarjeta } from './ui'
import { mensajeError } from '../services/api'
import { nombreParticipante } from '../utils/partidos'
import { segundosDelReloj, tiempoVisible } from '../utils/relojArbitral'

const roles = ['local', 'visitante']

function EquipoMarcador({ partido, rol }) {
  const equipo = partido.participantes.find((p) => p.rol === rol)?.campeonato_equipo?.equipo
  const nombre = nombreParticipante(partido, rol)
  return (
    <div className="grid min-w-0 justify-items-center gap-2 text-center">
      <ImagenEntidad
        ruta={equipo?.escudo}
        nombre={nombre}
        decorativa
        className="size-10 sm:size-14"
      />
      <strong className="min-w-0 text-sm wrap-anywhere sm:text-lg">{nombre}</strong>
      <span className="text-xs capitalize text-green-100">{rol}</span>
    </div>
  )
}

export function RelojArbitral({ partido, marcador, panel, bloqueado, onReloj }) {
  const [ahora, setAhora] = useState(() => performance.now())
  useEffect(() => {
    if (!panel?.en_marcha) return
    const actualizar = () => setAhora(performance.now())
    const intervalo = window.setInterval(actualizar, 250)
    window.addEventListener('focus', actualizar)
    return () => {
      window.clearInterval(intervalo)
      window.removeEventListener('focus', actualizar)
    }
  }, [panel?.en_marcha])

  const duracion = panel?.duracion_minutos
  const segundos = duracion ? segundosDelReloj(panel, ahora) : 0
  const cumplida = duracion && segundos >= duracion * 60
  const marcha = panel?.en_marcha
  const [error, setError] = useState('')
  async function mover() {
    setError('')
    try {
      await onReloj(marcha ? 'pausar' : 'reanudar')
    } catch (e) {
      setError(mensajeError(e))
    }
  }

  return (
    <section
      className="my-4 grid gap-4 rounded-2xl bg-marca-oscuro px-3 py-5 text-white sm:px-7"
      aria-label="Marcador y cronómetro"
    >
      <div className="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 sm:gap-5">
        <EquipoMarcador partido={partido} rol="local" />
        <div className="text-center">
          <p className="font-titulo text-2xl font-bold tabular-nums sm:text-4xl">
            {marcador ? `${marcador.local.goles} – ${marcador.visitante.goles}` : '—'}
          </p>
          <p className="mt-2 text-xs capitalize text-green-100">
            {partido.estado.replaceAll('_', ' ')}
          </p>
        </div>
        <EquipoMarcador partido={partido} rol="visitante" />
      </div>
      <div className="grid justify-items-center gap-2 border-t border-white/25 pt-4 text-center">
        <span className="text-sm text-green-100">Cronómetro del encuentro</span>
        <time
          aria-label="Tiempo transcurrido"
          className="font-titulo text-4xl font-bold tabular-nums sm:text-5xl"
        >
          {duracion ? tiempoVisible(segundos) : '—'}
        </time>
        <p className="text-sm">
          {duracion ? `Duración: ${duracion} min` : 'Duración sin configurar'}
        </p>
        {cumplida ? (
          <p
            role="status"
            className="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-marca-oscuro"
          >
            Se alcanzó la duración configurada
          </p>
        ) : duracion && partido.estado === 'en_curso' && !marcha ? (
          <p className="text-sm text-green-100">Reloj pausado</p>
        ) : null}
        {duracion && partido.estado === 'en_curso' && !cumplida && (
          <Boton variante="claro" disabled={bloqueado} onClick={mover}>
            {marcha ? 'Pausar reloj' : 'Reanudar reloj'}
          </Boton>
        )}
        <Aviso error={error} />
      </div>
    </section>
  )
}

export function TitularesArbitrales({ partido, panel, bloqueado, onPreparar, onIniciar }) {
  const editable = partido.estado === 'programado'
  const [duracion, setDuracion] = useState(panel.duracion_minutos ?? '')
  const [titulares, setTitulares] = useState(() =>
    Object.fromEntries(
      roles.map((rol) => [
        rol,
        editable
          ? panel.titulares[rol].filter((id) => panel.elegibles[rol].some((j) => j.id === id))
          : panel.titulares[rol],
      ]),
    ),
  )
  const [error, setError] = useState('')
  const { minimo, maximo } = panel.limites
  const cambio =
    Number(duracion) !== panel.duracion_minutos ||
    roles.some((rol) => titulares[rol].join(',') !== panel.titulares[rol].join(','))
  const completos = roles.every(
    (rol) =>
      titulares[rol].length >= minimo &&
      titulares[rol].every((id) => panel.elegibles[rol].some((j) => j.id === id)),
  )
  function elegir(rol, id, marcado) {
    setTitulares((anterior) => ({
      ...anterior,
      [rol]: marcado ? [...anterior[rol], id] : anterior[rol].filter((valor) => valor !== id),
    }))
  }
  async function guardar(evento) {
    evento.preventDefault()
    setError('')
    try {
      await onPreparar({
        duracion_minutos: Number(duracion),
        titulares: Object.fromEntries(
          roles.map((rol) => [
            rol,
            titulares[rol].filter((id) => panel.elegibles[rol].some((j) => j.id === id)),
          ]),
        ),
      })
    } catch (e) {
      setError(mensajeError(e))
    }
  }

  if (!editable && !panel.duracion_minutos) return null
  return (
    <Tarjeta as="form" className="my-5 grid gap-4" onSubmit={guardar}>
      <div>
        <h2 className="font-titulo text-xl font-bold">Titulares</h2>
        {editable && (
          <p className="text-sm text-gris">
            Por equipo: mínimo {minimo}
            {maximo ? `, máximo ${maximo}` : ''}.
          </p>
        )}
      </div>
      {editable && (
        <div className="max-w-xs">
          <Campo
            etiqueta="Duración del encuentro (minutos)"
            type="number"
            required
            min="1"
            max="150"
            step="1"
            value={duracion}
            disabled={bloqueado}
            onChange={(e) => setDuracion(e.target.value)}
          />
        </div>
      )}
      <div className="grid min-w-0 gap-4 md:grid-cols-2">
        {roles.map((rol) => {
          const lista = panel.elegibles[rol].filter(
            (j) => editable || titulares[rol].includes(j.id),
          )
          return (
            <fieldset
              key={rol}
              className="min-w-0 rounded-xl border border-borde px-3 pb-3"
              disabled={bloqueado}
            >
              <legend className="px-2 font-semibold wrap-anywhere text-marca-oscuro">
                {nombreParticipante(partido, rol)}
              </legend>
              {!lista.length && (
                <p className="text-sm text-gris">
                  No hay {editable ? 'jugadores elegibles' : 'titulares registrados'}.
                </p>
              )}
              <ul className="grid gap-1">
                {lista.map(({ id, jugador, dorsal, posicion }) => {
                  const elegido = titulares[rol].includes(id)
                  const nombre = `${jugador.nombre} ${jugador.apellido || ''}`.trim()
                  return (
                    <li key={id}>
                      <label className="flex min-h-12 items-center gap-2 rounded-lg px-2 py-1 hover:bg-green-50">
                        {editable && (
                          <input
                            type="checkbox"
                            checked={elegido}
                            disabled={
                              !elegido && maximo !== null && titulares[rol].length >= maximo
                            }
                            onChange={(e) => elegir(rol, id, e.target.checked)}
                          />
                        )}
                        <ImagenEntidad
                          ruta={jugador.foto}
                          nombre={nombre}
                          decorativa
                          className="size-9"
                        />
                        <span className="min-w-0 wrap-anywhere">
                          <strong>{nombre}</strong>
                          <small className="block text-gris">
                            {dorsal != null ? `#${dorsal}` : ''}
                            {posicion ? ` · ${posicion}` : ''}
                          </small>
                        </span>
                      </label>
                    </li>
                  )
                })}
              </ul>
            </fieldset>
          )
        })}
      </div>
      <Aviso error={error} />
      {editable && (
        <>
          {!panel.campeonato_en_curso && (
            <p className="text-sm text-gris">
              El administrador debe iniciar el campeonato para habilitar el partido.
            </p>
          )}
          <div className="flex flex-wrap gap-2">
            <Boton type="submit" variante="secundario" disabled={bloqueado || !duracion || !cambio}>
              Guardar preparación
            </Boton>
            <Boton
              disabled={
                bloqueado ||
                cambio ||
                !completos ||
                !panel.duracion_minutos ||
                !panel.campeonato_en_curso
              }
              onClick={onIniciar}
            >
              Iniciar partido
            </Boton>
          </div>
        </>
      )}
    </Tarjeta>
  )
}
