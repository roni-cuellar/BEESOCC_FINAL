import { useCallback, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import CuadroEliminacion from '../components/CuadroEliminacion'
import TablasGrupos from '../components/grupos/TablasGrupos'
import PartidoCard from '../components/PartidoCard'
import EstadoVista from '../components/EstadoVista'
import publicoService from '../services/publicoService'
import useConsultaPublica from '../hooks/useConsultaPublica'
import TablaPage from './TablaPage'
import { campeonEnCuadro } from '../utils/eliminacion'
import '../styles/grupos.css'
import '../styles/eliminacion.css'

export function CuadrosPublicos({ fases, campeon, mostrarFinal = true }) {
  const cuadros = fases.filter((f) => f.tipo === 'eliminacion')
  if (!cuadros.length)
    return (
      <EstadoVista
        vacio
        titulo="Cuadro pendiente"
        texto="La organización todavía no ha confirmado la fase eliminatoria."
      />
    )
  return cuadros.map((fase) => (
    <section key={fase.id} className="mb-8">
      <h2 className="mb-5 font-titulo text-2xl font-bold text-oscuro">{fase.nombre}</h2>
      {!fase.rondas.length ? (
        <p className="nota">Enfrentamientos por confirmar.</p>
      ) : (
        <CuadroEliminacion
          fase={fase}
          grupos={fases.flatMap((f) => f.grupos || [])}
          publica
          campeon={campeon}
          mostrarFinal={mostrarFinal}
          idCampeon={`campeon-cuadro-${fase.id}`}
          marcadores={Object.fromEntries(
            fase.rondas.flatMap((r) =>
              r.llaves.flatMap((l) => l.partidos.map((p) => [p.id, { datos: p.marcador }])),
            ),
          )}
        />
      )}
    </section>
  ))
}

export function GruposPublicos({ fases, partidos, vista, onVista, ligaId }) {
  const grupos = fases.filter((f) => f.tipo === 'grupos')
  if (!grupos.length)
    return (
      <EstadoVista
        vacio
        titulo="Grupos pendientes"
        texto="La organización todavía no ha confirmado los grupos."
      />
    )
  return grupos.map((fase) => (
    <section key={fase.id} className="mb-8">
      <h2 className="mb-5 font-titulo text-2xl font-bold text-oscuro">{fase.nombre}</h2>
      <TablasGrupos
        fase={fase}
        tablas={vista === 'oficial' ? fase.tablas_oficiales : fase.tablas_provisionales}
        clasificacion={fase.clasificacion}
        vista={vista}
        onVista={onVista}
        destinoEquipo={ligaId ? (id) => `/campeonatos/${ligaId}/equipos/${id}` : undefined}
        publica
      />
      {fase.grupos.map((g) => (
        <section key={g.id} className="mt-8" aria-label={`Calendario ${g.codigo}`}>
          <h3 className="mb-4">Calendario · {g.nombre || g.codigo}</h3>
          <div className="grid gap-4 lg:grid-cols-2">
            {partidos
              .filter((p) => p.grupo_id === g.id)
              .map((p) => (
                <PartidoCard key={p.id} partido={p} publica />
              ))}
          </div>
          {!partidos.some((p) => p.grupo_id === g.id) && (
            <p className="nota">No hay partidos programados en este grupo.</p>
          )}
        </section>
      ))}
      {fase.rondas_desempate?.length > 0 && (
        <CuadrosPublicos
          mostrarFinal={false}
          fases={[
            {
              ...fase,
              tipo: 'eliminacion',
              nombre: 'Partidos extra y penales',
              rondas: fase.rondas_desempate,
            },
          ]}
        />
      )}
    </section>
  ))
}

function ClasificacionGruposPublica({ liga, fases }) {
  const [vista, setVista] = useState('oficial')
  const consultar = useCallback(() => publicoService.partidos(liga.id), [liga.id])
  const consulta = useConsultaPublica(consultar)
  return (
    <>
      <EstadoVista {...consulta} />
      {consulta.datos && (
        <GruposPublicos
          ligaId={liga.id}
          fases={fases}
          partidos={consulta.datos}
          vista={vista}
          onVista={setVista}
        />
      )}
    </>
  )
}

export default function DeportePublicoPage() {
  const { liga, deporte } = useOutletContext()
  if (liga.formato === 'todos_contra_todos') return <TablaPage publica />
  const grupos = liga.formato === 'grupos_eliminacion'
  const faseCampeon = deporte.fases.find(
    (fase) => fase.tipo === 'eliminacion' && campeonEnCuadro(fase, deporte.campeon),
  )

  return (
    <>
      <h2 className="mb-5 font-titulo text-2xl font-bold">Clasificación</h2>
      {(grupos || faseCampeon) && (
        <nav
          aria-label="Fases del campeonato"
          className="mb-5 flex flex-wrap gap-2 text-sm font-semibold text-marca-oscuro"
        >
          {grupos && (
            <a
              href="#clasificacion-grupos"
              className="inline-flex min-h-11 items-center rounded-lg border border-borde bg-white px-3 hover:bg-green-50"
            >
              Clasificación de grupos
            </a>
          )}
          <a
            href="#cuadro-publico"
            className="inline-flex min-h-11 items-center rounded-lg border border-borde bg-white px-3 hover:bg-green-50"
          >
            Cuadro eliminatorio
          </a>
          {faseCampeon && (
            <a
              href={`#campeon-cuadro-${faseCampeon.id}`}
              className="inline-flex min-h-11 items-center rounded-lg border border-borde bg-white px-3 hover:bg-green-50"
            >
              Campeón
            </a>
          )}
        </nav>
      )}
      {grupos && (
        <div id="clasificacion-grupos">
          <ClasificacionGruposPublica liga={liga} fases={deporte.fases} />
        </div>
      )}
      <div id="cuadro-publico">
        <CuadrosPublicos
          fases={deporte.fases}
          campeon={liga.estado === 'finalizado' ? deporte.campeon : null}
        />
      </div>
    </>
  )
}
