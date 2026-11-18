import { Boton } from '../components/ui'
import Icono from '../components/Icono'
import ayuda from '../assets/branding/optimizadas/inicio.png'
import ayudaCampeonatos from '../assets/branding/optimizadas/campeonatos.png'
import ayudaEquipos from '../assets/branding/optimizadas/equipos.png'
import ayudaCalendario from '../assets/branding/optimizadas/partidos.png'
import ayudaEstadisticas from '../assets/branding/optimizadas/estadisticas.png'

const caracteristicas = [
  {
    numero: '01',
    titulo: 'Tu campeonato, a tu manera.',
    texto: 'Organiza tus ligas, define las vueltas y configura las reglas de desempate.',
    imagen: ayudaCampeonatos,
    alt: 'Trofeo de BEESOCC junto a un balón y recursos para organizar torneos',
  },
  {
    numero: '02',
    titulo: 'Equipos y jugadores',
    texto: 'Gestiona sus inscripciones, dorsales y posiciones sin perder el historial.',
    imagen: ayudaEquipos,
    alt: 'Jugadores con camisetas de BEESOCC, fichas de inscripción y una formación de equipo',
  },
  {
    numero: '03',
    titulo: 'Calendario y partidos',
    texto: 'Organiza las jornadas, programa los encuentros y sigue cada resultado.',
    imagen: ayudaCalendario,
    alt: 'Calendario de fútbol con un encuentro entre equipos y un silbato',
  },
  {
    numero: '04',
    titulo: 'Tabla de posiciones y estadísticas',
    texto: 'Consulta la clasificación, los goles, las asistencias y las tarjetas del torneo.',
    imagen: ayudaEstadisticas,
    alt: 'Tabla de posiciones y gráficos de estadísticas de un campeonato de fútbol',
  },
]

function InicioPage() {
  return (
    <>
      <section
        className="mx-auto grid w-full max-w-7xl items-center gap-7 px-5 pt-12 pb-14 sm:px-8 sm:pt-16 lg:grid-cols-[1fr_1.05fr] lg:gap-10 lg:px-10 lg:py-20"
        aria-labelledby="titulo-inicio"
      >
        <div className="min-w-0">
          <p className="mb-6 flex items-center gap-3 text-[10px] font-bold tracking-[.14em] text-marca-oscuro sm:text-xs">
            <span className="h-px w-8 bg-marca" />
            LA PASIÓN SE JUEGA. LA LIGA SE ORGANIZA.
          </p>
          <h1
            id="titulo-inicio"
            className="max-w-xl font-titulo text-[clamp(2.65rem,5.2vw,4.75rem)] leading-[1.05] font-extrabold tracking-[-.06em] text-oscuro"
          >
            Menos gestión.
            <br />
            Más <span className="text-marca">fútbol.</span>
          </h1>
          <p className="mt-6 max-w-md text-base leading-8 text-gris sm:text-lg">
            Equipos, jornadas y resultados conectados. Dedica tu energía al juego; organiza el
            campeonato con BEESOCC.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Boton to="/registro">Comenzar ahora</Boton>
            <Boton to="/#caracteristicas" variante="secundario">
              Conocer la plataforma
            </Boton>
          </div>
          <p className="mt-7 max-w-sm text-[11px] leading-5 font-semibold tracking-[.1em] text-marca-oscuro">
            TODO TU CAMPEONATO EN UN SOLO LUGAR.
          </p>
        </div>
        <figure className="relative min-w-0 rounded-[2rem] bg-[#edf5ef] p-3 sm:p-5 lg:rounded-[3rem]">
          <img
            className="relative z-1 aspect-[4/3] w-full object-contain"
            src={ayuda}
            alt="Ilustración oficial de BEESOCC con gestión de torneos, partidos, equipos y estadísticas"
            width="1448"
            height="1086"
            fetchPriority="high"
          />
          <figcaption className="mx-3 mb-3 flex items-center justify-between gap-4 border-t border-marca-oscuro/15 pt-4 text-xs text-marca-oscuro">
            <span>Del primer equipo al último silbatazo.</span>
            <Icono nombre="partidos" />
          </figcaption>
        </figure>
      </section>
      <div className="border-y border-borde bg-[#f5f8f6]">
        <div className="mx-auto flex max-w-7xl flex-col justify-between gap-3 px-5 py-6 sm:flex-row sm:items-center sm:px-8 lg:px-10">
          <p className="font-titulo text-lg font-bold text-marca-oscuro">Del barrio a la liga.</p>
          <p className="text-sm text-gris">La misma pasión. Una mejor organización.</p>
        </div>
      </div>
      <section
        id="caracteristicas"
        className="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24"
        aria-labelledby="titulo-caracteristicas"
      >
        <div className="mb-10 grid items-end gap-5 md:grid-cols-2">
          <div>
            <p className="mb-4 text-xs font-semibold tracking-widest text-marca-oscuro">
              EL TORNEO, BAJO CONTROL
            </p>
            <h2
              id="titulo-caracteristicas"
              className="max-w-lg font-titulo text-3xl leading-tight font-bold text-oscuro sm:text-4xl"
            >
              Organización que se nota.
              <br />
              Dentro y fuera de la cancha.
            </h2>
          </div>
          <p className="max-w-md text-sm leading-7 text-gris md:justify-self-end">
            Una vista clara de cada etapa: prepara tu liga, reúne a los equipos y sigue la
            competición sin perder su historial.
          </p>
        </div>
        <div className="grid gap-x-10 gap-y-8 md:grid-cols-2">
          {caracteristicas.map((c) => (
            <article
              key={c.numero}
              className="grid grid-cols-[88px_1fr] items-center gap-4 border-t border-borde pt-6 sm:grid-cols-[132px_1fr] sm:gap-6"
            >
              <img
                className="aspect-square w-full rounded-2xl bg-[#f5f8f6] object-contain"
                src={c.imagen}
                alt={c.alt}
                width="1254"
                height="1254"
                loading="lazy"
              />
              <div className="min-w-0">
                <span className="text-[10px] font-bold tracking-widest text-marca">{c.numero}</span>
                <h3 className="mt-2 font-titulo text-lg font-bold text-oscuro">{c.titulo}</h3>
                <p className="mt-3 text-sm leading-6 text-gris">{c.texto}</p>
              </div>
            </article>
          ))}
        </div>
      </section>
      <section
        id="como-funciona"
        className="bg-marca-oscuro text-white"
        aria-labelledby="titulo-pasos"
      >
        <div className="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
          <div className="grid gap-8 lg:grid-cols-[.9fr_1.2fr]">
            <div>
              <p className="mb-4 text-xs tracking-widest text-green-200">
                DEL PRIMER PASO AL PITAZO FINAL
              </p>
              <h2
                id="titulo-pasos"
                className="max-w-md font-titulo text-3xl leading-tight font-bold sm:text-4xl"
              >
                Tu próxima liga
                <br />
                empieza contigo.
              </h2>
              <p className="mt-5 max-w-sm text-sm leading-7 text-green-100">
                Menos información dispersa y más claridad para tomar decisiones en cada jornada.
              </p>
            </div>
            <ol className="grid list-none gap-6">
              {[
                ['Crea tu campeonato', 'Dale nombre, define su temporada y configura las vueltas.'],
                ['Reúne a tus equipos', 'Organiza inscripciones, jugadores y dorsales.'],
                [
                  'Que ruede el balón',
                  'Programa partidos, registra eventos y consulta resultados.',
                ],
              ].map(([titulo, texto], i) => (
                <li key={titulo} className="flex items-start gap-5 border-b border-white/15 pb-6">
                  <span className="pt-1 text-xs font-semibold text-green-200">0{i + 1}</span>
                  <div>
                    <h3 className="font-titulo text-lg font-semibold">{titulo}</h3>
                    <p className="mt-2 text-sm leading-6 text-green-100">{texto}</p>
                  </div>
                </li>
              ))}
            </ol>
          </div>
        </div>
      </section>
      <section
        className="mx-auto flex max-w-7xl flex-col items-start justify-between gap-7 px-5 py-16 sm:flex-row sm:items-center sm:px-8 lg:px-10 lg:py-20"
        aria-labelledby="titulo-cta"
      >
        <div>
          <p className="mb-3 text-xs font-semibold tracking-widest text-marca-oscuro">
            TU CANCHA. TU GENTE.
          </p>
          <h2
            id="titulo-cta"
            className="max-w-xl font-titulo text-3xl leading-tight font-bold text-oscuro sm:text-4xl"
          >
            La próxima gran liga
            <br />
            puede ser la tuya.
          </h2>
        </div>
        <Boton to="/registro">Comenzar ahora</Boton>
      </section>
    </>
  )
}
export default InicioPage
