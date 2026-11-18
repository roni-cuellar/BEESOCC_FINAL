import CicloCampeonatoPanel from '../components/CicloCampeonatoPanel'
import { useEffect, useState } from 'react'
import { useOutletContext, useParams } from 'react-router-dom'
import equipoService from '../services/equipoService'
import jugadorService from '../services/jugadorService'
import ligaService from '../services/ligaService'
import arbitroService from '../services/arbitroService'
import usePartidoStore from '../stores/usePartidoStore'
import PartidoCard from '../components/PartidoCard'
import EstadoVista from '../components/EstadoVista'
import Aviso from '../components/Aviso'
import { mensajeError } from '../services/api'
import { Boton, Tarjeta } from '../components/ui'
import IlustracionModulo from '../components/IlustracionModulo'
import ResumenDeportivo from '../components/ResumenDeportivo'
import Icono from '../components/Icono'

function DashboardPage() {
  const { liga } = useOutletContext()
  const eliminacion = liga.formato === 'eliminacion_directa'
  const grupos = liga.formato === 'grupos_eliminacion'
  const { ligaId } = useParams()
  const { partidos, cargando, error, cargar } = usePartidoStore()
  const [resumen, setResumen] = useState({
    equipos: null,
    jugadores: null,
    arbitros: null,
    tabla: [],
    error: '',
    cargando: true,
  })
  const [intento, setIntento] = useState(0)
  useEffect(() => {
    let vigente = true
    cargar(ligaId)
    async function consultar() {
      setResumen({
        equipos: null,
        jugadores: null,
        arbitros: null,
        tabla: [],
        error: '',
        cargando: true,
      })
      const [inscripciones, tabla, arbitraje] = await Promise.allSettled([
        equipoService.inscripciones(ligaId),
        eliminacion || grupos ? Promise.resolve({ tabla: [] }) : ligaService.tabla(ligaId),
        arbitroService.vigente(ligaId),
      ])
      let equipos = null,
        jugadores = null
      const errores = []
      if (inscripciones.status === 'fulfilled') {
        equipos = inscripciones.value.length
        const plantillas = await Promise.allSettled(
          inscripciones.value.map((i) => jugadorService.plantillas(i.id)),
        )
        if (plantillas.every((p) => p.status === 'fulfilled'))
          jugadores = new Set(plantillas.flatMap((p) => p.value.map((j) => j.jugador_id))).size
        else errores.push('No fue posible consultar todas las plantillas.')
      } else errores.push(mensajeError(inscripciones.reason))
      if (tabla.status === 'rejected') errores.push(mensajeError(tabla.reason))
      if (arbitraje.status === 'rejected') errores.push(mensajeError(arbitraje.reason))
      if (vigente)
        setResumen({
          equipos,
          jugadores,
          arbitros: arbitraje.status === 'fulfilled' ? (arbitraje.value ? 1 : 0) : null,
          tabla: tabla.status === 'fulfilled' ? tabla.value.tabla : [],
          error: errores.join(' '),
          cargando: false,
        })
    }
    consultar()
    return () => {
      vigente = false
    }
  }, [ligaId, cargar, intento, eliminacion, grupos])
  const proximos = partidos
    .filter((p) => p.estado === 'programado')
    .sort(
      (a, b) =>
        (a.fecha_hora ? new Date(a.fecha_hora).getTime() : Infinity) -
          (b.fecha_hora ? new Date(b.fecha_hora).getTime() : Infinity) || a.jornada - b.jornada,
    )
    .slice(0, 3)
  const ultimos = partidos
    .filter((p) => p.estado === 'finalizado')
    .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
    .slice(0, 3)
  return (
    <>
      <header className="dashboard-portada mb-7">
        <div className="flex min-w-0 items-start gap-4 sm:gap-6">
          <IlustracionModulo
            tipo={grupos ? 'grupos' : eliminacion ? 'eliminacion' : 'campeonatos'}
          />
          <div className="min-w-0">
            <p className="mb-3 text-xs font-semibold tracking-widest text-green-200">
              TEMPORADA {liga.temporada} · RESUMEN
            </p>
            <h1 className="font-titulo text-3xl leading-tight font-bold break-words sm:text-4xl">
              {liga.nombre}
            </h1>
            <p className="mt-3 max-w-2xl text-sm leading-6 text-green-100">
              {liga.descripcion ||
                'Tu campeonato, de un vistazo. Organiza lo que viene y sigue lo que ya se jugó.'}
            </p>
            <span className="mt-5 inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1.5 text-xs font-semibold">
              <span className="size-2 rounded-full bg-green-300" aria-hidden="true" />
              {liga.estado === 'planificacion'
                ? 'Planificación'
                : liga.estado === 'en_curso'
                  ? 'En curso'
                  : liga.estado === 'finalizado'
                    ? 'Finalizado'
                    : liga.estado}
            </span>
          </div>
        </div>
        <div className="flex flex-wrap gap-3">
          <Boton
            variante="claro"
            disabled={cargando || resumen.cargando}
            onClick={() => setIntento((v) => v + 1)}
          >
            Actualizar
          </Boton>
          <Boton variante="claro" to={'/ligas/' + ligaId + '/arbitros'}>
            Árbitros
          </Boton>
        </div>
      </header>
      <ResumenDeportivo
        equipos={resumen.equipos}
        jugadores={resumen.jugadores}
        partidos={cargando || error ? null : partidos.length}
        arbitros={resumen.arbitros}
        cargando={cargando || resumen.cargando}
      />
      <CicloCampeonatoPanel key={`${liga.id}:${liga.estado}`} liga={liga} />
      <Aviso error={resumen.error} />
      <EstadoVista cargando={cargando} error={error} reintentar={() => setIntento((v) => v + 1)} />
      <nav className="dashboard-accesos mb-10" aria-label="Accesos rápidos">
        {[
          ['equipos', 'Inscribir equipos', 'equipos'],
          ['jugadores', 'Gestionar plantillas', 'jugadores'],
          ['calendario', 'Organizar calendario', 'calendario'],
        ].map(([ruta, texto, icono]) => (
          <Boton variante="secundario" key={ruta} to={'/ligas/' + ligaId + '/' + ruta}>
            <Icono nombre={icono} />
            {texto}
          </Boton>
        ))}
      </nav>
      {!cargando && !error && (
        <>
          <div className="mb-5 flex items-center justify-between gap-4">
            <h2 className="font-titulo text-xl font-bold text-oscuro">Próximos encuentros</h2>
            <Boton variante="secundario" to={'/ligas/' + ligaId + '/partidos'}>
              Ver todos
            </Boton>
          </div>
          {proximos.length ? (
            <div className="mb-9 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {proximos.map((p) => (
                <PartidoCard key={p.id} partido={p} ligaId={ligaId} />
              ))}
            </div>
          ) : (
            <Tarjeta className="mb-9">
              <div className="flex items-start gap-4">
                <Icono nombre="calendario" className="mt-1 text-marca-oscuro" />
                <div>
                  <p className="text-sm font-semibold text-oscuro">
                    La próxima jornada está por organizarse
                  </p>
                  <p className="mt-2 text-sm text-gris">
                    No hay encuentros programados. Revisa el calendario para preparar los siguientes
                    partidos.
                  </p>
                </div>
              </div>
            </Tarjeta>
          )}
          <h2 className="mb-5 font-titulo text-xl font-bold text-oscuro">Últimos resultados</h2>
          {ultimos.length ? (
            <div className="mb-9 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {ultimos.map((p) => (
                <PartidoCard key={p.id} partido={p} ligaId={ligaId} />
              ))}
            </div>
          ) : (
            <Tarjeta className="mb-9">
              <p className="text-sm text-gris">
                Aún no hay partidos finalizados. Los resultados aparecerán aquí cuando termine un
                encuentro.
              </p>
            </Tarjeta>
          )}
        </>
      )}
      {grupos ? (
        <Tarjeta>
          <h2>De los grupos al título</h2>
          <p className="nota">
            Consulta posiciones, actas, desempates y la clasificación oficial antes de generar los
            cruces.
          </p>
          <Boton to={'/ligas/' + ligaId + '/grupos'}>Abrir grupos y eliminación</Boton>
        </Tarjeta>
      ) : eliminacion ? (
        <Tarjeta>
          <h2>Clasificados por llaves</h2>
          <p className="nota">
            Consulta las rondas, resultados oficiales y clasificados en el cuadro eliminatorio.
          </p>
          <Boton to={'/ligas/' + ligaId + '/cuadro'}>Abrir cuadro eliminatorio</Boton>
        </Tarjeta>
      ) : (
        <>
          <div className="mb-5 flex items-center justify-between gap-4">
            <h2 className="font-titulo text-xl font-bold text-oscuro">Clasificación</h2>
            <Boton variante="secundario" to={'/ligas/' + ligaId + '/tabla'}>
              Tabla completa
            </Boton>
          </div>
          <Tarjeta sinRelleno>
            <div className="overflow-x-auto">
              <table className="w-full">
                <caption className="sr-only">Primeras posiciones del campeonato</caption>
                <thead>
                  <tr>
                    <th>Pos.</th>
                    <th>Equipo</th>
                    <th>PJ</th>
                    <th>PTS</th>
                  </tr>
                </thead>
                <tbody>
                  {resumen.tabla.slice(0, 5).map((f) => (
                    <tr key={f.equipo_id}>
                      <td>{f.posicion}</td>
                      <td>{f.nombre}</td>
                      <td>{f.PJ}</td>
                      <td>
                        <strong>{f.PTS}</strong>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {!resumen.tabla.length && (
              <p className="px-5 py-7 text-sm text-gris">
                {resumen.cargando
                  ? 'Consultando clasificación…'
                  : resumen.error
                    ? 'No se pudo obtener toda la información. Vuelve a actualizar el resumen.'
                    : 'Todavía no hay equipos en la clasificación.'}
              </p>
            )}
          </Tarjeta>
        </>
      )}
    </>
  )
}
export default DashboardPage
