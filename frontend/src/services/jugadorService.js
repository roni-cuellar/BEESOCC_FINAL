import api from './api.js'
import { guardarCatalogo } from './imagenCatalogo.js'
const jugadorService = {
  listar: async () => (await api.get('/jugadores')).data,
  crear: async (datos) => (await guardarCatalogo('/jugadores', datos, 'foto')).jugador,
  actualizar: async (id, datos) =>
    (await guardarCatalogo('/jugadores/' + id, datos, 'foto', true)).jugador,
  eliminar: async (id) => (await api.delete('/jugadores/' + id)).data,
  plantillas: async (inscripcionId) =>
    (await api.get('/inscripciones-equipos/' + inscripcionId + '/plantillas')).data,
  inscribir: async (id, datos) =>
    (await api.post('/inscripciones-equipos/' + id + '/plantillas', datos)).data.plantilla,
  actualizarPlantilla: async (id, datos) =>
    (await api.patch('/plantillas/' + id, datos)).data.plantilla,
  eliminarPlantilla: async (id) => (await api.delete('/plantillas/' + id)).data,
}

export default jugadorService
