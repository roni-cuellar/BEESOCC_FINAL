import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import useLigaStore from '../stores/useLigaStore'
import ligaService from '../services/ligaService'
import LigaCard from '../components/LigaCard'
import EstadoVista from '../components/EstadoVista'
import Confirmacion from '../components/Confirmacion'
import Aviso from '../components/Aviso'
import { Boton } from '../components/ui'
import Icono from '../components/Icono'

function MisLigasPage() {
  const { ligas, cargando, error, cargarLigas, cambiarPublicacion, publicando } = useLigaStore()
  const [eliminar, setEliminar] = useState(null)
  const [publicacion, setPublicacion] = useState(null)
  const [mensaje, setMensaje] = useState(useLocation().state?.mensaje || '')
  useEffect(() => { cargarLigas() }, [cargarLigas])
  return <section className="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10 lg:py-12">
    <div className="mb-8 flex flex-col items-start justify-between gap-6 border-b border-borde pb-8 sm:flex-row sm:items-center"><div><p className="mb-3 text-xs font-semibold tracking-widest text-marca-oscuro">TU ESPACIO DE COMPETICIÓN</p><h1 className="font-titulo text-4xl font-bold tracking-tight text-oscuro">Mis ligas</h1><p className="mt-3 text-sm leading-7 text-gris sm:text-base">Cada campeonato, con su propia historia. Organiza el próximo encuentro.</p></div><Boton to="/ligas/nueva"><Icono nombre="mas" />Crear liga</Boton></div>
    
    <Aviso exito={mensaje} onCerrar={() => setMensaje('')} />
    <EstadoVista cargando={cargando} error={error} vacio={!ligas.length} titulo="Tu próximo campeonato empieza aquí" texto="Crea una liga y reúne a tus equipos. El resto del torneo comienza desde aquí." accion={<Boton to="/ligas/nueva">Crear liga</Boton>} reintentar={cargarLigas} />
    {!cargando && !error && ligas.length > 0 && <div className="mb-5 flex items-center gap-3 text-sm text-gris"><span className="h-2 w-2 rounded-full bg-marca" />{ligas.length} {ligas.length === 1 ? 'campeonato' : 'campeonatos'}<span className="ml-auto hidden text-xs sm:block">Selecciona una liga para administrarla</span></div>}
    {!cargando && !error && ligas.length > 0 && <div className="grid items-stretch gap-6 md:grid-cols-2 xl:grid-cols-3">{ligas.map(liga => <LigaCard key={liga.id} liga={liga} onEliminar={setEliminar} onPublicacion={setPublicacion} publicando={publicando} />)}</div>}
    {publicacion && <Confirmacion titulo={publicacion.publicado ? 'Ocultar campeonato' : 'Publicar campeonato'} texto={publicacion.publicado ? 'El campeonato dejará de estar disponible para espectadores.' : 'Los espectadores podrán consultar la información deportiva de este campeonato.'} onCerrar={() => setPublicacion(null)} onConfirmar={async () => {
      const actualizada = await cambiarPublicacion(publicacion.id, !publicacion.publicado)
      setMensaje(actualizada.publicado ? 'Campeonato publicado correctamente.' : 'Campeonato oculto correctamente.')
    }} />}
    {eliminar && <Confirmacion titulo="Eliminar liga" texto={'¿Eliminar “' + eliminar.nombre + '”? Solo se puede eliminar si no tiene equipos inscritos ni partidos.'} onCerrar={() => setEliminar(null)} onConfirmar={async () => { await ligaService.eliminar(eliminar.id); await cargarLigas(); setMensaje('Liga eliminada correctamente.') }} />}
  </section>
}
export default MisLigasPage
