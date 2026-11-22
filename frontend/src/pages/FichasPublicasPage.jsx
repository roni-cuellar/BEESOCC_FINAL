import { useCallback } from 'react'
import { useOutletContext, useParams } from 'react-router-dom'
import EstadoVista from '../components/EstadoVista'
import ImagenEntidad from '../components/ImagenEntidad'
import TablaDeportiva, { IdentidadDeportiva } from '../components/deportivo/TablaDeportiva'
import { Boton, Tarjeta } from '../components/ui'
import useConsultaPublica from '../hooks/useConsultaPublica'
import publicoService from '../services/publicoService'
import ligaService from '../services/ligaService'
import equipoService from '../services/equipoService'
import jugadorService from '../services/jugadorService'
import faseService from '../services/faseService'
import grupoService from '../services/grupoService'

const estadisticas = [
  ['goles', 'Goles'],
  ['asistencias', 'Asistencias'],
  ['amarillas', 'Tarjetas amarillas'],
  ['rojas', 'Tarjetas rojas'],
  ['faltas', 'Faltas'],
]

function CabeceraFicha({ nombre, imagen, descripcion, volver, textoVolver }) {
  return (
    <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-marca-oscuro p-4 text-white sm:p-6">
      <div className="flex w-full min-w-0 items-center gap-4 sm:w-auto sm:flex-1">
        <ImagenEntidad
          ruta={imagen}
          nombre={nombre}
          className="size-16 shrink-0 bg-white sm:size-24"
        />
        <div className="min-w-0">
          <h2 className="font-titulo text-2xl font-bold break-words sm:text-3xl">{nombre}</h2>
          {descripcion && <p className="mt-1 break-words text-green-100">{descripcion}</p>}
        </div>
      </div>
      <Boton to={volver} variante="claro">
        {textoVolver}
      </Boton>
    </header>
  )
}

function Indicadores({ datos }) {
  return (
    <dl className="grid grid-cols-2 gap-3 md:grid-cols-3">
      {datos.map(([etiqueta, valor]) => (
        <div key={etiqueta} className="min-w-0 rounded-xl border border-borde bg-[#f5f7f6] p-3">
          <dt className="text-sm break-words text-gris">{etiqueta}</dt>
          <dd className="mt-1 font-titulo text-2xl font-bold break-words text-marca-oscuro">
            {valor}
          </dd>
        </div>
      ))}
    </dl>
  )
}

export function FichaEquipo({ equipo, plantilla, estado, consultaRendimiento, publica = true }) {
  const resumen = consultaRendimiento?.datos
  return (
    <>
      <CabeceraFicha
        nombre={equipo.nombre}
        imagen={equipo.escudo}
        descripcion={[
          equipo.ciudad,
          estado === 'activo' ? 'Inscripción activa' : 'Inscripción inactiva',
        ]
          .filter(Boolean)
          .join(' · ')}
        volver={publica ? '..' : '../equipos'}
        textoVolver={publica ? 'Volver a Inicio' : 'Volver a Equipos'}
      />
      {consultaRendimiento && (
        <Tarjeta as="section" className="mb-6">
          <h3 className="mb-4 font-titulo text-xl font-bold">Rendimiento en el campeonato</h3>
          <EstadoVista {...consultaRendimiento} />
          {resumen?.posiciones.map(({ grupo, fila }) => (
            <p className="mb-3 font-semibold text-marca-oscuro" key={grupo || 'general'}>
              {grupo ? `${grupo} · ` : ''}Posición {fila.posicion ?? 'pendiente'} · {fila.PTS}{' '}
              puntos · {fila.PJ} PJ
            </p>
          ))}
          {resumen?.errorPosicion && (
            <p className="nota">La clasificación no está disponible en este momento.</p>
          )}
          {resumen?.errorEstadistica && (
            <p className="nota">
              Las estadísticas del equipo no están disponibles en este momento.
            </p>
          )}
          {resumen?.totales && (
            <Indicadores
              datos={estadisticas
                .filter(([campo]) => campo in resumen.totales)
                .map(([campo, etiqueta]) => [etiqueta, resumen.totales[campo]])}
            />
          )}
          {resumen && !resumen.totales && !resumen.posiciones.length && (
            <p className="nota">Aún no hay datos de rendimiento para este equipo.</p>
          )}
        </Tarjeta>
      )}
      <section aria-labelledby="plantilla-publica">
        <h3 id="plantilla-publica" className="mb-4 font-titulo text-xl font-bold">
          {publica ? 'Plantilla pública' : 'Plantilla del campeonato'}
        </h3>
        <EstadoVista
          vacio={!plantilla.length}
          titulo="Sin jugadores inscritos"
          texto="La plantilla se mostrará cuando el equipo tenga jugadores inscritos."
        />
        {plantilla.length > 0 && (
          <TablaDeportiva titulo="Plantilla del equipo">
            <thead>
              <tr>
                <th scope="col">Dorsal</th>
                <th scope="col">Jugador</th>
                <th scope="col">Posición</th>
              </tr>
            </thead>
            <tbody>
              {plantilla.map(({ jugador, dorsal, posicion }) => (
                <tr key={jugador.id}>
                  <td aria-label={`Dorsal ${dorsal ?? 'por definir'}`}>{dorsal ?? '—'}</td>
                  <th scope="row">
                    <IdentidadDeportiva
                      nombre={`${jugador.nombre} ${jugador.apellido || ''}`.trim()}
                      imagen={jugador.foto}
                      to={`../jugadores/${jugador.id}`}
                      primaria
                    />
                  </th>
                  <td>{posicion || 'Posición sin registrar'}</td>
                </tr>
              ))}
            </tbody>
          </TablaDeportiva>
        )}
      </section>
    </>
  )
}

async function consultarPosiciones(liga, deporte, publica, equipo, inscripcionId) {
  if (liga.formato === 'todos_contra_todos') {
    const { tabla } = await (publica ? publicoService : ligaService).tabla(liga.id)
    return tabla
      .filter((fila) => String(fila.equipo_id) === String(equipo.id))
      .map((fila) => ({ fila }))
  }
  if (liga.formato !== 'grupos_eliminacion') return []

  const fases = publica
    ? deporte.fases
    : await Promise.all(
        (await faseService.listar(liga.id))
          .filter((fase) => fase.tipo === 'grupos')
          .map(async ({ id }) => {
            const [fase, { tablas }] = await Promise.all([
              faseService.obtener(id),
              grupoService.clasificacion(id),
            ])
            return { ...fase, tablas_oficiales: tablas }
          }),
      )
  return fases.flatMap((fase) =>
    (fase.tablas_oficiales || []).flatMap((tabla) => {
      const fila = tabla.tabla.find((f) => String(f.campeonato_equipo_id) === String(inscripcionId))
      const grupo = fase.grupos.find((g) => g.id === tabla.grupo_id)
      return fila ? [{ fila, grupo: grupo?.nombre || grupo?.codigo }] : []
    }),
  )
}

function EquipoConRendimiento({ inscripcion, liga, deporte, publica }) {
  const { equipo, inscripcion_id } = inscripcion
  const consultar = useCallback(async () => {
    const [estadistica, posiciones] = await Promise.allSettled([
      (publica ? publicoService : ligaService).estadisticas(liga.id, 'equipos'),
      consultarPosiciones(liga, deporte, publica, equipo, inscripcion_id),
    ])
    if (estadistica.status === 'rejected' && posiciones.status === 'rejected')
      throw estadistica.reason
    return {
      totales:
        estadistica.status === 'fulfilled'
          ? estadistica.value.resultados.find(
              (fila) => String(fila.equipo_id) === String(equipo.id),
            )
          : null,
      posiciones: posiciones.status === 'fulfilled' ? posiciones.value : [],
      errorPosicion: posiciones.status === 'rejected',
      errorEstadistica: estadistica.status === 'rejected',
    }
  }, [liga, deporte, publica, equipo, inscripcion_id])
  const consulta = useConsultaPublica(consultar)
  return <FichaEquipo {...inscripcion} publica={publica} consultaRendimiento={consulta} />
}

export function EquipoPublicoPage({ publica = true }) {
  const { ligaId, equipoId } = useParams()
  const { liga, deporte } = useOutletContext()
  const consultar = useCallback(async () => {
    const equipos = await (publica
      ? publicoService.equipos(ligaId)
      : equipoService.inscripciones(ligaId))
    const inscripcion = equipos.find(({ equipo }) => String(equipo?.id) === equipoId)
    if (!inscripcion) return null
    const inscripcion_id = inscripcion.inscripcion_id ?? inscripcion.id
    const plantilla = await (publica
      ? publicoService.plantilla(ligaId, inscripcion_id)
      : jugadorService.plantillas(inscripcion_id))
    return {
      inscripcion_id,
      equipo: inscripcion.equipo,
      estado: inscripcion.estado,
      plantilla: publica ? plantilla : plantilla.filter((fila) => !fila.fecha_salida),
    }
  }, [ligaId, equipoId, publica])
  const consulta = useConsultaPublica(consultar)
  return (
    <>
      <EstadoVista
        {...consulta}
        vacio={consulta.datos === null}
        titulo="Equipo no disponible"
        texto="El equipo no está inscrito en este campeonato."
      />
      {consulta.datos && (
        <EquipoConRendimiento
          inscripcion={consulta.datos}
          liga={liga}
          deporte={deporte}
          publica={publica}
        />
      )}
    </>
  )
}

export function FichaJugador({ jugador, equipos, totales }) {
  const nombre = `${jugador.nombre} ${jugador.apellido || ''}`.trim()
  return (
    <>
      <CabeceraFicha
        nombre={nombre}
        imagen={jugador.foto}
        volver="../estadisticas"
        textoVolver="Ir a Estadísticas"
      />
      <div className="grid gap-4 xl:grid-cols-2">
        <Tarjeta as="section">
          <h3 className="mb-4 font-titulo text-xl font-bold">Inscripciones en el campeonato</h3>
          <ul className="grid gap-3">
            {equipos.map(({ equipo, dorsal, posicion, vigente }, i) => (
              <li key={`${equipo.id}:${i}`} className="rounded-xl border border-borde p-3">
                <IdentidadDeportiva
                  nombre={equipo.nombre}
                  imagen={equipo.escudo}
                  to={`../equipos/${equipo.id}`}
                />
                <p className="mt-1 text-sm text-gris">
                  Dorsal {dorsal ?? 'sin registrar'} · {posicion || 'Posición sin registrar'} ·{' '}
                  {vigente ? 'Vigente' : 'Histórica'}
                </p>
              </li>
            ))}
          </ul>
        </Tarjeta>
        <Tarjeta as="section">
          <h3 className="mb-4 font-titulo text-xl font-bold">Estadísticas del campeonato</h3>
          <Indicadores
            datos={estadisticas.map(([campo, etiqueta]) => [etiqueta, totales[campo] ?? '—'])}
          />
        </Tarjeta>
      </div>
    </>
  )
}

export function JugadorPublicoPage({ publica = true }) {
  const { ligaId, jugadorId } = useParams()
  const consultar = useCallback(
    () =>
      publica
        ? publicoService.jugador(ligaId, jugadorId)
        : ligaService.fichaJugador(ligaId, jugadorId),
    [ligaId, jugadorId, publica],
  )
  const consulta = useConsultaPublica(consultar)
  return (
    <>
      <EstadoVista
        {...consulta}
        error={
          consulta.estadoError === 404
            ? 'La ficha del jugador no está disponible en este campeonato.'
            : consulta.error
        }
      />
      {consulta.datos && (
        <FichaJugador
          jugador={consulta.datos.jugador}
          equipos={consulta.datos.equipos}
          totales={consulta.datos.estadisticas}
        />
      )}
    </>
  )
}
