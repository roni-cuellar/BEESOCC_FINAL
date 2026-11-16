import { create } from 'zustand'
import equipoService from '../services/equipoService.js'
import { mensajeError } from '../services/api.js'
let solicitud = 0
const useEquipoStore = create((set, get) => ({
  equipos: [],
  inscripciones: [],
  ligaId: '',
  cargando: false,
  error: '',
  cargar: async (ligaId) => {
    const actual = ++solicitud
    const id = String(ligaId)
    set({ ligaId: id, equipos: [], inscripciones: [], cargando: true, error: '' })
    try {
      const [equipos, inscripciones] = await Promise.all([
        equipoService.listar(),
        equipoService.inscripciones(id),
      ])
      if (actual === solicitud && get().ligaId === id) set({ equipos, inscripciones })
    } catch (error) {
      if (actual === solicitud && get().ligaId === id) set({ error: mensajeError(error) })
    } finally {
      if (actual === solicitud && get().ligaId === id) set({ cargando: false })
    }
  },
}))
export default useEquipoStore
