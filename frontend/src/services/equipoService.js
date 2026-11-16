
import api from './api.js'

import { guardarCatalogo } from './imagenCatalogo.js'

const equipoService = {
  // Listar equipos
  listar: async () =>
    (await api.get('/equipos')).data,

  crear: async datos => (await guardarCatalogo('/equipos', datos, 'escudo')).equipo,
  actualizar: async (id, datos) => (await guardarCatalogo('/equipos/' + id, datos, 'escudo', true)).equipo,
  // Eliminar equipo
  eliminar: async (id) =>
    (await api.delete('/equipos/' + id)).data,

  // Inscripciones
  inscripciones: async (ligaId) =>
    (await api.get('/campeonatos/' + ligaId + '/inscripciones-equipos')).data,

  inscribir: async (ligaId, equipoId) =>
    (await api.post('/campeonatos/' + ligaId + '/inscripciones-equipos', {
      equipo_id: Number(equipoId)
    })).data.inscripcion,

  estadoInscripcion: async (id, estado) =>
    (await api.patch('/inscripciones-equipos/' + id, {
      estado
    })).data.inscripcion,

  eliminarInscripcion: async (id) =>
    (await api.delete('/inscripciones-equipos/' + id)).data,
}

export default equipoService
