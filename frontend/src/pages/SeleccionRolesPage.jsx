import { Link, useLocation } from 'react-router-dom'
import IlustracionModulo from '../components/IlustracionModulo'
import { Boton } from '../components/ui'

const roles = [
  {
    titulo: 'Administrador',
    texto: 'Crea campeonatos y gestiona los equipos, el calendario y los resultados de tus ligas.',
    accion: 'Ir a Mis ligas',
    ilustracion: 'campeonatos',
    destino: '/mis-ligas',
  },
  {
    titulo: 'Árbitro',
    texto: 'Consulta tus asignaciones vigentes y registra las actas de los partidos que arbitras.',
    accion: 'Ir a Mis arbitrajes',
    ilustracion: 'arbitraje',
    destino: '/mis-arbitrajes',
  },
  {
    titulo: 'Espectador',
    texto: 'Busca una liga por código o explora los campeonatos publicados.',
    accion: 'Ver ligas públicas',
    ilustracion: 'publico',
    destino: '/campeonatos',
  },
]

export default function SeleccionRolesPage() {
  const { state } = useLocation()
  const destino = state?.desde
  const puedeContinuar =
    typeof destino === 'string' && /^\/(mis-ligas|mis-arbitrajes|ligas)(\/|[?#]|$)/.test(destino)

  return (
    <section className="mx-auto max-w-7xl px-5 pt-8 pb-12 sm:px-8 lg:px-10 lg:pt-14">
      <div className="relative overflow-hidden rounded-3xl bg-marca-oscuro px-6 py-9 text-white sm:px-10 lg:px-14 lg:py-14">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute top-1/2 right-10 hidden size-64 -translate-y-1/2 rounded-full border-2 border-white/15 before:absolute before:inset-8 before:rounded-full before:border before:border-white/20 after:absolute after:inset-y-0 after:left-1/2 after:border-l after:border-white/20 lg:block"
        />
        <div className="relative max-w-xl">
          <h1 className="font-titulo text-4xl font-bold tracking-tight sm:text-5xl">
            Bienvenido a BEESOCC
          </h1>
          <p className="mt-4 text-base leading-relaxed text-green-50 sm:text-lg">
            Elige cómo quieres participar. Puedes cambiar de rol en cualquier momento.
          </p>
        </div>
      </div>

      <ul aria-label="Roles disponibles" className="mt-5 grid gap-5 md:grid-cols-3 lg:mt-7">
        {roles.map(({ titulo, texto, accion, ilustracion, destino }) => (
          <li
            key={titulo}
            className="flex min-w-0 flex-col items-start rounded-2xl border border-borde border-t-4 border-t-[#16A34A] bg-white p-5 shadow-sm sm:p-7"
          >
            <div className="grid place-items-center rounded-2xl bg-green-50 p-2">
              <IlustracionModulo tipo={ilustracion} />
            </div>
            <h2 className="mt-4 font-titulo text-2xl font-bold text-marca-oscuro">{titulo}</h2>
            <p className="mt-2 mb-5 flex-1 text-sm leading-6 text-gris">{texto}</p>
            <Boton to={destino} variante="secundario" className="w-full">
              {accion}
            </Boton>
          </li>
        ))}
      </ul>

      {puedeContinuar && (
        <Link className="enlace-texto mt-4 inline-flex min-h-11 items-center" to={destino}>
          Continuar a la página solicitada
        </Link>
      )}
    </section>
  )
}
