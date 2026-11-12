import { create } from 'zustand'
import ligaService from '../services/ligaService.js'
import { mensajeError } from '../services/api.js'
let solicitud = 0

const useLigaStore = create((set, get) => ({
  ligas: [], liga: null, solicitada: '', cargando: false, error: '',
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
  cargarLiga: async id => {
    const actual = ++solicitud
    set({ liga: null, solicitada: String(id), cargando: true, error: '' })
    try {
      const liga = await ligaService.obtener(id)
      if (actual === solicitud && get().solicitada === String(id)) set({ liga })
    } catch (error) {
      if (actual === solicitud && get().solicitada === String(id)) set({ error: mensajeError(error) })
    } finally {
      if (actual === solicitud && get().solicitada === String(id)) set({ cargando: false })
    }
  },
}))
export default useLigaStore
