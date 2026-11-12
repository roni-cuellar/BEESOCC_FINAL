import SalirSesion from './SalirSesion'
import { Link, NavLink, Outlet } from 'react-router-dom'
import logo from '../assets/branding/logo.png'
function AplicacionLayout() {
  return <div className="min-h-dvh bg-[#f5f7f6]"><a className="saltar-contenido" href="#contenido-app">Saltar al contenido</a><header className="border-b border-borde bg-white"><div className="mx-auto flex min-h-20 max-w-7xl flex-wrap items-center justify-between gap-x-5 px-5 sm:px-8 lg:px-10"><Link className="marca-logo w-40 sm:w-48" to="/mis-ligas" aria-label="BEESOCC, Mis ligas"><img src={logo} alt="BEESOCC" width="1448" height="1086" /></Link><nav className="flex items-center gap-2 text-sm" aria-label="Navegación de la aplicación"><NavLink className="flex min-h-11 items-center rounded-lg px-3 font-semibold text-marca-oscuro hover:bg-green-50" to="/mis-ligas">Mis ligas</NavLink><SalirSesion className="flex min-h-11 items-center rounded-lg px-3 text-gris hover:bg-gray-50" /></nav></div></header><main id="contenido-app" tabIndex={-1}><Outlet /></main></div>
}
export default AplicacionLayout
