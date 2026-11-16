import api from './api'

export default {
  resultado: async (id, datos) => (await api.post(`/partidos/${id}/resultado-administrativo`, datos)).data,
  inactivar: async (id, datos) => (await api.post(`/inscripciones-equipos/${id}/decision-administrativa`, datos)).data,
  solicitarRectificacion: async (id, motivo) => (await api.post(`/partidos/${id}/correcciones`, { motivo, alcance: 'resultado' })).data.correccion,
}
