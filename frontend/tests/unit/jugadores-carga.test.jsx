import { expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import JugadoresPage from '../../src/pages/JugadoresPage'
import useConsultaPublica from '../../src/hooks/useConsultaPublica'

vi.mock('../../src/hooks/useConsultaPublica')

function renderizar(catalogo, plantilla) {
  useConsultaPublica.mockReset()
  useConsultaPublica
    .mockReturnValueOnce({
      datos: [{ id: 5, estado: 'activo', equipo: { nombre: 'Equipo de prueba' } }],
    })
    .mockReturnValueOnce(catalogo)
    .mockReturnValueOnce(plantilla)
  return renderToStaticMarkup(
    <MemoryRouter initialEntries={['/ligas/2/jugadores']}>
      <Routes>
        <Route path="/ligas/:ligaId/jugadores" element={<JugadoresPage />} />
      </Routes>
    </MemoryRouter>,
  )
}

it.each([{ cargando: true }, { error: 'El catálogo no responde' }])(
  'muestra la plantilla aunque el catálogo global no esté disponible (%j)',
  (catalogo) => {
    const html = renderizar(catalogo, {
      datos: [
        {
          id: 1,
          jugador: { nombre: 'Ana', apellido: 'Pérez' },
          fecha_ingreso: '2026-01-01',
        },
      ],
    })
    expect(html).toContain('Ana Pérez')
    expect(html).not.toContain('Cargando información')
    expect(html).not.toContain('El catálogo no responde')
  },
)

it('ofrece reintentar cuando falla la consulta de la plantilla seleccionada', () => {
  const html = renderizar(
    { cargando: true },
    { error: 'La plantilla no responde', reintentar: vi.fn() },
  )
  expect(html).toContain('La plantilla no responde')
  expect(html).toContain('Volver a intentar')
})
