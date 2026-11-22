import { useEffect, useRef, useState } from 'react'
import { Link, NavLink } from 'react-router-dom'
import logo from '../../assets/branding/nuevos/logo-fondo-blanco.png'
import Icono from '../Icono'
import SalirSesion from '../SalirSesion'
import useAuthStore from '../../stores/useAuthStore'

const secciones = [
  ['', 'Inicio', 'resumen'],
  ['/clasificacion', 'Clasificación', 'tabla'],
  ['/estadisticas', 'Estadísticas', 'estadisticas'],
]

export default function MenuEspectador({ ligaId }) {
  const [abierto, setAbierto] = useState(false)
  const botonMenu = useRef(null)
  const { usuario, verificado, verificar } = useAuthStore()
  useEffect(() => {
    if (!verificado) verificar()
  }, [verificado, verificar])

  return (
    <aside className="min-w-0 border-b border-borde bg-white lg:sticky lg:top-0 lg:flex lg:min-h-dvh lg:flex-col lg:self-start lg:border-r lg:border-b-0">
      <div className="flex min-h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:min-h-24">
        <Link
          to="/campeonatos"
          className="marca-logo-claro w-40 sm:w-44"
          aria-label="BEESOCC, campeonatos"
        >
          <img src={logo} alt="BEESOCC" width="2172" height="724" />
        </Link>
        <button
          ref={botonMenu}
          className="flex min-h-11 shrink-0 items-center gap-2 rounded-lg border border-borde px-3 font-semibold text-marca-oscuro lg:hidden"
          type="button"
          aria-expanded={abierto}
          aria-controls="menu-espectador"
          onClick={() => setAbierto(!abierto)}
        >
          Menú
          <Icono nombre={abierto ? 'cerrar' : 'menu'} />
        </button>
      </div>
      <nav
        id="menu-espectador"
        aria-label="Navegación del espectador"
        className={`gap-1 px-4 pb-4 lg:grid lg:flex-1 lg:content-start ${abierto ? 'grid' : 'hidden'}`}
        onKeyDown={(e) => {
          if (e.key === 'Escape' && abierto) {
            setAbierto(false)
            botonMenu.current?.focus()
          }
        }}
      >
        {secciones.map(([ruta, texto, icono]) => (
          <NavLink
            key={ruta}
            to={`/campeonatos/${ligaId}${ruta}`}
            end={!ruta}
            onClick={() => setAbierto(false)}
            className={({ isActive }) =>
              `flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold ${isActive ? 'bg-marca-oscuro text-white' : 'text-marca-oscuro hover:bg-green-50'}`
            }
          >
            <Icono nombre={icono} />
            {texto}
          </NavLink>
        ))}
        <div className="mt-4 grid gap-1 border-t border-borde pt-4 text-sm font-semibold text-marca-oscuro">
          <Link
            className="flex min-h-11 items-center rounded-lg px-3 hover:bg-green-50"
            to="/campeonatos"
          >
            Ver campeonatos
          </Link>
          {usuario ? (
            <>
              <Link
                className="flex min-h-11 items-center rounded-lg px-3 hover:bg-green-50"
                to="/seleccion-roles"
              >
                Cambiar rol
              </Link>
              <SalirSesion className="min-h-11 rounded-lg px-3 text-left hover:bg-green-50" />
            </>
          ) : (
            <Link
              className="flex min-h-11 items-center rounded-lg px-3 hover:bg-green-50"
              to="/login"
            >
              Iniciar sesión
            </Link>
          )}
        </div>
      </nav>
    </aside>
  )
}
