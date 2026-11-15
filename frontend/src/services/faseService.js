import api from './api.js'

// Comparte cookies Sanctum y CSRF con los demás servicios privados.
const faseService = {
  listar: async id => (await api.get('/campeonatos/' + id + '/fases')).data,
  crear: async (id, nombre) => (await api.post('/campeonatos/' + id + '/fases', { nombre, tipo: 'eliminacion' })).data.fase,
  obtener: async id => (await api.get('/fases/' + id)).data,
  auditoria: async id => (await api.get('/fases/' + id + '/auditoria')).data,
  previsualizar: async (id, datos) => (await api.post('/fases/' + id + '/previsualizar-llaves', datos)).data,
  generar: async (id, datos) => (await api.post('/fases/' + id + '/generar-llaves', datos)).data.fase,
  avanzar: async id => (await api.post('/llaves/' + id + '/avanzar')).data.llave,
  penales: async (id, datos) => (await api.post('/llaves/' + id + '/penales', datos)).data.llave,
}
export default faseService
