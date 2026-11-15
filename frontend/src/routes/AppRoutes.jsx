import { useEffect } from 'react'
import { Link, Navigate, Outlet, Route, Routes, useLocation, useOutletContext } from 'react-router-dom'
import RutaPrivada from '../components/RutaPrivada'
import Navbar from '../components/Navbar'
import Footer from '../components/Footer'
import DashboardLayout from '../components/DashboardLayout'
import AccesoLayout from '../components/AccesoLayout'
import AplicacionLayout from '../components/AplicacionLayout'
import InicioPage from '../pages/InicioPage'
import LoginPage from '../pages/LoginPage'
import RegistroPage from '../pages/RegistroPage'
import MisLigasPage from '../pages/MisLigasPage'
import CrearLigaPage from '../pages/CrearLigaPage'
import DashboardPage from '../pages/DashboardPage'
import ArbitrosPage from '../pages/ArbitrosPage'
import MisArbitrajesPage from '../pages/MisArbitrajesPage'
import PartidosArbitrajePage from '../pages/PartidosArbitrajePage'
import EquiposPage from '../pages/EquiposPage'
import JugadoresPage from '../pages/JugadoresPage'
import PartidosPage from '../pages/PartidosPage'
import PartidoDetallePage from '../pages/PartidoDetallePage'
import CalendarioPage from '../pages/CalendarioPage'
import TablaPage from '../pages/TablaPage'
import EstadisticasPage from '../pages/EstadisticasPage'
import CampeonatosPublicosPage from '../pages/CampeonatosPublicosPage'
import DetallePublicoPage, { ResumenPublicoPage } from '../pages/DetallePublicoPage'
import EliminacionPage from '../pages/EliminacionPage'

function PublicLayout() {
  return <><a className="saltar-contenido" href="#contenido">Saltar al contenido</a><Navbar /><main id="contenido" tabIndex={-1}><Outlet /></main><Footer /></>
}
function TablaPrivada() {
  const { liga } = useOutletContext()
  return liga.formato === 'eliminacion_directa' ? <Navigate to={'/ligas/' + liga.id + '/cuadro'} replace /> : <TablaPage />
}
function AppRoutes() {
  const { pathname, hash } = useLocation()
  useEffect(() => {
    if (hash) document.getElementById(hash.slice(1))?.scrollIntoView()
    else window.scrollTo(0, 0)
  }, [pathname, hash])
  return <Routes>
    <Route element={<PublicLayout />}>
      <Route path="/" element={<InicioPage />} />
      <Route path="/campeonatos" element={<CampeonatosPublicosPage />} />
      <Route path="/campeonatos/:ligaId" element={<DetallePublicoPage />}>
        <Route index element={<ResumenPublicoPage />} />
        <Route path="tabla" element={<TablaPage publica />} />
        <Route path="estadisticas" element={<EstadisticasPage publica />} />
      </Route>
      <Route path="*" element={<section className="pagina-provisional contenedor"><p className="etiqueta">404</p><h1>Página no encontrada</h1><Link className="boton boton-verde" to="/mis-ligas">Volver a Mis ligas</Link></section>} />
    </Route>
    <Route element={<AccesoLayout />}>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/registro" element={<RegistroPage />} />
    </Route>
    <Route element={<RutaPrivada />}>
    <Route element={<AplicacionLayout />}>
      <Route path="/mis-ligas" element={<MisLigasPage />} />
      <Route path="/mis-arbitrajes" element={<MisArbitrajesPage />} />
      <Route path="/mis-arbitrajes/:ligaId/partidos" element={<PartidosArbitrajePage />} />
      <Route path="/mis-arbitrajes/:ligaId/cuadro" element={<EliminacionPage arbitral />} />
      <Route path="/mis-arbitrajes/:ligaId/partidos/:partidoId" element={<section className="mx-auto max-w-7xl px-5 py-8 sm:px-8"><PartidoDetallePage arbitral /></section>} />
      <Route path="/ligas/nueva" element={<CrearLigaPage />} />
      <Route path="/ligas/:ligaId/editar" element={<CrearLigaPage />} />
    </Route>
    <Route path="/ligas/:ligaId" element={<DashboardLayout />}>
      <Route index element={<Navigate to="dashboard" replace />} />
      <Route path="dashboard" element={<DashboardPage />} />
      <Route path="arbitros" element={<ArbitrosPage />} />
      <Route path="equipos" element={<EquiposPage />} />
      <Route path="jugadores" element={<JugadoresPage />} />
      <Route path="partidos" element={<PartidosPage />} />
      <Route path="partidos/:partidoId" element={<PartidoDetallePage />} />
      <Route path="calendario" element={<CalendarioPage />} />
      <Route path="cuadro" element={<EliminacionPage />} />
      <Route path="tabla" element={<TablaPrivada />} />
      <Route path="estadisticas" element={<EstadisticasPage />} />
      <Route path="*" element={<section className="panel"><h1>Página no encontrada</h1><Link to="/mis-ligas" className="enlace-texto">Volver a Mis Ligas</Link></section>} />
    </Route>
    </Route>
  </Routes>
}
export default AppRoutes
