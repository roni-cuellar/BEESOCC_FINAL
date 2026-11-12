import { create } from 'zustand'
import authService from '../services/authService'
import api, { mensajeError } from '../services/api'

let verificacion
let version = 0
const useAuthStore = create((set, get) => ({
  usuario: null, verificado: false, cargando: false, error: '',
  limpiarError: () => set({ error: '' }),
  verificar: () => {
    if (verificacion) return verificacion
    const actual = version
    set({ cargando: true, error: '' })
    verificacion = authService.usuario()
      .then(usuario => { if (actual === version) set({ usuario, verificado: true }) })
      .catch(error => {
        if (actual === version) set({ usuario: null, verificado: error.response?.status === 401, error: error.response?.status === 401 ? '' : mensajeError(error) })
      })
      .finally(() => { if (actual === version) set({ cargando: false }); verificacion = null })
    return verificacion
  },
  autenticar: async (accion, datos) => {
    if (get().cargando) return false
    version++
    set({ cargando: true, error: '' })
    try {
      const usuario = await authService[accion](datos)
      set({ usuario, verificado: true })
      return true
    } catch (error) { set({ error: mensajeError(error) }); return false }
    finally { set({ cargando: false }) }
  },
  salir: async () => {
    if (get().cargando) return false
    version++
    set({ cargando: true, error: '' })
    try {
      await authService.logout()
      set({ usuario: null, verificado: true })
      return true
    } catch (error) {
      if (error.response?.status === 401) { set({ usuario: null, verificado: true }); return true }
      set({ error: mensajeError(error) })
      return false
    } finally { set({ cargando: false }) }
  },
}))

api.interceptors.response.use(respuesta => respuesta, error => {
  if (error.response?.status === 401) {
    version++
    useAuthStore.setState({ usuario: null, verificado: true, cargando: false })
  }
  return Promise.reject(error)
})
export default useAuthStore
