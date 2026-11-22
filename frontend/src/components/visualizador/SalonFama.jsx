import { useCallback, useRef } from 'react'
import { Link } from 'react-router-dom'
import useConsultaPublica from '../../hooks/useConsultaPublica'
import publicoService from '../../services/publicoService'
import { ultimosResultados } from '../../utils/espectador'
import EstadoVista from '../EstadoVista'
import PartidoCard from '../PartidoCard'
import { Boton, Tarjeta } from '../ui'

function mejoresPosiciones(tabla) {
  return tabla
    .filter((fila) => fila.PJ > 0 && fila.posicion >= 1 && fila.posicion <= 3)
    .sort((a, b) => a.posicion - b.posicion)
    .slice(0, 3)
}

function podiosGrupos(fases) {
  return fases
    .flatMap((fase) =>
      (fase.tablas_oficiales || []).map(({ grupo_id, tabla }) => ({
        id: grupo_id,
        nombre: fase.grupos.find((grupo) => grupo.id === grupo_id)?.nombre,
        equipos: mejoresPosiciones(tabla).map((fila) => ({
          ...fila,
          equipo_id: fase.equipos.find(
            (equipo) => equipo.campeonato_equipo_id === fila.campeonato_equipo_id,
          )?.inscripcion.equipo.id,
        })),
      })),
    )
    .filter(({ equipos }) => equipos.length)
}

function Puestos({ jugadores, nombres, unidad }) {
  const primero = jugadores[0]
  if (!primero) return <p className="text-sm text-gris">Todavía no hay registros.</p>
  const segundo = jugadores.find((jugador) => jugador.jugador_id !== primero.jugador_id)
  const unicos = segundo ? [primero, segundo] : [primero]
  return (
    <ol className="grid gap-3">
      {unicos.map((jugador, i) => (
        <li key={jugador.jugador_id} className="rounded-xl bg-green-50 p-3">
          <p className="text-xs font-semibold text-marca-oscuro">{nombres[i]}</p>
          <Link className="enlace-texto break-words" to={`jugadores/${jugador.jugador_id}`}>
            {jugador.nombre} {jugador.apellido}
          </Link>
          <p className="mt-1 text-sm text-gris">
            {jugador.total} {unidad}
          </p>
        </li>
      ))}
    </ol>
  )
}

export function SalonFamaContenido({ consultas, podios, partidos, consultaPartidos }) {
  const pista = useRef(null)
  const diapositiva = 'min-w-0 shrink-0 basis-[90%] snap-start sm:basis-[47%] xl:basis-[31%]'
  function desplazar(direccion) {
    const primerElemento = pista.current?.firstElementChild
    if (!primerElemento) return
    const espacio = parseFloat(getComputedStyle(pista.current).columnGap) || 0
    const distancia = primerElemento.getBoundingClientRect().width + espacio
    const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    pista.current.scrollBy({
      left: direccion * distancia,
      behavior: movimientoReducido ? 'instant' : 'smooth',
    })
  }

  return (
    <section aria-labelledby="salon-fama">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="salon-fama" className="font-titulo text-2xl font-bold">
          Salón de la fama
        </h2>
        <div className="flex gap-2">
          <Boton
            variante="secundario"
            aria-label="Anterior en el salón de la fama"
            onClick={() => desplazar(-1)}
            className="px-3"
          >
            ←
          </Boton>
          <Boton
            variante="secundario"
            aria-label="Siguiente en el salón de la fama"
            onClick={() => desplazar(1)}
            className="px-3"
          >
            →
          </Boton>
        </div>
      </div>
      <ul
        ref={pista}
        className="flex snap-x snap-mandatory items-start gap-4 overflow-x-auto overscroll-x-contain pb-4"
        tabIndex={0}
        aria-label="Carrusel de destacados"
      >
        {[
          ['Goleadores', consultas.goles, ['Máximo goleador', 'Segundo goleador'], 'goles'],
          [
            'Asistencias',
            consultas.asistencias,
            ['Máximo asistidor', 'Segundo asistidor'],
            'asistencias',
          ],
        ].map(([titulo, consulta, nombres, unidad]) => (
          <li key={titulo} className={diapositiva}>
            <Tarjeta as="article" className="border-t-4 border-t-marca">
              <h3 className="mb-3 font-titulo text-lg font-bold">{titulo}</h3>
              <EstadoVista {...consulta} />
              {consulta.datos && (
                <Puestos jugadores={consulta.datos.resultados} nombres={nombres} unidad={unidad} />
              )}
            </Tarjeta>
          </li>
        ))}
        <li className={diapositiva}>
          <Tarjeta as="article" className="border-t-4 border-t-marca">
            <h3 className="mb-3 font-titulo text-lg font-bold">Primeros en clasificación</h3>
            <EstadoVista {...consultas.tabla} />
            {!consultas.tabla.cargando &&
              !consultas.tabla.error &&
              (podios.length ? (
                podios.map(({ id, nombre, equipos }) => (
                  <div key={id} className="mb-3">
                    {nombre && <h4 className="mb-2 font-semibold">{nombre}</h4>}
                    <ol className="grid gap-2">
                      {equipos.map((equipo) => (
                        <li
                          key={equipo.equipo_id ?? equipo.campeonato_equipo_id}
                          className="rounded-lg bg-green-50 p-2 text-sm break-words"
                        >
                          <strong>{equipo.posicion}. </strong>
                          {equipo.equipo_id ? (
                            <Link className="enlace-texto" to={`equipos/${equipo.equipo_id}`}>
                              {equipo.nombre}
                            </Link>
                          ) : (
                            equipo.nombre
                          )}
                          <span className="block text-xs text-gris">{equipo.PTS} puntos</span>
                        </li>
                      ))}
                    </ol>
                  </div>
                ))
              ) : (
                <p className="text-sm text-gris">
                  Aún no hay posiciones aplicables con resultados.
                </p>
              ))}
          </Tarjeta>
        </li>
        <li className={diapositiva}>
          <Tarjeta as="article" className="border-t-4 border-t-marca">
            <h3 className="mb-3 font-titulo text-lg font-bold">Últimos resultados</h3>
            <EstadoVista {...consultaPartidos} />
            {partidos &&
              (partidos.length ? (
                <div className="grid gap-3">
                  {partidos.map((partido) => (
                    <PartidoCard key={partido.id} partido={partido} publica />
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gris">Todavía no hay partidos finalizados.</p>
              ))}
          </Tarjeta>
        </li>
      </ul>
    </section>
  )
}

export default function SalonFama({ liga, deporte, consultaPartidos }) {
  const consultarGoles = useCallback(
    () => publicoService.estadisticas(liga.id, 'goleadores'),
    [liga.id],
  )
  const consultarAsistencias = useCallback(
    () => publicoService.estadisticas(liga.id, 'asistencias'),
    [liga.id],
  )
  const general = liga.formato === 'todos_contra_todos'
  const consultarTabla = useCallback(
    () => (general ? publicoService.tabla(liga.id) : Promise.resolve(null)),
    [general, liga.id],
  )
  const goles = useConsultaPublica(consultarGoles)
  const asistencias = useConsultaPublica(consultarAsistencias)
  const tabla = useConsultaPublica(consultarTabla)
  const equipos = general ? mejoresPosiciones(tabla.datos?.tabla || []) : []
  const podios = general
    ? equipos.length
      ? [{ id: 'general', equipos }]
      : []
    : podiosGrupos(deporte.fases)

  return (
    <SalonFamaContenido
      consultas={{ goles, asistencias, tabla }}
      podios={podios}
      partidos={
        consultaPartidos.datos ? ultimosResultados(consultaPartidos.datos.partidos) : undefined
      }
      consultaPartidos={consultaPartidos}
    />
  )
}
