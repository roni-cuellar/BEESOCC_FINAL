import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation, useParams } from 'react-router-dom'
import publicoService from '../services/publicoService'
import { mensajeError } from '../services/api'
import EncabezadoPagina from '../components/layout/EncabezadoPagina'
import EstadoVista from '../components/EstadoVista'
import ImagenEntidad from '../components/ImagenEntidad'
import PartidoCard from '../components/PartidoCard'
import { Boton, Tarjeta } from '../components/ui'

export function ResumenPublico({ equipos, partidos }) {
  return <>
    <h2 className="mb-4 font-titulo text-2xl font-bold text-oscuro">Equipos</h2>
    <EstadoVista vacio={!equipos.length} titulo="Aún no hay equipos inscritos" texto="La información deportiva aparecerá cuando el organizador la registre." />
    <div className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{equipos.map(inscripcion => <Tarjeta key={inscripcion.inscripcion_id}>
      <div className="flex items-center gap-3"><ImagenEntidad ruta={inscripcion.equipo.escudo} nombre={inscripcion.equipo.nombre} /><h3 className="font-semibold text-oscuro">{inscripcion.equipo.nombre}</h3></div>
    </Tarjeta>)}</div>
    <h2 className="mb-4 font-titulo text-2xl font-bold text-oscuro">Calendario y resultados</h2>
    <EstadoVista vacio={!partidos.length} titulo="No hay partidos programados" texto="Consulta esta página más adelante para conocer las jornadas." />
    <div className="grid gap-4 lg:grid-cols-2">{partidos.map(partido => <PartidoCard key={partido.id} partido={partido} publica />)}</div>
  </>
}

export function ResumenPublicoPage() {
  const { ligaId } = useParams()
  const [datos, setDatos] = useState(null)
  const [error, setError] = useState('')
  const [revision, setRevision] = useState(0)
  useEffect(() => {
    let vigente = true
    async function consultar() {
      setDatos(null); setError('')
      try {
        const [equipos, partidos] = await Promise.all([publicoService.equipos(ligaId), publicoService.partidos(ligaId)])
        if (vigente) setDatos({ equipos, partidos })
      } catch (e) { if (vigente) setError(mensajeError(e)) }
    }
    consultar()
    return () => { vigente = false }
  }, [ligaId, revision])
  if (!datos) return <EstadoVista cargando={!error} error={error} reintentar={() => setRevision(r => r + 1)} />
  return <ResumenPublico {...datos} />
}

export default function DetallePublicoPage() {
  const { ligaId } = useParams()
  const { pathname } = useLocation()
  const [liga, setLiga] = useState(null)
  const [error, setError] = useState('')
  const [revision, setRevision] = useState(0)
  useEffect(() => {
    const actualizar = () => setRevision(r => r + 1)
    window.addEventListener('focus', actualizar)
    return () => window.removeEventListener('focus', actualizar)
  }, [])
  useEffect(() => {
    let vigente = true
    async function consultar() {
      setLiga(null); setError('')
      try { const datos = await publicoService.obtener(ligaId); if (vigente) setLiga(datos) }
      catch (e) { if (vigente) setError(e.response?.status === 404 ? 'Este campeonato no está publicado o ya no está disponible.' : mensajeError(e)) }
    }
    consultar()
    return () => { vigente = false }
  }, [ligaId, pathname, revision])
  return <section className="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10 lg:py-12">
    <Boton to="/campeonatos" variante="secundario" className="mb-6">Volver a campeonatos</Boton>
    {!liga ? <EstadoVista cargando={!error} error={error} reintentar={() => setRevision(r => r + 1)} /> : <>
      <EncabezadoPagina titulo={liga.nombre} descripcion={liga.descripcion || 'Información deportiva del campeonato.'} acciones={<Boton variante="secundario" onClick={() => setRevision(r => r + 1)}>Actualizar</Boton>} />
      <p className="mb-5 text-sm text-gris">Temporada {liga.temporada} · {liga.formato === 'todos_contra_todos' ? 'Todos contra todos' : liga.formato} · {liga.estado.replaceAll('_', ' ')}</p>
      <nav className="mb-6 flex flex-wrap gap-2" aria-label="Consulta del campeonato">{[['', 'Resumen'], ['/tabla', 'Tabla'], ['/estadisticas', 'Estadísticas']].map(([ruta, texto]) => <NavLink key={ruta} end className={({ isActive }) => 'inline-flex min-h-11 items-center rounded-xl border px-4 text-sm font-semibold ' + (isActive ? 'border-marca-oscuro bg-marca-oscuro text-white' : 'border-borde bg-white text-marca-oscuro')} to={'/campeonatos/' + ligaId + ruta}>{texto}</NavLink>)}</nav>
      <Outlet />
    </>}
  </section>
}
