import { expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import LigaCard from '../../src/components/LigaCard'
import HeaderDashboard from '../../src/components/HeaderDashboard'
import CrearLigaPage from '../../src/pages/CrearLigaPage'

const liga = {
  id: 3,
  nombre: 'Liga ficticia',
  estado: 'planificacion',
  formato: 'grupos_eliminacion',
  logo: 'https://example.test/logo.png',
}

function vista(elemento) {
  return renderToStaticMarkup(<MemoryRouter>{elemento}</MemoryRouter>)
}

it.each([false, true])(
  'la tarjeta pública=%s utiliza el logo o la ilustración oficial',
  (publica) => {
    const conLogo = vista(<LigaCard liga={liga} publica={publica} />)
    expect(conLogo).toContain(liga.logo)
    expect(conLogo).not.toContain('/campeonatos.png')
    const porDefecto = vista(<LigaCard liga={{ ...liga, logo: null }} publica={publica} />)
    expect(porDefecto).toContain('/campeonatos.png')
  },
)

it('el encabezado muestra el logo y conserva el acceso a Configuración de grupos', () => {
  const html = vista(<HeaderDashboard liga={liga} ligaId="3" />)
  expect(html).toContain(liga.logo)
  expect(html).toContain('href="/ligas/3/grupos"')
})

it('ofrece la carga opcional y el respaldo desde el formulario de creación', () => {
  const html = vista(
    <Routes>
      <Route path="/" element={<CrearLigaPage />} />
    </Routes>,
  )
  expect(html).toContain('Logo de la liga (opcional)')
  expect(html).toContain('accept="image/jpeg,image/png,image/webp"')
  expect(html).toContain('/campeonatos.png')
})
