import { expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { CuadrosPublicos, GruposPublicos } from '../../src/pages/DeportePublicoPage'
import PartidoCard from '../../src/components/PartidoCard'
import publicoService from '../../src/services/publicoService'
import api from '../../src/services/api'

const render = (elemento) => renderToStaticMarkup(<MemoryRouter>{elemento}</MemoryRouter>)
const equipos = [1, 2].map((id) => ({
  id,
  campeonato_equipo_id: id,
  inscripcion: { equipo: { nombre: `Equipo ${id}` } },
}))
const partido = {
  id: 1,
  jornada: 1,
  grupo_id: 1,
  estado: 'finalizado',
  tipo_resultado: 'administrativo',
  oficializado_at: '2026-11-16T12:00:00Z',
  marcador_deportivo: { local: 1, visitante: 0 },
  marcador: {
    local: { nombre: 'Equipo 1', goles: 3 },
    visitante: { nombre: 'Equipo 2', goles: 0 },
  },
  participantes: [
    { rol: 'local', equipo: { nombre: 'Equipo 1' }, goles: 3 },
    { rol: 'visitante', equipo: { nombre: 'Equipo 2' }, goles: 0 },
  ],
}
const llave = {
  id: 1,
  numero: 1,
  estado: 'resuelta',
  ganador_fase_equipo_id: 1,
  plazas: equipos.map((e) => ({ id: e.id, fase_equipo_id: e.id })),
  partidos: [partido],
}
const fase = {
  id: 1,
  nombre: 'Final',
  tipo: 'eliminacion',
  equipos,
  rondas: [
    {
      id: 1,
      numero: 1,
      nombre: 'Final',
      estado: 'finalizada',
      modalidad: 'unico',
      desempate: 'penales',
      llaves: [llave],
    },
  ],
}
const tabla = {
  grupo_id: 1,
  definitiva: true,
  partidos_oficiales: 1,
  partidos_esperados: 1,
  pendientes_resolucion: [],
  tabla: [
    {
      campeonato_equipo_id: 1,
      nombre: 'Equipo 1',
      posicion: 1,
      PJ: 1,
      PG: 1,
      PE: 0,
      PP: 0,
      GF: 3,
      GC: 0,
      DG: 3,
      PTS: 3,
    },
  ],
}
const grupo = {
  id: 2,
  nombre: 'Grupos',
  tipo: 'grupos',
  equipos,
  grupos: [{ id: 1, codigo: 'A', nombre: 'Grupo A' }],
  tablas_oficiales: [tabla],
  tablas_provisionales: [{ ...tabla, definitiva: false }],
  clasificacion: { clasificacion_vigente: false, candidatos: [] },
}

it('reutiliza el cuadro público sin controles ni enlaces administrativos', () => {
  const html = render(<CuadrosPublicos fases={[fase]} />)
  for (const texto of [
    'Final',
    'Equipo 1',
    'Ganador de la final',
    '3 – 0',
    'Resultado administrativo',
    'marcador deportivo: 1',
  ])
    expect(html).toContain(texto)
  for (const texto of [
    '/ligas/',
    '/mis-arbitrajes/',
    'Solicitar avance',
    'Certificar penales',
    'correcciones',
  ])
    expect(html).not.toContain(texto)
})

it('no ofrece avances en una llave pendiente con partidos oficiales', () => {
  const datos = {
    ...fase,
    rondas: [
      {
        ...fase.rondas[0],
        llaves: [{ ...llave, estado: 'pendiente', ganador_fase_equipo_id: null }],
      },
    ],
  }
  const html = render(<CuadrosPublicos fases={[datos]} />)
  expect(html).toContain('Pendiente')
  expect(html).not.toContain('Solicitar avance')
  expect(html).not.toContain('Clasificado:')
})

it('muestra grupos, calendario adicional y clasificación pendiente sin inventar candidatos', () => {
  const html = render(
    <GruposPublicos
      fases={[grupo]}
      partidos={[{ ...partido, tipo_partido: 'desempate' }]}
      vista="oficial"
      onVista={() => {}}
    />,
  )
  for (const texto of [
    'Grupo A',
    'Calendario',
    'Clasificación pendiente',
    'Partido adicional de desempate',
    'Marcador deportivo conservado',
  ])
    expect(html).toContain(texto)
  expect(html).not.toContain('Confirmar clasificación oficial')
  expect(html).not.toContain('Clasificado oficial')
})

it('solo marca clasificados cuando Laravel confirma clasificación vigente', () => {
  const datos = {
    ...grupo,
    clasificacion: {
      clasificacion_vigente: true,
      candidatos: [{ campeonato_equipo_id: 1, origen_grupo_id: 1, origen_puesto: 1 }],
    },
  }
  const html = render(
    <GruposPublicos fases={[datos]} partidos={[]} vista="provisional" onVista={() => {}} />,
  )
  expect(html).toContain('Provisional')
  expect(html).toContain('Clasificación oficial confirmada')
  expect(html).toContain('Clasificado oficial')
})

it('ofrece estados vacíos de grupos y eliminación', () => {
  expect(render(<CuadrosPublicos fases={[]} />)).toContain('Cuadro pendiente')
  expect(render(<GruposPublicos fases={[]} partidos={[]} vista="oficial" />)).toContain(
    'Grupos pendientes',
  )
})

it('muestra tandas de partidos extra con el cuadro existente y sin acciones', () => {
  const datos = {
    ...grupo,
    rondas_desempate: [
      { ...fase.rondas[0], llaves: [{ ...llave, penales_plaza_1: 4, penales_plaza_2: 3 }] },
    ],
  }
  const html = render(
    <GruposPublicos fases={[datos]} partidos={[]} vista="oficial" onVista={() => {}} />,
  )
  expect(html).toContain('Partidos extra y penales')
  expect(html).toContain('Tanda certificada')
  expect(html).not.toContain('Certificar penales')
})

it('el calendario público distingue resultado oficial y deportivo sin motivos privados', () => {
  const html = render(
    <PartidoCard partido={{ ...partido, motivo_administrativo: 'SECRETO' }} publica />,
  )
  expect(html).toContain('Resultado oficial')
  expect(html).toContain('Administrativo')
  expect(html).not.toContain('SECRETO')
})

it('el nuevo contrato usa GET sin cookies y conserva errores de publicación', async () => {
  const original = api.defaults.adapter
  api.defaults.adapter = async (config) => {
    expect(config.url).toBe('/publico/campeonatos/8/deporte')
    expect(config.method).toBe('get')
    expect(config.withCredentials).toBe(false)
    expect(config.withXSRFToken).toBe(false)
    throw { response: { status: 404 } }
  }
  try {
    await expect(publicoService.deporte(8)).rejects.toMatchObject({ response: { status: 404 } })
  } finally {
    api.defaults.adapter = original
  }
})
