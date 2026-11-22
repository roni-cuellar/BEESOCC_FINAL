import { useCallback, useEffect } from 'react'
import { Link, Outlet, useLocation, useOutletContext, useParams } from 'react-router-dom'
import publicoService from '../services/publicoService'
import useConsultaPublica from '../hooks/useConsultaPublica'
import EstadoVista from '../components/EstadoVista'
import IlustracionModulo from '../components/IlustracionModulo'
import LogoCampeonato from '../components/LogoCampeonato'
import ImagenEntidad from '../components/ImagenEntidad'
import PartidoCard from '../components/PartidoCard'
import MenuEspectador from '../components/visualizador/MenuEspectador'
import SalonFama from '../components/visualizador/SalonFama'
import { Boton, Tarjeta } from '../components/ui'
import { campeonEnCuadro } from '../utils/eliminacion'

const formatos = {
  todos_contra_todos: 'Todos contra todos',
  eliminacion_directa: 'Eliminación directa',
  grupos_eliminacion: 'Grupos + eliminación',
}

const estados = {
  planificacion: 'Planificación',
  en_curso: 'En curso',
  finalizado: 'Finalizado',
}

export function ResumenPublico({ equipos, partidos }) {
  return (
    <>
      <section className="mb-8" aria-labelledby="equipos-participantes">
        <h2 id="equipos-participantes" className="mb-4 font-titulo text-2xl font-bold">
          Equipos participantes
        </h2>
        <EstadoVista
          vacio={!equipos.length}
          titulo="Aún no hay equipos inscritos"
          texto="Verás aquí los equipos y sus plantillas cuando estén disponibles."
        />
        <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {equipos.map(({ inscripcion_id, equipo }) => (
            <li key={inscripcion_id}>
              <Link
                className="flex h-full min-h-20 items-center gap-3 rounded-xl border border-borde bg-white p-3 text-marca-oscuro hover:border-marca hover:bg-green-50"
                to={`equipos/${equipo.id}`}
              >
                <ImagenEntidad ruta={equipo.escudo} nombre={equipo.nombre} />
                <strong className="min-w-0 break-words">{equipo.nombre}</strong>
              </Link>
            </li>
          ))}
        </ul>
      </section>
      <section className="mb-8" aria-labelledby="calendario-publico">
        <h2 id="calendario-publico" className="mb-4 font-titulo text-2xl font-bold">
          Calendario y resultados
        </h2>
        <EstadoVista
          vacio={!partidos.length}
          titulo="No hay partidos programados"
          texto="Aquí se mostrarán las jornadas y sus resultados."
        />
        <div className="grid gap-4 xl:grid-cols-2">
          {partidos.map((partido) => (
            <PartidoCard key={partido.id} partido={partido} publica />
          ))}
        </div>
      </section>
    </>
  )
}

export function ResumenPublicoPage() {
  const { liga, deporte } = useOutletContext()
  const consultar = useCallback(async () => {
    const [equipos, partidos] = await Promise.all([
      publicoService.equipos(liga.id),
      publicoService.partidos(liga.id),
    ])
    return { equipos, partidos }
  }, [liga.id])
  const consulta = useConsultaPublica(consultar)

  return (
    <>
      <EstadoVista {...consulta} />
      {consulta.datos && <ResumenPublico {...consulta.datos} />}
      <SalonFama liga={liga} deporte={deporte} consultaPartidos={consulta} />
    </>
  )
}

export function CampeonPublico({ liga, campeon }) {
  if (liga.estado !== 'finalizado') return null
  if (!campeon) return <p className="nota mb-6">No hay un campeón confirmado disponible.</p>

  return (
    <Tarjeta
      as="section"
      aria-label="Campeón"
      className="mb-6 flex items-center gap-4 border-green-200 bg-green-50"
    >
      <ImagenEntidad ruta={campeon.escudo} nombre={campeon.nombre} />
      <div className="min-w-0">
        <p className="text-sm font-semibold text-marca-oscuro">Campeón confirmado</p>
        <h2 className="font-titulo text-xl font-bold break-words">{campeon.nombre}</h2>
      </div>
    </Tarjeta>
  )
}

export default function DetallePublicoPage() {
  const { ligaId } = useParams()
  const { pathname } = useLocation()
  const consultar = useCallback(async () => {
    const [liga, deporte] = await Promise.all([
      publicoService.obtener(ligaId),
      publicoService.deporte(ligaId),
    ])
    return { liga, deporte }
  }, [ligaId])
  // Se verifica la publicación al cambiar de sección o abrir una ficha.
  const { datos, cargando, error, estadoError, reintentar } = useConsultaPublica(
    consultar,
    pathname,
  )
  useEffect(() => {
    window.addEventListener('focus', reintentar)
    return () => window.removeEventListener('focus', reintentar)
  }, [reintentar])
  const liga = datos?.liga

  return (
    <div className="grid min-h-dvh grid-cols-1 content-start items-start bg-[#f5f7f6] lg:grid-cols-[14rem_minmax(0,1fr)]">
      <a href="#contenido-espectador" className="saltar-contenido">
        Saltar al contenido
      </a>
      <MenuEspectador ligaId={ligaId} />
      <main
        id="contenido-espectador"
        tabIndex={-1}
        className="mx-auto w-full min-w-0 max-w-7xl px-4 py-6 text-oscuro sm:px-8 lg:py-10"
      >
        <EstadoVista
          cargando={cargando}
          error={
            estadoError === 404
              ? 'Este campeonato no está publicado o ya no está disponible.'
              : error
          }
          reintentar={reintentar}
        />
        {liga && (
          <>
            <header className="mb-6 rounded-3xl bg-marca-oscuro p-5 text-white sm:p-8">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex w-full min-w-0 items-start gap-3 sm:w-auto sm:flex-1 sm:gap-5">
                  <LogoCampeonato
                    ruta={liga.logo}
                    respaldo={<IlustracionModulo tipo="publico" />}
                  />
                  <div className="min-w-0">
                    <h1 className="font-titulo text-2xl leading-tight font-bold break-words sm:text-4xl">
                      {liga.nombre}
                    </h1>
                    {liga.descripcion && (
                      <p className="mt-2 break-words text-green-50">{liga.descripcion}</p>
                    )}
                  </div>
                </div>
                <Boton variante="claro" onClick={reintentar}>
                  Actualizar
                </Boton>
              </div>
              <dl className="mt-5 flex flex-wrap gap-x-6 gap-y-3 border-t border-white/25 pt-4 text-sm">
                {[
                  ['Temporada', liga.temporada],
                  ['Formato', formatos[liga.formato]],
                  ['Estado', estados[liga.estado]],
                ].map(([etiqueta, valor]) => (
                  <div key={etiqueta}>
                    <dt className="text-green-100">{etiqueta}</dt>
                    <dd className="font-semibold break-words">{valor}</dd>
                  </div>
                ))}
              </dl>
            </header>
            {!(
              pathname.endsWith('/clasificacion') &&
              liga.formato !== 'todos_contra_todos' &&
              datos.deporte.fases.some(
                (fase) =>
                  fase.tipo === 'eliminacion' && campeonEnCuadro(fase, datos.deporte.campeon),
              )
            ) && <CampeonPublico liga={liga} campeon={datos.deporte.campeon} />}
            <Outlet context={datos} />
          </>
        )}
      </main>
    </div>
  )
}
