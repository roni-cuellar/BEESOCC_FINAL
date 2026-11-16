import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import logo from '../assets/branding/logo.png'
import Icono from './Icono'
import { Boton } from './ui'

function Navbar() {
  const [abierto, setAbierto] = useState(false)
  const control = useRef(null)
  const cerrar = () => setAbierto(false)
  return (
    <header className="sticky top-0 z-20 border-b border-white/10 bg-marca-oscuro text-white">
      <div className="relative mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-5 px-5 sm:px-8 lg:px-10">
        <Link
          className="marca-logo w-40 sm:w-48"
          to="/"
          aria-label="BEESOCC, inicio"
          onClick={cerrar}
        >
          <img src={logo} alt="Logo oficial de BEESOCC" width="1448" height="1086" />
        </Link>
        <button
          ref={control}
          type="button"
          className="flex min-h-11 items-center gap-2 rounded-xl border border-white/25 px-3 text-sm lg:hidden"
          aria-expanded={abierto}
          aria-controls="navegacion-principal"
          onClick={() => setAbierto(!abierto)}
        >
          {abierto ? 'Cerrar' : 'Menú'}
          <Icono nombre={abierto ? 'cerrar' : 'menu'} />
        </button>
        <nav
          id="navegacion-principal"
          aria-label="Navegación principal"
          onKeyDown={(e) => {
            if (e.key === 'Escape') {
              cerrar()
              control.current?.focus()
            }
          }}
          className={
            'absolute inset-x-0 top-full border-b border-white/15 bg-marca-oscuro p-5 shadow-lg lg:static lg:flex lg:flex-1 lg:items-center lg:justify-end lg:gap-8 lg:border-0 lg:p-0 lg:shadow-none ' +
            (abierto ? 'grid gap-5' : 'hidden')
          }
        >
          <div className="grid gap-1 lg:flex lg:items-center lg:gap-5">
            {[
              ['/', 'Inicio'],
              ['/campeonatos', 'Campeonatos'],
              ['/#caracteristicas', 'Características'],
              ['/#como-funciona', 'Cómo funciona'],
            ].map(([to, texto]) => (
              <Link
                key={to}
                className="flex min-h-11 items-center rounded-lg px-2 text-sm text-green-50 hover:bg-white/10"
                to={to}
                onClick={cerrar}
              >
                {texto}
              </Link>
            ))}
          </div>
          <div className="flex flex-wrap items-center gap-3 border-t border-white/15 pt-4 lg:border-0 lg:pt-0">
            <Link
              className="flex min-h-11 items-center rounded-lg px-2 text-sm hover:bg-white/10"
              to="/login"
              onClick={cerrar}
            >
              Iniciar sesión
            </Link>
            <Boton to="/registro" variante="claro" onClick={cerrar}>
              Registrarse
            </Boton>
          </div>
        </nav>
      </div>
    </header>
  )
}
export default Navbar
