import { useEffect, useState } from 'react'
import { Navigate, useOutletContext, useParams } from 'react-router-dom'
import CuadroEliminacion from '../components/CuadroEliminacion'
import TablasGrupos from '../components/grupos/TablasGrupos'
import PartidoCard from '../components/PartidoCard'
import EstadoVista from '../components/EstadoVista'
import publicoService from '../services/publicoService'
import { mensajeError } from '../services/api'
import '../styles/grupos.css'
import '../styles/eliminacion.css'

export function CuadrosPublicos({ fases }) {
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
    <section key={fase.id}>
      <h2>{fase.nombre}</h2>
      {!fase.rondas.length ? (
        <p className="nota">Enfrentamientos por confirmar.</p>
      ) : (
        <CuadroEliminacion
          fase={fase}
          grupos={fases.flatMap((f) => f.grupos || [])}
          publica
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

export function GruposPublicos({ fases, partidos, vista, onVista }) {
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
    <section key={fase.id}>
      <h2>{fase.nombre}</h2>
      <TablasGrupos
        fase={fase}
        tablas={vista === 'oficial' ? fase.tablas_oficiales : fase.tablas_provisionales}
        clasificacion={fase.clasificacion}
        vista={vista}
        onVista={onVista}
        publica
      />
      {fase.grupos.map((g) => (
        <section key={g.id} aria-label={`Calendario ${g.codigo}`}>
          <h3>Calendario · {g.nombre || g.codigo}</h3>
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

export default function DeportePublicoPage({ grupos = false }) {
  const { liga, deporte } = useOutletContext()
  const { ligaId } = useParams()
  const [vista, setVista] = useState('oficial')
  const [consulta, setConsulta] = useState(null)
  const [revision, setRevision] = useState(0)
  useEffect(() => {
    let vigente = true
    if (grupos)
      publicoService
        .partidos(ligaId)
        .then((partidos) => {
          if (vigente) setConsulta({ ligaId, partidos })
        })
        .catch((e) => {
          if (vigente) setConsulta({ ligaId, error: mensajeError(e) })
        })
    return () => {
      vigente = false
    }
  }, [ligaId, grupos, revision])
  if (liga.formato === 'todos_contra_todos')
    return <Navigate to={`/campeonatos/${ligaId}/tabla`} replace />
  if (grupos && liga.formato !== 'grupos_eliminacion')
    return <Navigate to={`/campeonatos/${ligaId}/cuadro`} replace />
  if (!grupos) return <CuadrosPublicos fases={deporte.fases} />
  const actual = consulta?.ligaId === ligaId ? consulta : null
  if (!actual?.partidos)
    return (
      <EstadoVista
        cargando={!actual?.error}
        error={actual?.error}
        reintentar={() => {
          setConsulta(null)
          setRevision((r) => r + 1)
        }}
      />
    )
  return (
    <GruposPublicos
      fases={deporte.fases}
      partidos={actual.partidos}
      vista={vista}
      onVista={setVista}
    />
  )
}
