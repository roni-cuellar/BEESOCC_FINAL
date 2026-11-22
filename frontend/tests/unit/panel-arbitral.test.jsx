import { expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { TitularesArbitrales, RelojArbitral } from '../../src/components/PanelArbitral'
import EventoForm from '../../src/components/EventoForm'
import { segundosDelReloj, tiempoVisible } from '../../src/utils/relojArbitral'

const partido = {
  estado: 'programado',
  participantes: ['local', 'visitante'].map((rol, i) => ({
    id: i + 1,
    rol,
    campeonato_equipo: {
      equipo: {
        nombre: rol === 'local' ? 'Águilas' : 'Leones',
        escudo: 'https://example.test/escudo.png',
      },
    },
  })),
}
const panel = {
  recibidoEn: 100,
  segundos: 70,
  duracion_minutos: 2,
  en_marcha: false,
  limites: { minimo: 1, maximo: 5 },
  campeonato_en_curso: true,
  titulares: { local: [7], visitante: [] },
  elegibles: {
    local: [
      {
        id: 7,
        dorsal: 10,
        posicion: 'Delantero',
        jugador: { nombre: 'Ana', apellido: 'Díaz', foto: 'https://example.test/foto.png' },
      },
    ],
    visitante: [],
  },
}

it('pinta el tiempo desde una muestra del servidor, descontando pausas y sin superar la duración', () => {
  expect(segundosDelReloj(panel, 150000)).toBe(70)
  expect(segundosDelReloj({ ...panel, en_marcha: true }, 2100)).toBe(72)
  expect(segundosDelReloj({ ...panel, en_marcha: true }, 150000)).toBe(120)
  expect(tiempoVisible(70)).toBe('01:10')
})

it('restaura los titulares guardados con su fotografía, dorsal y posición', () => {
  const html = renderToStaticMarkup(<TitularesArbitrales partido={partido} panel={panel} />)
  for (const contenido of [
    'foto.png',
    'Ana Díaz',
    '#10',
    'Delantero',
    'checked=""',
    'máximo 5',
    'value="2"',
  ]) {
    expect(html).toContain(contenido)
  }
})

it('muestra el marcador y una pausa del reloj independiente del estado del partido', () => {
  const html = renderToStaticMarkup(
    <RelojArbitral
      partido={{ ...partido, estado: 'en_curso' }}
      marcador={{ local: { goles: 2 }, visitante: { goles: 1 } }}
      panel={panel}
    />,
  )
  for (const contenido of ['2 – 1', 'escudo.png', '01:10', 'en curso', 'Reanudar reloj'])
    expect(html).toContain(contenido)
})

it('solo el formulario con reloj activo oculta la edición del minuto', () => {
  const props = { partido, plantillas: {}, eventos: [], onGuardar: vi.fn() }
  expect(renderToStaticMarkup(<EventoForm {...props} />)).toContain('name="minuto"')
  const automatico = renderToStaticMarkup(<EventoForm {...props} minutoAutomatico />)
  expect(automatico).not.toContain('name="minuto"')
  expect(automatico).toContain('cronómetro oficial')
})
