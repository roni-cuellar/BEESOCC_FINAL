import SalirSesion from './SalirSesion'
import { Link, NavLink, Outlet } from 'react-router-dom'
import logoClaro from '../assets/branding/nuevos/logo-fondo-blanco.png'
function AplicacionLayout({ destino, etiqueta }) {
  return (
    <div className="min-h-dvh bg-[#f5f7f6]">
      <a className="saltar-contenido" href="#contenido-app">
        Saltar al contenido
      </a>
      <header className="border-b border-borde bg-white">
        <div className="mx-auto flex min-h-20 max-w-7xl flex-wrap items-center justify-between gap-x-5 px-5 sm:px-8 lg:px-10">
          <Link
            className="marca-logo-claro"
            to="/seleccion-roles"
            aria-label="BEESOCC, selección de roles"
          >
            <img src={logoClaro} alt="BEESOCC" width="2172" height="724" />
          </Link>
          <nav
            className="flex flex-wrap items-center gap-2 text-sm"
            aria-label="Navegación de la aplicación"
          >
            {destino && (
              <>
                <NavLink
                  className="flex min-h-11 items-center rounded-lg px-3 font-semibold text-marca-oscuro hover:bg-green-50"
                  to={destino}
                >
                  {etiqueta}
                </NavLink>
                <Link
                  className="flex min-h-11 items-center rounded-lg px-3 text-marca-oscuro hover:bg-green-50"
                  to="/seleccion-roles"
                >
                  Cambiar rol
                </Link>
              </>
            )}
            <SalirSesion className="flex min-h-11 items-center rounded-lg px-3 text-gris hover:bg-gray-50" />
          </nav>
        </div>
      </header>
      <main id="contenido-app" tabIndex={-1}>
        <Outlet />
      </main>
    </div>
  )
}
export default AplicacionLayout
