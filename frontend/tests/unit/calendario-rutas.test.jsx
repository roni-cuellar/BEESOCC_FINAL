import { expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import AppRoutes from '../../src/routes/AppRoutes'

vi.mock('../../src/stores/useAuthStore', () => ({
  default: () => ({ usuario: { name: 'Organizador' }, verificado: true }),
}))

vi.mock('../../src/stores/useLigaStore', () => ({
  default: () => ({ liga: { id: 2, nombre: 'Copa', formato: 'grupos_eliminacion' } }),
}))

it.each([
  ['calendario', 'Calendario', 'Calendario'],
  ['grupos', 'Grupos y eliminación', 'Cada grupo, un camino al título.'],
])('la ruta %s muestra su vista y marca su enlace lateral', (ruta, enlace, titulo) => {
  const html = renderToStaticMarkup(
    <MemoryRouter initialEntries={[`/ligas/2/${ruta}`]}>
      <AppRoutes />
    </MemoryRouter>,
  )
  expect(html).toContain(`<h1>${titulo}</h1>`)
  expect(html).toMatch(
    new RegExp(`aria-current="page"[^>]*href="/ligas/2/${ruta}"[^>]*>.*?</svg>${enlace}</a>`),
  )
})
