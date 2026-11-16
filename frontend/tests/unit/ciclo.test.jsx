import { afterEach, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import api from '../../src/services/api.js'
import liga from '../../src/services/ligaService.js'
import Ciclo from '../../src/components/CicloCampeonatoPanel.jsx'
import useLigaStore from '../../src/stores/useLigaStore.js'

const adaptador = api.defaults.adapter
afterEach(() => {
  api.defaults.adapter = adaptador
})

it('refresca un conflicto sin desmontar el panel ni aceptar una respuesta tardía', async () => {
  const anterior = { id: 12, estado: 'planificacion' }
  useLigaStore.setState({ liga: anterior, cargando: false, error: '', solicitada: '12' })
  let resolver
  api.defaults.adapter = (config) =>
    new Promise((resolve) => {
      resolver = (datos) => resolve({ data: datos, status: 200, headers: {}, config })
    })
  const pendiente = useLigaStore.getState().cargarLiga(12, { conservar: true })
  expect(useLigaStore.getState().liga).toBe(anterior)
  expect(useLigaStore.getState().cargando).toBe(false)
  resolver({ id: 12, estado: 'en_curso' })
  await pendiente
  expect(useLigaStore.getState().liga.estado).toBe('en_curso')
  const vieja = useLigaStore.getState().cargarLiga(12, { conservar: true })
  const resolverVieja = resolver
  const reciente = useLigaStore.getState().cargarLiga(14)
  resolver({ id: 14, estado: 'planificacion' })
  await reciente
  resolverVieja({ id: 12, estado: 'finalizado' })
  await vieja
  expect(useLigaStore.getState().liga.id).toBe(14)
})

it('envía configuración explícita y usa las transiciones privadas existentes', async () => {
  const envios = []
  api.defaults.adapter = async (config) => {
    envios.push([config.method, config.url, config.data ? JSON.parse(config.data) : null])
    return { data: {}, status: 200, headers: {}, config }
  }
  await liga.requisitosInicio(12, { modalidad: 'Ficticia', minimo_jugadores: 3 })
  await liga.iniciar(12)
  await liga.finalizar(12)
  await liga.auditoriaCiclo(12)
  expect(envios).toEqual([
    ['patch', '/campeonatos/12/requisitos-inicio', { modalidad: 'Ficticia', minimo_jugadores: 3 }],
    ['post', '/campeonatos/12/iniciar', null],
    ['post', '/campeonatos/12/finalizar', null],
    ['get', '/campeonatos/12/auditoria-ciclo', null],
  ])
})

for (const estado of ['planificacion', 'en_curso', 'finalizado']) {
  it(`muestra únicamente la acción correspondiente a ${estado}`, () => {
    const html = renderToStaticMarkup(<Ciclo liga={{ id: 12, estado }} />)
    expect(html.includes('Iniciar campeonato')).toBe(estado === 'planificacion')
    expect(html.includes('Finalizar campeonato')).toBe(estado === 'en_curso')
    expect(html).toContain('Consultar auditoría del ciclo')
    if (estado === 'finalizado')
      expect(html).toContain('no se permiten modificaciones deportivas ordinarias')
    if (estado === 'planificacion')
      expect(html).toContain('No se asigna un número por modalidad automáticamente')
  })
}
