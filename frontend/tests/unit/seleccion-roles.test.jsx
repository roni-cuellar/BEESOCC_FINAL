import { beforeEach, expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import AppRoutes from '../../src/routes/AppRoutes'
import useAuthStore from '../../src/stores/useAuthStore'

vi.mock('../../src/stores/useAuthStore', () => ({ default: vi.fn() }))

function vista(ruta, state) {
  return renderToStaticMarkup(
    <MemoryRouter initialEntries={[{ pathname: ruta, state }]}>
      <AppRoutes />
    </MemoryRouter>,
  )
}

beforeEach(() => {
  useAuthStore.mockReturnValue({ usuario: { id: 1 }, verificado: true })
})

it('ofrece los tres destinos desde la bienvenida privada', () => {
  const html = vista('/seleccion-roles')
  expect(html).toContain('Bienvenido a BEESOCC')
  for (const [rol, ruta] of [
    ['Administrador', '/mis-ligas'],
    ['Árbitro', '/mis-arbitrajes'],
    ['Espectador', '/campeonatos'],
  ]) {
    expect(html).toMatch(new RegExp(`<h2[^>]*>${rol}</h2>`))
    expect(html).toContain(`href="${ruta}"`)
  }
})

it.each(['/mis-ligas', '/mis-arbitrajes', '/campeonatos'])(
  'permite cambiar de rol desde %s',
  (ruta) => {
    expect(vista(ruta)).toMatch(/href="\/seleccion-roles"[^>]*>Cambiar rol<\/a>/)
  },
)

it('reserva la bienvenida a usuarios con sesión y conserva el catálogo público', () => {
  useAuthStore.mockReturnValue({ usuario: null, verificado: true })
  expect(vista('/seleccion-roles')).toBe('')
  expect(vista('/campeonatos')).toContain('Campeonatos publicados')
  expect(vista('/campeonatos')).not.toContain('Cambiar rol')
})

it('conserva el enlace privado solicitado con sus parámetros y ancla', () => {
  const desde = '/ligas/12/calendario?jornada=2#configuracion'
  expect(vista('/seleccion-roles', { desde })).toContain(`href="${desde}"`)
  expect(vista('/seleccion-roles', { desde: '//externo.test' })).not.toContain(
    'Continuar a la página solicitada',
  )
})
