import { expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import LigaCard from '../../src/components/LigaCard'
import CampeonatosPublicosPage from '../../src/pages/CampeonatosPublicosPage'
import api from '../../src/services/api'
import publicoService from '../../src/services/publicoService'

const liga = {
  id: 12,
  nombre: 'Copa de prueba',
  temporada: '2026',
  estado: 'planificacion',
  codigo_liga: 'F7RK28WBQ5',
}

it('muestra el código y su botón solo en la tarjeta de gestión', () => {
  const tarjeta = (publica) =>
    renderToStaticMarkup(
      <MemoryRouter>
        <LigaCard liga={liga} publica={publica} />
      </MemoryRouter>,
    )
  expect(tarjeta(false)).toContain(liga.codigo_liga)
  expect(tarjeta(false)).toContain('Copiar código de Copa de prueba')
  expect(tarjeta(false)).toContain('/ligas/12/dashboard')
  expect(tarjeta(true)).not.toContain(liga.codigo_liga)
  expect(tarjeta(true)).not.toContain('Copiar')
  expect(tarjeta(true)).toContain('/campeonatos/12')
})

it('añade la búsqueda sin quitar el encabezado del catálogo público', () => {
  const html = renderToStaticMarkup(
    <MemoryRouter>
      <CampeonatosPublicosPage />
    </MemoryRouter>,
  )
  expect(html).toContain('Campeonatos publicados')
  expect(html).toContain('Código de liga')
  expect(html).toContain('Buscar liga')
})

it('consulta por código con el contrato público de lectura', async () => {
  const peticion = vi.spyOn(api, 'get').mockResolvedValue({ data: { id: liga.id } })
  try {
    const { signal } = new AbortController()
    expect(await publicoService.porCodigo(liga.codigo_liga, signal)).toEqual({ id: liga.id })
    expect(peticion).toHaveBeenCalledWith('/publico/campeonatos/por-codigo', {
      params: { codigo: liga.codigo_liga },
      withCredentials: false,
      withXSRFToken: false,
      signal,
    })
  } finally {
    peticion.mockRestore()
  }
})
