import api from './api'

// Las firmas y todos los resultados deportivos pertenecen a Laravel.
const grupoService = {
  crearFase: async (id, nombre) => (await api.post(`/campeonatos/${id}/fases`, { nombre, tipo: 'grupos' })).data.fase,
  previsualizarDistribucion: async (id, datos) => (await api.post(`/fases/${id}/previsualizar-grupos`, datos)).data,
  confirmarDistribucion: async (id, datos) => (await api.post(`/fases/${id}/confirmar-grupos`, datos)).data.fase,
  calendario: async id => (await api.post(`/fases/${id}/generar-calendario-grupos`)).data,
  tabla: async (id, vista = 'oficial') => (await api.get(`/grupos/${id}/tabla`, { params: { vista } })).data,
  clasificacion: async id => (await api.get(`/fases/${id}/clasificacion-grupos`)).data,
  clasificar: async (id, confirmacion) => (await api.post(`/fases/${id}/clasificar-grupos`, { confirmacion })).data,
  desempates: async id => (await api.get(`/grupos/${id}/desempates`)).data,
  previsualizarDesempate: async (id, datos) => (await api.post(`/grupos/${id}/previsualizar-desempate`, datos)).data,
  generarDesempate: async (id, datos) => (await api.post(`/grupos/${id}/generar-desempate`, datos)).data.desempate,
  previsualizarEliminacion: async (id, datos) => (await api.post(`/fases/${id}/previsualizar-eliminacion`, datos)).data,
  generarEliminacion: async (id, datos) => (await api.post(`/fases/${id}/generar-eliminacion`, datos)).data.fase,
}
export default grupoService
