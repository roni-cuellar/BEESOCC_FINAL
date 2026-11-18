import { Link, Outlet } from 'react-router-dom'
import logo from '../assets/branding/logo.png'
import logoClaro from '../assets/branding/nuevos/logo-fondo-blanco.png'
import ayuda from '../assets/branding/optimizadas/equipos.png'

function AccesoLayout() {
  return (
    <div className="acceso-visual grid min-h-dvh bg-[#f4f7f5] lg:grid-cols-[1fr_1.1fr]">
      <aside
        className="acceso-ilustrado hidden flex-col justify-between bg-marca-oscuro px-12 py-10 text-white lg:flex xl:px-20"
        aria-label="BEESOCC, organización deportiva"
      >
        <Link className="marca-logo" to="/" aria-label="BEESOCC, inicio">
          <img src={logo} alt="BEESOCC" width="1448" height="1086" />
        </Link>
        <div className="py-8">
          <p className="mb-5 text-xs font-semibold tracking-[.15em] text-green-200">
            TU CANCHA. TU GENTE. TU CAMPEONATO.
          </p>
          <h2 className="max-w-md font-titulo text-4xl leading-tight font-bold xl:text-5xl">
            El fútbol nos reúne.
            <br />
            Organizarlo nos mueve.
          </h2>
          <img
            className="mx-auto mt-6 w-full max-w-sm object-contain"
            src={ayuda}
            alt="Ilustración oficial de equipos de BEESOCC"
            width="512"
            height="512"
          />
        </div>
        <p className="text-xs tracking-widest text-green-100">
          TODO TU CAMPEONATO EN UN SOLO LUGAR.
        </p>
      </aside>
      <main
        className="flex min-w-0 items-center justify-center px-4 py-8 sm:px-8 lg:py-12"
        id="contenido"
      >
        <section
          className="acceso-tarjeta w-full max-w-md rounded-3xl border border-borde bg-white px-5 py-8 sm:px-9 sm:py-10"
          aria-label="Acceso a BEESOCC"
        >
          <Link
            className="marca-logo-claro marca-logo-claro-acceso mx-auto mb-7"
            to="/"
            aria-label="BEESOCC, inicio"
          >
            <img src={logoClaro} alt="BEESOCC" width="2172" height="724" />
          </Link>
          <Outlet />
          <Link
            className="mt-7 flex min-h-11 items-center justify-center text-sm font-medium text-gris hover:text-marca-oscuro hover:underline"
            to="/"
          >
            Volver al inicio
          </Link>
        </section>
      </main>
    </div>
  )
}
export default AccesoLayout
