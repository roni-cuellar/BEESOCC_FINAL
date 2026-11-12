
import api from './api.js'

// Preparar datos del equipo, incluyendo la imagen
const prepararDatos = (datos) => {
  const formulario = new FormData()

  formulario.append('nombre', datos.nombre)
  formulario.append('ciudad', datos.ciudad)

  if (datos.escudo instanceof File) {
    formulario.append('escudo', datos.escudo)
  }

  return formulario
}

const equipoService = {
  // Listar equipos
  listar: async () =>
    (await api.get('/equipos')).data,

  // Crear equipo con escudo
  crear: async (datos) =>
    (await api.post('/equipos', prepararDatos(datos))).data.equipo,

  // Actualizar equipo (con o sin imagen)
  actualizar: async (id, datos) => {
    if (datos.escudo instanceof File) {
      const formulario = prepararDatos(datos)

      // Laravel interpreta POST + _method como PATCH
      formulario.append('_method', 'PATCH')

      return (await api.post('/equipos/' + id, formulario)).data.equipo
    }

    return (await api.patch('/equipos/' + id, datos)).data.equipo
  },

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
