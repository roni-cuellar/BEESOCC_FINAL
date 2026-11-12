import { useCallback, useEffect, useState } from 'react'
import { Outlet, useParams } from 'react-router-dom'
import useLigaStore from '../stores/useLigaStore'
import Sidebar from './Sidebar'
import HeaderDashboard from './HeaderDashboard'
import EstadoVista from './EstadoVista'
function DashboardLayout() {
  const { ligaId } = useParams()
  const { liga, error, cargando, cargarLiga } = useLigaStore()
  const [abierta, setAbierta] = useState(false)
  const cerrar = useCallback(() => setAbierta(false), [])
  useEffect(() => { cargarLiga(ligaId) }, [ligaId, cargarLiga])
  const ligaActual = String(liga?.id) === ligaId ? liga : null
  return <div className="min-h-dvh bg-[#f5f7f6] lg:pl-64">
    <a className="saltar-contenido" href="#contenido-dashboard">Saltar al contenido</a>
    <Sidebar ligaId={ligaId} abierta={abierta} onCerrar={cerrar} />
    <div className="min-w-0" inert={abierta || undefined}>
      <HeaderDashboard liga={ligaActual} ligaId={ligaId} abierta={abierta} onMenu={() => setAbierta(!abierta)} />
      <main className="dashboard-main mx-auto w-full max-w-[1450px] px-4 py-7 sm:px-6 lg:px-8 lg:py-9" id="contenido-dashboard" tabIndex={-1}>
        {cargando || error || !ligaActual ? <EstadoVista cargando={cargando} error={error} reintentar={() => cargarLiga(ligaId)} /> : <Outlet context={{ liga: ligaActual }} />}
      </main>
    </div>
  </div>
}
export default DashboardLayout
