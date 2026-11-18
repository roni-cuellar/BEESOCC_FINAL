import { Tarjeta } from './ui'
import Icono from './Icono'

export default function ResumenDeportivo({ equipos, jugadores, partidos, arbitros, cargando }) {
  return (
    <div
      className="mb-7 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4"
      aria-label="Cifras del campeonato"
    >
      {[
        ['Equipos inscritos', equipos, 'equipos'],
        ['Jugadores', jugadores, 'jugadores'],
        ['Partidos', partidos, 'partidos'],
        ['Árbitros asignados', arbitros, 'jugadores'],
      ].map(([titulo, cantidad, icono]) => (
        <Tarjeta key={titulo} className="resumen-cifra">
          <span
            className="mb-4 grid size-10 place-items-center rounded-xl bg-green-50 text-marca-oscuro"
            aria-hidden="true"
          >
            <Icono nombre={icono} />
          </span>
          <p className="text-xs font-semibold text-gris sm:text-sm">{titulo}</p>
          <p className="mt-2 font-titulo text-3xl font-bold tracking-tight text-marca-oscuro sm:text-4xl">
            {cantidad ?? '—'}
          </p>
          <p className="mt-2 text-xs text-gris">
            {cantidad == null
              ? cargando
                ? 'Consultando…'
                : 'No disponible'
              : 'En este campeonato'}
          </p>
        </Tarjeta>
      ))}
    </div>
  )
}
