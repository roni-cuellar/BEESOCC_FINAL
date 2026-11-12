import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import api, { mensajeError } from '../../src/services/api'
import authService from '../../src/services/authService'
import useAuthStore from '../../src/stores/useAuthStore'
import RutaPrivada from '../../src/components/RutaPrivada'

beforeEach(() => useAuthStore.setState({ usuario: null, verificado: false, cargando: false, error: '' }))

describe('sesiones Sanctum', () => {
  it('usa cookies y pide CSRF antes de registrar o iniciar sesión', async () => {
    expect(api.defaults.withCredentials).toBe(true)
    expect(api.defaults.withXSRFToken).toBe(true)
    const llamadas = []
    const adaptador = api.defaults.adapter
    api.defaults.adapter = async config => {
      llamadas.push([config.method, config.url, config.baseURL])
      return { data: { usuario: { id: 4 } }, status: 200, headers: {}, config }
    }
    try {
      expect(await authService.registrar({ name: 'SENA' })).toEqual({ id: 4 })
      await authService.login({ email: 'prueba@example.test', password: 'solo prueba' })
      await authService.usuario()
      await authService.logout()
      expect(llamadas.map(c => c[1])).toEqual(['/sanctum/csrf-cookie', '/registro', '/sanctum/csrf-cookie', '/login', '/usuario', '/sanctum/csrf-cookie', '/logout'])
      expect(llamadas[0][2]).toBe('')
    } finally { api.defaults.adapter = adaptador }
  })

  it('recupera una sesión y evita consultas duplicadas durante la carga', async () => {
    let resolver
    const consulta = vi.spyOn(authService, 'usuario').mockImplementation(() => new Promise(resolve => { resolver = resolve }))
    const primera = useAuthStore.getState().verificar()
    const segunda = useAuthStore.getState().verificar()
    expect(consulta).toHaveBeenCalledTimes(1)
    expect(useAuthStore.getState().cargando).toBe(true)
    resolver({ id: 7 })
    await Promise.all([primera, segunda])
    expect(useAuthStore.getState()).toMatchObject({ usuario: { id: 7 }, verificado: true, cargando: false })
  })

  it('un 401 recuperando sesión se interpreta como invitado', async () => {
    vi.spyOn(authService, 'usuario').mockRejectedValue({ response: { status: 401 } })
    await useAuthStore.getState().verificar()
    expect(useAuthStore.getState()).toMatchObject({ usuario: null, verificado: true, error: '' })
  })

  it('un fallo de red permite reintentar sin dar la sesión por verificada', async () => {
    vi.spyOn(authService, 'usuario').mockRejectedValue(new Error('red'))
    await useAuthStore.getState().verificar()
    expect(useAuthStore.getState().verificado).toBe(false)
    expect(useAuthStore.getState().error).toContain('No se pudo conectar')
  })

  it('rechaza credenciales y evita envíos duplicados', async () => {
    let rechazar
    const login = vi.spyOn(authService, 'login').mockImplementation(() => new Promise((_, reject) => { rechazar = reject }))
    const primera = useAuthStore.getState().autenticar('login', {})
    expect(await useAuthStore.getState().autenticar('login', {})).toBe(false)
    rechazar({ response: { status: 422, data: { errors: { email: ['Credenciales incorrectas.'] } } } })
    expect(await primera).toBe(false)
    expect(login).toHaveBeenCalledTimes(1)
    expect(useAuthStore.getState().error).toBe('Credenciales incorrectas.')
  })

  it('login y logout actualizan el usuario sin persistencia manual', async () => {
    vi.spyOn(authService, 'login').mockResolvedValue({ id: 9 })
    vi.spyOn(authService, 'logout').mockResolvedValue()
    expect(await useAuthStore.getState().autenticar('login', {})).toBe(true)
    expect(useAuthStore.getState().usuario.id).toBe(9)
    expect(await useAuthStore.getState().salir()).toBe(true)
    expect(useAuthStore.getState().usuario).toBeNull()
  })

  it('no oculta un fallo de logout y explica 401, 419 y 422', async () => {
    useAuthStore.setState({ usuario: { id: 9 } })
    vi.spyOn(authService, 'logout').mockRejectedValue({ response: { status: 419 } })
    expect(await useAuthStore.getState().salir()).toBe(false)
    expect(useAuthStore.getState().usuario.id).toBe(9)
    expect(mensajeError({ response: { status: 401 } })).toContain('Inicia sesión')
    expect(useAuthStore.getState().error).toContain('Intenta de nuevo')
    expect(mensajeError({ response: { status: 422, data: { errors: { name: ['El campo name es obligatorio.'] } } } })).toContain('nombre')
  })

  it('muestra espera mientras verifica y no renderiza contenido privado', () => {
    const html = renderToStaticMarkup(<MemoryRouter><RutaPrivada /></MemoryRouter>)
    expect(html).toContain('role="status"')
    expect(html).toContain('Cargando información')
  })
})
