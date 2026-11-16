import { create } from 'zustand'
import partidoService from '../services/partidoService.js'
import { mensajeError } from '../services/api.js'
let solicitud = 0
const usePartidoStore = create((set, get) => ({
  partidos: [],
  ligaId: '',
  cargando: false,
  error: '',
  cargar: async (ligaId) => {
    const actual = ++solicitud
    const id = String(ligaId)
    set({ ligaId: id, partidos: [], cargando: true, error: '' })
    try {
      const partidos = await partidoService.listar(id)
      if (actual === solicitud && get().ligaId === id) set({ partidos })
    } catch (error) {
      if (actual === solicitud && get().ligaId === id) set({ error: mensajeError(error) })
    } finally {
      if (actual === solicitud && get().ligaId === id) set({ cargando: false })
    }
  },
}))
export default usePartidoStore
