import { expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import IlustracionModulo from '../../src/components/IlustracionModulo'
import EncabezadoPagina from '../../src/components/layout/EncabezadoPagina'
import Navbar from '../../src/components/Navbar'

it.each([
  ['campeonatos', 'campeonatos.png', 384],
  ['equipos', 'equipos.png', 512],
  ['partidos', 'partidos.png', 512],
  ['estadisticas', 'estadisticas.png', 512],
  ['arbitraje', 'arbitraje-actas.png', 256],
  ['grupos', 'fase-grupos.png', 256],
  ['eliminacion', 'eliminacion-directa.png', 256],
  ['desempates', 'penales-desempates.png', 256],
  ['plantillas', 'plantillas.png', 256],
  ['publico', 'consulta-publica.png', 256],
])('ilustración de %s usa su recurso y mantiene un marco cuadrado', (tipo, archivo, lado) => {
  const html = renderToStaticMarkup(<IlustracionModulo tipo={tipo} />)
  expect(html).toContain('/branding/optimizadas/' + archivo)
  expect(html).toContain(`width="${lado}"`)
  expect(html).toContain(`height="${lado}"`)
  expect(html).toContain('object-contain')
  expect(html).toContain('loading="lazy"')
  expect(html).toContain('alt=""')
})

it('un tipo desconocido no muestra una imagen que no corresponda al módulo', () => {
  expect(renderToStaticMarkup(<IlustracionModulo tipo="desconocido" />)).toBe('')
})

it('el encabezado conserva título, descripción y acciones con o sin ilustración', () => {
  for (const ilustracion of [undefined, 'grupos']) {
    const html = renderToStaticMarkup(
      <EncabezadoPagina
        titulo="Mis grupos"
        descripcion="Resultados confirmados"
        acciones={<button disabled>Actualizar</button>}
        ilustracion={ilustracion}
      />,
    )
    expect(html).toContain('Mis grupos')
    expect(html).toContain('Resultados confirmados')
    expect(html).toContain('disabled=""')
    expect(html.includes('<img')).toBe(Boolean(ilustracion))
  }
})

it('la marca original se conserva mientras los logos nuevos tengan diferencias pendientes', () => {
  const html = renderToStaticMarkup(
    <MemoryRouter>
      <Navbar />
    </MemoryRouter>,
  )
  expect(html).toContain('/branding/logo.png')
  expect(html).not.toContain('/branding/nuevos/')
})
