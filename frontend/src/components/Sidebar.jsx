import SalirSesion from './SalirSesion'
import { useEffect, useRef } from 'react'
import { Link, NavLink } from 'react-router-dom'
import logo from '../assets/branding/logo.png'
import Icono from './Icono'
const enlaces = [
  ['dashboard', 'Resumen', 'resumen'],
  ['arbitros', 'Árbitros', 'jugadores'],
  ['equipos', 'Equipos', 'equipos'],
  ['jugadores', 'Jugadores', 'jugadores'],
  ['partidos', 'Partidos', 'partidos'],
  ['calendario', 'Calendario', 'calendario'],
  ['tabla', 'Tabla de posiciones', 'tabla'],
  ['estadisticas', 'Estadísticas', 'estadisticas'],
]
function Sidebar({ ligaId, abierta, onCerrar, eliminacion = false, grupos = false }) {
  const modulos = grupos
    ? enlaces.map((e) => (e[0] === 'tabla' ? ['grupos', 'Grupos y eliminación', 'tabla'] : e))
    : eliminacion
      ? enlaces.map((e) => (e[0] === 'tabla' ? ['cuadro', 'Cuadro eliminatorio', 'tabla'] : e))
      : enlaces
  const panel = useRef(null)
  useEffect(() => {
    const escritorio = window.matchMedia('(min-width:1024px)')
    if (!abierta || escritorio.matches) return
    const anterior = document.activeElement
    const desborde = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const controles = () => [...panel.current.querySelectorAll('a[href],button:not([disabled])')]
    controles()[0]?.focus()
    function teclado(e) {
      if (e.key === 'Escape') {
        e.preventDefault()
        onCerrar()
      }
      if (e.key === 'Tab') {
        const lista = controles(),
          primero = lista[0],
          ultimo = lista[lista.length - 1]
        if (e.shiftKey && document.activeElement === primero) {
          e.preventDefault()
          ultimo?.focus()
        }
        if (!e.shiftKey && document.activeElement === ultimo) {
          e.preventDefault()
          primero?.focus()
        }
      }
    }
    document.addEventListener('keydown', teclado)
    const redimensionar = (e) => {
      if (e.matches) onCerrar()
    }
    escritorio.addEventListener('change', redimensionar)
    return () => {
      document.body.style.overflow = desborde
      document.removeEventListener('keydown', teclado)
      escritorio.removeEventListener('change', redimensionar)
      if (anterior?.isConnected) anterior.focus()
    }
  }, [abierta, onCerrar])
  return (
    <>
      {abierta && (
        <button
          className="fixed inset-0 z-40 bg-marca-oscuro/60 lg:hidden"
          tabIndex={-1}
          aria-label="Cerrar navegación"
          onClick={onCerrar}
        />
      )}
      <aside
        ref={panel}
        id="sidebar-liga"
        role={abierta ? 'dialog' : undefined}
        aria-modal={abierta || undefined}
        aria-label="Navegación del campeonato"
        className={
          'fixed inset-y-0 left-0 z-50 flex w-64 max-w-[85vw] flex-col overflow-y-auto bg-marca-oscuro p-5 text-white shadow-xl transition-transform duration-150 motion-reduce:transition-none lg:visible lg:translate-x-0 lg:shadow-none ' +
          (abierta ? 'visible translate-x-0' : 'invisible -translate-x-full')
        }
      >
        <div className="mb-8 flex items-center justify-between gap-2">
          <Link className="marca-logo w-40" to="/mis-ligas" onClick={onCerrar}>
            <img src={logo} alt="BEESOCC, Mis Ligas" width="1448" height="1086" />
          </Link>
          <button
            type="button"
            className="grid size-11 shrink-0 place-items-center rounded-xl border border-white/20 lg:hidden"
            aria-label="Cerrar menú de la liga"
            onClick={onCerrar}
          >
            <Icono nombre="cerrar" />
          </button>
        </div>
        <p className="mb-3 px-3 text-[10px] font-semibold tracking-[.16em] text-green-200">
          EL CAMPEONATO
        </p>
        <nav className="grid gap-1.5" aria-label="Módulos de la liga">
          {modulos.map(([ruta, texto, icono]) => (
            <NavLink
              key={ruta}
              to={'/ligas/' + ligaId + '/' + ruta}
              onClick={onCerrar}
              className={({ isActive }) =>
                'flex min-h-12 items-center gap-3 rounded-xl border px-3 text-sm transition-colors hover:bg-white/10 ' +
                (isActive
                  ? 'border-white/15 bg-white/10 font-semibold text-white shadow-[inset_3px_0_#16a34a]'
                  : 'border-transparent text-green-100')
              }
            >
              <Icono nombre={icono} />
              {texto}
            </NavLink>
          ))}
        </nav>
        <div className="mt-auto grid gap-1 border-t border-white/15 pt-5 text-sm">
          <Link
            className="flex min-h-11 items-center rounded-xl px-3 text-green-100 hover:bg-white/10"
            to="/mis-ligas"
            onClick={onCerrar}
          >
            Mis ligas
          </Link>
          <Link
            className="flex min-h-11 items-center rounded-xl px-3 text-green-100 hover:bg-white/10"
            to="/mis-arbitrajes"
            onClick={onCerrar}
          >
            Mis arbitrajes
          </Link>
          <SalirSesion className="flex min-h-11 items-center rounded-xl px-3 text-green-100 hover:bg-white/10" />
          <p className="mt-5 px-3 text-[9px] leading-5 tracking-wider text-green-200">
            TODO TU CAMPEONATO
            <br />
            EN UN SOLO LUGAR.
          </p>
        </div>
      </aside>
    </>
  )
}
export default Sidebar
