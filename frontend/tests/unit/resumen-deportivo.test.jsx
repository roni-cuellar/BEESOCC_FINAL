import { expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import ResumenDeportivo from '../../src/components/ResumenDeportivo'

it('presenta las cuatro cifras recibidas y conserva los ceros reales', () => {
  const html = renderToStaticMarkup(
    <ResumenDeportivo equipos={8} jugadores={56} partidos={0} arbitros={1} />,
  )
  for (const titulo of ['Equipos inscritos', 'Jugadores', 'Partidos', 'Árbitros asignados'])
    expect(html).toContain(titulo)
  for (const valor of [8, 56, 0, 1]) expect(html).toContain(`>${valor}</p>`)
  expect(html).not.toContain('No disponible')
})

it('no presenta un fallo de consulta como un cero deportivo', () => {
  const html = renderToStaticMarkup(
    <ResumenDeportivo equipos={2} jugadores={null} partidos={4} arbitros={null} cargando={false} />,
  )
  expect(html.match(/>—<\/p>/g)).toHaveLength(2)
  expect(html.match(/No disponible/g)).toHaveLength(2)
  expect(html).not.toContain('>0</p>')
})

it('diferencia una cifra pendiente de un resultado consultado', () => {
  const html = renderToStaticMarkup(
    <ResumenDeportivo equipos={0} jugadores={null} partidos={null} arbitros={null} cargando />,
  )
  expect(html.match(/Consultando…/g)).toHaveLength(3)
  expect(html).toContain('>0</p>')
  expect(html).not.toContain('No disponible')
})
