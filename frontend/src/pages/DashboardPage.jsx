import { useEffect, useState } from 'react'
import { useOutletContext, useParams } from 'react-router-dom'
import equipoService from '../services/equipoService'
import jugadorService from '../services/jugadorService'
import ligaService from '../services/ligaService'
import usePartidoStore from '../stores/usePartidoStore'
import PartidoCard from '../components/PartidoCard'
import EstadoVista from '../components/EstadoVista'
import Aviso from '../components/Aviso'
import { mensajeError } from '../services/api'
import { Boton, Tarjeta } from '../components/ui'
import EncabezadoPagina from '../components/layout/EncabezadoPagina'
import Icono from '../components/Icono'

function DashboardPage() {
  const { liga } = useOutletContext()
  const { ligaId } = useParams()
  const { partidos, cargando, error, cargar } = usePartidoStore()
  const [resumen, setResumen] = useState({ equipos: null, jugadores: null, tabla: [], error: '', cargando: true })
  const [intento, setIntento] = useState(0)
  useEffect(() => {
    let vigente = true
    cargar(ligaId)
    async function consultar() {
      setResumen({ equipos: null, jugadores: null, tabla: [], error: '', cargando: true })
      const [inscripciones, tabla] = await Promise.allSettled([equipoService.inscripciones(ligaId), ligaService.tabla(ligaId)])
      let equipos = null, jugadores = null
      const errores = []
      if (inscripciones.status === 'fulfilled') {
        equipos = inscripciones.value.length
        const plantillas = await Promise.allSettled(inscripciones.value.map(i => jugadorService.plantillas(i.id)))
        if (plantillas.every(p => p.status === 'fulfilled')) jugadores = new Set(plantillas.flatMap(p => p.value.map(j => j.jugador_id))).size
        else errores.push('No fue posible consultar todas las plantillas.')
      } else errores.push(mensajeError(inscripciones.reason))
      if (tabla.status === 'rejected') errores.push(mensajeError(tabla.reason))
      if (vigente) setResumen({ equipos, jugadores, tabla: tabla.status === 'fulfilled' ? tabla.value.tabla : [], error: errores.join(' '), cargando: false })
    }
    consultar()
    return () => { vigente = false }
  }, [ligaId, cargar, intento])
  const proximos = partidos.filter(p => p.estado === 'programado').sort((a, b) => (a.fecha_hora ? new Date(a.fecha_hora).getTime() : Infinity) - (b.fecha_hora ? new Date(b.fecha_hora).getTime() : Infinity) || a.jornada - b.jornada).slice(0, 3)
  const ultimos = partidos.filter(p => p.estado === 'finalizado').sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at)).slice(0, 3)
  return <>
    <p className="mb-3 text-xs font-semibold tracking-widest text-marca-oscuro">TEMPORADA {liga.temporada}</p>
    <div className="mb-4"><Boton variante="secundario" to={'/ligas/' + ligaId + '/arbitros'}>Árbitros</Boton></div>
    <EncabezadoPagina titulo="Resumen" descripcion={liga.descripcion || 'Tu campeonato, de un vistazo. Organiza lo que viene y sigue lo que ya se jugó.'} acciones={<Boton variante="secundario" disabled={cargando || resumen.cargando} onClick={()=>setIntento(v=>v+1)}>Actualizar</Boton>} />
    <div className="mb-6 flex items-center gap-2 text-xs text-gris"><span className="size-2 rounded-full bg-marca" />{liga.estado === 'planificacion' ? 'Planificación' : liga.estado.replaceAll('_',' ')}</div>
    <div className="mb-7 grid gap-4 sm:grid-cols-3">{[['Equipos inscritos',resumen.equipos,'equipos'],['Jugadores',resumen.jugadores,'jugadores'],['Partidos',cargando || error ? null : partidos.length,'partidos']].map(([titulo,cantidad,icono])=><Tarjeta key={titulo}><div className="flex items-center justify-between gap-3"><p className="text-sm font-medium text-gris">{titulo}</p><span className="grid size-10 place-items-center rounded-xl bg-green-50 text-marca-oscuro"><Icono nombre={icono}/></span></div><p className="mt-4 font-titulo text-4xl font-bold tracking-tight text-oscuro">{cantidad ?? '—'}</p><p className="mt-2 text-xs text-gris">{cantidad === null ? (cargando || resumen.cargando ? 'Consultando información…' : 'No disponible') : 'En este campeonato'}</p></Tarjeta>)}</div>
    <Aviso error={resumen.error}/><EstadoVista cargando={cargando} error={error} reintentar={()=>setIntento(v=>v+1)}/>
    <div className="mb-10 flex flex-wrap gap-3">{[['equipos','Inscribir equipos','equipos'],['jugadores','Gestionar plantillas','jugadores'],['calendario','Organizar calendario','calendario']].map(([ruta,texto,icono])=><Boton variante="secundario" key={ruta} to={'/ligas/'+ligaId+'/'+ruta}><Icono nombre={icono}/>{texto}</Boton>)}</div>
    {!cargando && !error && <><div className="mb-5 flex items-center justify-between gap-4"><h2 className="font-titulo text-xl font-bold text-oscuro">Próximos encuentros</h2><Boton variante="secundario" to={'/ligas/'+ligaId+'/partidos'}>Ver todos</Boton></div>{proximos.length ? <div className="mb-9 grid gap-4 md:grid-cols-2 xl:grid-cols-3">{proximos.map(p=><PartidoCard key={p.id} partido={p} ligaId={ligaId}/>)}</div> : <Tarjeta className="mb-9"><div className="flex items-start gap-4"><Icono nombre="calendario" className="mt-1 text-marca-oscuro"/><div><p className="text-sm font-semibold text-oscuro">La próxima jornada está por organizarse</p><p className="mt-2 text-sm text-gris">No hay encuentros programados. Revisa el calendario para preparar los siguientes partidos.</p></div></div></Tarjeta>}<h2 className="mb-5 font-titulo text-xl font-bold text-oscuro">Últimos resultados</h2>{ultimos.length ? <div className="mb-9 grid gap-4 md:grid-cols-2 xl:grid-cols-3">{ultimos.map(p=><PartidoCard key={p.id} partido={p} ligaId={ligaId}/>)}</div> : <Tarjeta className="mb-9"><p className="text-sm text-gris">Aún no hay partidos finalizados. Los resultados aparecerán aquí cuando termine un encuentro.</p></Tarjeta>}</>}
    <div className="mb-5 flex items-center justify-between gap-4"><h2 className="font-titulo text-xl font-bold text-oscuro">Clasificación</h2><Boton variante="secundario" to={'/ligas/'+ligaId+'/tabla'}>Tabla completa</Boton></div>
    <Tarjeta sinRelleno><div className="overflow-x-auto"><table className="w-full"><caption className="sr-only">Primeras posiciones del campeonato</caption><thead><tr><th>Pos.</th><th>Equipo</th><th>PJ</th><th>PTS</th></tr></thead><tbody>{resumen.tabla.slice(0,5).map(f=><tr key={f.equipo_id}><td>{f.posicion}</td><td>{f.nombre}</td><td>{f.PJ}</td><td><strong>{f.PTS}</strong></td></tr>)}</tbody></table></div>{!resumen.tabla.length && <p className="px-5 py-7 text-sm text-gris">{resumen.cargando ? 'Consultando clasificación…' : resumen.error ? 'No se pudo obtener toda la información. Vuelve a actualizar el resumen.' : 'Todavía no hay equipos en la clasificación.'}</p>}</Tarjeta>
  </>
}
export default DashboardPage
