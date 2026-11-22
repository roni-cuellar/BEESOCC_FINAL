import { expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import CuadroEliminacion from '../../src/components/CuadroEliminacion'
import { campeonEnCuadro, conexionesCuadro, nombreRondaCuadro } from '../../src/utils/eliminacion'

const render = (elemento) => renderToStaticMarkup(<MemoryRouter>{elemento}</MemoryRouter>)
const equipo = (id, nombre) => ({ id, nombre, escudo: `https://example.test/escudo-${id}.png` })
const equipos = [
  { id: 5, inscripcion: { equipo: equipo(20, 'Águilas') } },
  { id: 6, inscripcion: { equipo: equipo(21, 'Leones') } },
]
const plaza = (id, origen, clasificado) => ({
  id,
  origen_tipo: 'ganador_llave',
  origen_llave_id: origen,
  fase_equipo_id: clasificado,
})
const ronda = (id, llaves) => ({
  id,
  numero: id,
  nombre: `Ronda ${id}`,
  modalidad: 'unico',
  desempate: 'penales',
  estado: 'en_curso',
  llaves,
})
const semifinales = ronda(1, [
  { id: 33, numero: 1, estado: 'resuelta', ganador_fase_equipo_id: 5, plazas: [], partidos: [] },
  { id: 67, numero: 2, estado: 'pendiente', plazas: [], partidos: [] },
])
const final = {
  id: 80,
  numero: 1,
  estado: 'resuelta',
  ganador_fase_equipo_id: 5,
  plazas: [plaza(91, 67, 6), plaza(92, 33, 5)],
  partidos: [],
  marcador_global: { plaza_1: 2, plaza_2: 2 },
  penales_plaza_1: 3,
  penales_plaza_2: 4,
}
const fase = { equipos, rondas: [semifinales, ronda(2, [final])] }

it('conecta por origen real de plaza y distingue avance confirmado de plaza pendiente', () => {
  expect(conexionesCuadro(fase.rondas)).toEqual([
    { origen: 67, destino: 80, plaza: 91, confirmada: false },
    { origen: 33, destino: 80, plaza: 92, confirmada: true },
  ])
  expect(nombreRondaCuadro(semifinales, 1)).toBe('Semifinales')
  expect(nombreRondaCuadro(ronda(2, [final]), 0)).toBe('Final')
  expect(nombreRondaCuadro(ronda(2, [final]), 0, false)).toBe('Ronda 2')
})

it('muestra los escudos y el global de Laravel, y solo destaca un campeón confirmado', () => {
  const campeon = equipos[0].inscripcion.equipo
  const pendiente = render(<CuadroEliminacion fase={fase} publica />)
  expect(pendiente).toContain('Ganador de la final')
  expect(pendiente).not.toContain('Campeón confirmado')
  expect(campeonEnCuadro(fase, equipos[1].inscripcion.equipo)).toBe(false)
  const html = render(<CuadroEliminacion fase={fase} publica campeon={campeon} />)
  for (const texto of [
    campeon.escudo,
    'Campeón confirmado',
    'Global oficial',
    '2 – 2',
    '3 – 4',
    'Anterior',
    'Siguiente',
  ]) {
    expect(html).toContain(texto)
  }
  expect(html).not.toContain('Solicitar avance')
})
