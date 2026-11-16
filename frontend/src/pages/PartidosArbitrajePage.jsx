import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import arbitroService from '../services/arbitroService'
import { mensajeError } from '../services/api'
import PartidoCard from '../components/PartidoCard'
import EstadoVista from '../components/EstadoVista'

export default function PartidosArbitrajePage() {
  const { ligaId } = useParams()
  const [partidos, setPartidos] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [revision, setRevision] = useState(0)
  useEffect(() => {
    let vigente = true
    async function cargar() {
      setCargando(true); setError(''); setPartidos([])
      try {
        const datos = await arbitroService.partidos(ligaId)
        if (vigente) setPartidos(datos)
      } catch (e) { if (vigente) setError(mensajeError(e)) }
      finally { if (vigente) setCargando(false) }
    }
    cargar()
    // Al recuperar el foco se vuelve a comprobar la asignación en Laravel.
    const actualizar = () => setRevision(v => v + 1)
    window.addEventListener('focus', actualizar)
    const intervalo = window.setInterval(actualizar, 30000)
    return () => { vigente = false; window.removeEventListener('focus', actualizar); window.clearInterval(intervalo) }
  }, [ligaId, revision])
  return <section className="mx-auto max-w-7xl px-5 py-8 sm:px-8">
    <Link className="enlace-texto" to="/mis-arbitrajes">Mis arbitrajes</Link>
    {!cargando && !error && partidos.some(p => p.grupo_id) && <Link className="enlace-texto ml-4" to={'/mis-arbitrajes/' + ligaId + '/grupos'}>Grupos y desempates</Link>}
    {!cargando && !error && partidos.some(p => p.llave_id && !p.grupo_id) && <Link className="enlace-texto ml-4" to={'/mis-arbitrajes/' + ligaId + '/cuadro'}>Cuadro y penales</Link>}
    <div className="titulo-pagina"><div><h1>Partidos para arbitrar</h1><p className="texto-suave">Consulta el acta y controla los encuentros del campeonato seleccionado.</p></div><button className="boton boton-borde" disabled={cargando} onClick={() => setRevision(v => v + 1)}>Actualizar</button></div>
    <EstadoVista cargando={cargando} error={error} vacio={!partidos.length} titulo="Sin partidos programados" texto="El organizador todavía no ha registrado encuentros." reintentar={() => setRevision(v => v + 1)} />
    {!cargando && !error && <div className="rejilla-tarjetas">{partidos.map(p => <PartidoCard key={p.id + '-' + revision} partido={p} ligaId={ligaId} arbitral />)}</div>}
  </section>
}
