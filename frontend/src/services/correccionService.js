import api from './api'

const correccionService = {
  listar: async partidoId => (await api.get(`/partidos/${partidoId}/correcciones`)).data,
  auditoria: async partidoId => (await api.get(`/partidos/${partidoId}/auditoria`)).data,
  solicitar: async (partidoId, motivo) => (await api.post(`/partidos/${partidoId}/correcciones`, { motivo, alcance: 'eventos' })).data.correccion,
  resolver: async (id, estado) => (await api.patch(`/correcciones/${id}/autorizar`, { estado })).data.correccion,
  aplicar: async (id, operaciones) => (await api.post(`/correcciones/${id}/aplicar`, { operaciones })).data.correccion,
}

export default correccionService
