import { afterEach, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import api from '../../src/services/api.js'
import liga from '../../src/services/ligaService.js'
import Ciclo from '../../src/components/CicloCampeonatoPanel.jsx'

const adaptador = api.defaults.adapter
afterEach(() => { api.defaults.adapter = adaptador })

it('envía configuración explícita y usa las transiciones privadas existentes', async () => {
  const envios = []
  api.defaults.adapter = async config => {
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
    if (estado === 'finalizado') expect(html).toContain('no se permiten modificaciones deportivas ordinarias')
    if (estado === 'planificacion') expect(html).toContain('No se asigna un número por modalidad automáticamente')
  })
}
