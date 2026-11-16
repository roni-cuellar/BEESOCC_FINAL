import { afterEach, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import api, { mensajeError } from '../../src/services/api'
import arbitroService from '../../src/services/arbitroService'
import { HistorialArbitros } from '../../src/pages/ArbitrosPage'
import { ListaArbitrajes } from '../../src/pages/MisArbitrajesPage'
import AppRoutes from '../../src/routes/AppRoutes'
import useAuthStore from '../../src/stores/useAuthStore'

const original = api.defaults.adapter
afterEach(() => {
  api.defaults.adapter = original
  useAuthStore.setState({ usuario: null, verificado: false })
})
it('consulta y administra árbitros con rutas reales, cookies y cuerpo DELETE', async () => {
  const recibidas = []
  api.defaults.adapter = async (config) => {
    expect(config.withCredentials).toBe(true)
    expect(config.withXSRFToken).toBe(true)
    recibidas.push([config.method, config.url, config.data && JSON.parse(config.data)])
    return {
      data: { asignacion: { id: 1 }, asignaciones: [], usuario: { id: 7, name: 'Ana' } },
      status: 200,
      headers: {},
      config,
    }
  }
  await arbitroService.vigente(4)
  await arbitroService.historial(4)
  expect(await arbitroService.buscarUsuario(4, 'ana@example.test')).toEqual({ id: 7, name: 'Ana' })
  await arbitroService.asignar(4, 7)
  await arbitroService.reemplazar(4, 8, 'Cambio')
  await arbitroService.revocar(4, 'Fin')
  await arbitroService.misArbitrajes()
  expect(recibidas).toEqual([
    ['get', '/campeonatos/4/arbitro', undefined],
    ['get', '/campeonatos/4/arbitro/historial', undefined],
    ['post', '/campeonatos/4/arbitro/buscar-usuario', { email: 'ana@example.test' }],
    ['post', '/campeonatos/4/arbitro', { usuario_id: 7 }],
    ['patch', '/campeonatos/4/arbitro', { usuario_id: 8, motivo_revocacion: 'Cambio' }],
    ['delete', '/campeonatos/4/arbitro', { motivo_revocacion: 'Fin' }],
    ['get', '/mis-arbitrajes', undefined],
  ])
})
it.each([401, 403, 404, 409, 422, 429])(
  'propaga el error %s y lo presenta de forma comprensible',
  async (status) => {
    const error = {
      response: {
        status,
        data: {
          message: 'Operación rechazada.',
          errors: { usuario_id: ['Selecciona un usuario válido.'] },
        },
      },
    }
    api.defaults.adapter = async () => {
      throw error
    }
    await expect(arbitroService.asignar(1, 2)).rejects.toBe(error)
    expect(mensajeError(error)).toBeTruthy()
  },
)
it('historial distingue vigente y revocado y muestra el motivo sin datos personales adicionales', () => {
  const fila = {
    id: 1,
    usuario: { name: 'Ana', email: 'privado@example.test' },
    autorizador: { name: 'Dueño' },
    fecha_inicio: '2026-11-12',
    fecha_fin: null,
  }
  const html = renderToStaticMarkup(
    <HistorialArbitros
      asignaciones={[
        fila,
        { ...fila, id: 2, fecha_fin: '2026-11-13', motivo_revocacion: 'Disponibilidad' },
      ]}
    />,
  )
  expect(html).toContain('Vigente')
  expect(html).toContain('Revocada')
  expect(html).toContain('Disponibilidad')
  expect(html).not.toContain('privado@example.test')
})
it('mis arbitrajes no enlaza al dashboard administrativo ni muestra campos privados', () => {
  const html = renderToStaticMarkup(
    <MemoryRouter>
      <ListaArbitrajes
        asignaciones={[
          {
            id: 1,
            fecha_inicio: '2026-11-12',
            campeonato: {
              id: 4,
              nombre: 'Copa',
              temporada: '2026',
              estado: 'planificacion',
              administrador_id: 'dato-privado',
            },
          },
        ]}
      />
    </MemoryRouter>,
  )
  expect(html).toContain('Copa')
  expect(html).not.toContain('dato-privado')
  expect(html).toContain('href="/mis-arbitrajes/4/partidos"')
  expect(html).not.toContain('/dashboard')
})
it.each(['/mis-arbitrajes', '/ligas/4/arbitros'])(
  'la ruta %s espera la comprobación de sesión',
  (ruta) => {
    useAuthStore.setState({ usuario: null, verificado: false })
    const html = renderToStaticMarkup(
      <MemoryRouter initialEntries={[ruta]}>
        <AppRoutes />
      </MemoryRouter>,
    )
    expect(html).toContain('Cargando información')
    expect(html).not.toContain('Correo exacto')
  },
)
