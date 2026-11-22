import { Boton } from './ui'
import Icono from './Icono'
import LogoCampeonato from './LogoCampeonato'
function HeaderDashboard({ liga, ligaId, abierta, onMenu }) {
  return (
    <header className="sticky top-0 z-20 flex min-h-20 flex-wrap items-center justify-between gap-3 border-b border-borde bg-white px-4 py-4 sm:px-6 lg:px-8">
      <div className="flex min-w-0 flex-1 items-center gap-3">
        <button
          type="button"
          className="grid size-11 shrink-0 place-items-center rounded-xl border border-borde text-marca-oscuro hover:bg-green-50 lg:hidden"
          aria-controls="sidebar-liga"
          aria-expanded={abierta}
          onClick={onMenu}
        >
          <Icono nombre="menu" />
          <span className="sr-only">Menú de la liga</span>
        </button>
        <LogoCampeonato ruta={liga?.logo} respaldo={null} className="size-10 sm:size-12" />
        <div className="min-w-0">
          <p className="mb-1 text-[9px] font-semibold tracking-[.14em] whitespace-nowrap text-gris">
            CAMPEONATO
          </p>
          <p
            className="truncate text-sm font-semibold text-oscuro sm:text-base"
            title={liga?.nombre}
          >
            {liga?.nombre || 'Cargando campeonato…'}
          </p>
        </div>
      </div>
      <Boton
        variante="secundario"
        to={
          '/ligas/' +
          ligaId +
          (liga?.formato === 'grupos_eliminacion' ? '/grupos' : '/calendario#configuracion')
        }
        aria-label="Configuración"
        className="min-w-11 shrink-0 px-3"
      >
        <Icono nombre="calendario" />
        <span className="hidden sm:inline">Configuración</span>
      </Boton>
    </header>
  )
}
export default HeaderDashboard
