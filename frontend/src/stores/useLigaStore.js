import { create } from 'zustand'
import ligaService from '../services/ligaService.js'
import { mensajeError } from '../services/api.js'
let solicitud = 0

const useLigaStore = create((set, get) => ({
  ligas: [], liga: null, solicitada: '', cargando: false, error: '',
  publicando: false,
  cambiarPublicacion: async (id, publicado) => {
    if (get().publicando) throw new Error('Hay una publicación en curso.')
    set({ publicando: true })
    try {
      const actualizada = await ligaService.publicacion(id, publicado)
      set(estado => ({
        ligas: estado.ligas.map(liga => liga.id === actualizada.id ? actualizada : liga),
        liga: estado.liga?.id === actualizada.id ? actualizada : estado.liga,
      }))
      return actualizada
    } finally { set({ publicando: false }) }
  },
  cargarLigas: async () => {
    const actual = ++solicitud
    set({ cargando: true, error: '', ligas: [] })
    try {
      const ligas = await ligaService.listar()
      if (actual === solicitud) set({ ligas })
    }
    catch (error) { if (actual === solicitud) set({ error: mensajeError(error) }) }
    finally { if (actual === solicitud) set({ cargando: false }) }
  },
  cargarLiga: async (id, { conservar = false } = {}) => {
    const actual = ++solicitud
    // Una validación 409 conserva el panel y su mensaje mientras refresca el estado.
    set({ ...(conservar ? {} : { liga: null }), solicitada: String(id), cargando: !conservar, error: '' })
    try {
      const liga = await ligaService.obtener(id)
      if (actual === solicitud && get().solicitada === String(id)) set({ liga })
    } catch (error) {
      if (!conservar && actual === solicitud && get().solicitada === String(id)) set({ error: mensajeError(error) })
    } finally {
      if (actual === solicitud && get().solicitada === String(id)) set({ cargando: false })
    }
  },
}))
export default useLigaStore
