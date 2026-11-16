import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import api, { mensajeError } from '../../src/services/api'
import ligaService from '../../src/services/ligaService'
import publicoService from '../../src/services/publicoService'
import useLigaStore from '../../src/stores/useLigaStore'
import useAuthStore from '../../src/stores/useAuthStore'
import LigaCard from '../../src/components/LigaCard'
import { ResumenPublico } from '../../src/pages/DetallePublicoPage'
import AppRoutes from '../../src/routes/AppRoutes'

const liga = {
  id: 8,
  nombre: 'Copa SENA',
  temporada: '2026',
  formato: 'todos_contra_todos',
  estado: 'planificacion',
  publicado: false,
}
const render = (elemento) => renderToStaticMarkup(<MemoryRouter>{elemento}</MemoryRouter>)

beforeEach(() => {
  useLigaStore.setState({ ligas: [liga], liga, publicando: false })
  useAuthStore.setState({ usuario: null, verificado: false, cargando: false, error: '' })
})

describe('publicación desde React', () => {
  it('envía publicado como booleano al endpoint privado usando cookies', async () => {
    const original = api.defaults.adapter
    const enviados = []
    api.defaults.adapter = async (config) => {
      expect(config.method).toBe('patch')
      expect(config.url).toBe('/campeonatos/8/publicacion')
      expect(config.withCredentials).toBe(true)
      expect(config.withXSRFToken).toBe(true)
      const datos = JSON.parse(config.data)
      expect(typeof datos.publicado).toBe('boolean')
      enviados.push(datos.publicado)
      return {
        data: { campeonato: { ...liga, publicado: datos.publicado } },
        status: 200,
        headers: {},
        config,
      }
    }
    try {
      expect((await ligaService.publicacion(8, true)).publicado).toBe(true)
      expect((await ligaService.publicacion(8, false)).publicado).toBe(false)
      expect(enviados).toEqual([true, false])
    } finally {
      api.defaults.adapter = original
    }
  })

  it('actualiza listado y detalle con el estado confirmado por Laravel', async () => {
    vi.spyOn(ligaService, 'publicacion')
      .mockResolvedValueOnce({ ...liga, publicado: true })
      .mockResolvedValueOnce(liga)
    await useLigaStore.getState().cambiarPublicacion(8, true)
    expect(useLigaStore.getState().ligas[0].publicado).toBe(true)
    expect(useLigaStore.getState().liga.publicado).toBe(true)
    await useLigaStore.getState().cambiarPublicacion(8, false)
    expect(useLigaStore.getState().ligas[0].publicado).toBe(false)
    expect(useLigaStore.getState().publicando).toBe(false)
  })

  it('bloquea solicitudes duplicadas mientras guarda la publicación', async () => {
    let resolver
    const guardar = vi.spyOn(ligaService, 'publicacion').mockImplementation(
      () =>
        new Promise((resolve) => {
          resolver = resolve
        }),
    )
    const primera = useLigaStore.getState().cambiarPublicacion(8, true)
    await expect(useLigaStore.getState().cambiarPublicacion(8, true)).rejects.toThrow('en curso')
    resolver({ ...liga, publicado: true })
    await primera
    expect(guardar).toHaveBeenCalledTimes(1)
  })

  it.each([401, 403, 404, 422])(
    'un error %s conserva el estado y permite reintentar',
    async (status) => {
      const error = {
        response: {
          status,
          data: status === 422 ? { errors: { publicado: ['Indica un estado válido.'] } } : {},
        },
      }
      vi.spyOn(ligaService, 'publicacion').mockRejectedValue(error)
      await expect(useLigaStore.getState().cambiarPublicacion(8, true)).rejects.toBe(error)
      expect(useLigaStore.getState().ligas[0].publicado).toBe(false)
      expect(useLigaStore.getState().publicando).toBe(false)
      expect(mensajeError(error)).toBe(
        {
          401: 'Tu sesión terminó. Inicia sesión de nuevo.',
          403: 'No tienes permiso para realizar esta operación.',
          404: 'No encontramos este registro.',
          422: 'Indica un estado válido.',
        }[status],
      )
    },
  )

  it('la tarjeta privada muestra estado y acción correspondiente sin cambiar la administración', () => {
    const privada = render(<LigaCard liga={liga} onEliminar={() => {}} onPublicacion={() => {}} />)
    expect(privada).toContain('Privado')
    expect(privada).toContain('aria-label="Publicar Copa SENA"')
    expect(privada).toContain('/ligas/8/dashboard')
    const publicada = render(
      <LigaCard liga={{ ...liga, publicado: true }} onPublicacion={() => {}} publicando />,
    )
    expect(publicada).toContain('Publicado')
    expect(publicada).toContain('aria-label="Ocultar Copa SENA"')
    expect(publicada).toContain('disabled=""')
  })

  it('la tarjeta pública ofrece consulta sin controles administrativos ni datos internos', () => {
    const html = render(
      <LigaCard
        liga={{
          ...liga,
          administrador_id: 'DUENO_PRIVADO',
          fecha_nacimiento: 'FECHA_PRIVADA',
          email: 'CORREO_PRIVADO',
        }}
        publica
      />,
    )
    expect(html).toContain('href="/campeonatos/8"')
    expect(html).toContain('Ver campeonato')
    for (const texto of [
      'Administrar',
      'Editar liga',
      'Eliminar',
      'Ocultar',
      'DUENO_PRIVADO',
      'FECHA_PRIVADA',
      'CORREO_PRIVADO',
    ])
      expect(html).not.toContain(texto)
  })

  it('el resumen público muestra solo campos deportivos y marcador público', () => {
    const equipo = { id: 2, nombre: 'Abejas', administrador_id: 'SECRETO_DUENO' }
    const html = render(
      <ResumenPublico
        equipos={[{ inscripcion_id: 1, equipo, email: 'SECRETO_EMAIL' }]}
        partidos={[
          {
            id: 9,
            jornada: 1,
            estado: 'en_curso',
            fecha_nacimiento: 'SECRETO_FECHA',
            participantes: [
              { rol: 'local', equipo, goles: 2 },
              { rol: 'visitante', equipo: { nombre: 'Rivales' }, goles: 1 },
            ],
          },
        ]}
      />,
    )
    expect(html).toContain('Abejas')
    expect(html).toContain('2 – 1')
    expect(html).not.toContain('/ligas/')
    expect(html).not.toContain('SECRETO_')
  })

  it('los servicios públicos usan solo GET públicos y no solicitan cookies ni CSRF', async () => {
    const original = api.defaults.adapter
    const rutas = []
    api.defaults.adapter = async (config) => {
      expect(config.method).toBe('get')
      expect(config.withCredentials).toBe(false)
      expect(config.withXSRFToken).toBe(false)
      rutas.push(config.url)
      return { data: [], status: 200, headers: {}, config }
    }
    try {
      await publicoService.listar()
      await publicoService.obtener(8)
      await publicoService.equipos(8)
      await publicoService.partidos(8)
      await publicoService.tabla(8)
      await publicoService.estadisticas(8, 'goleadores')
      expect(rutas).toEqual([
        '/publico/campeonatos',
        '/publico/campeonatos/8',
        '/publico/campeonatos/8/equipos',
        '/publico/campeonatos/8/partidos',
        '/publico/campeonatos/8/tabla',
        '/publico/campeonatos/8/estadisticas/goleadores',
      ])
    } finally {
      api.defaults.adapter = original
    }
  })

  it('un privado recibe 404 en el contrato público sin recurrir a la API administrativa', async () => {
    const original = api.defaults.adapter
    api.defaults.adapter = async (config) => {
      expect(config.url).toBe('/publico/campeonatos/8')
      throw { response: { status: 404 }, config }
    }
    try {
      await expect(publicoService.obtener(8)).rejects.toMatchObject({ response: { status: 404 } })
    } finally {
      api.defaults.adapter = original
    }
  })

  it('el listado de espectadores es una ruta accesible sin usuario', () => {
    const html = renderToStaticMarkup(
      <MemoryRouter initialEntries={['/campeonatos']}>
        <AppRoutes />
      </MemoryRouter>,
    )
    expect(html).toContain('Campeonatos publicados')
    expect(html).toContain('Sigue la competición sin crear una cuenta.')
    expect(useAuthStore.getState().usuario).toBeNull()
  })
})
