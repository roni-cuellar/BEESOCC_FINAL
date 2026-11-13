import api from './api'

// Las vistas de espectadores usan únicamente los contratos públicos de lectura.
const opciones = { withCredentials: false, withXSRFToken: false }
const base = '/publico/campeonatos'
const publicoService = {
  listar: async () => (await api.get(base, opciones)).data,
  obtener: async id => (await api.get(base + '/' + id, opciones)).data,
  equipos: async id => (await api.get(base + '/' + id + '/equipos', opciones)).data,
  partidos: async id => (await api.get(base + '/' + id + '/partidos', opciones)).data,
  tabla: async id => (await api.get(base + '/' + id + '/tabla', opciones)).data,
  estadisticas: async (id, tipo) => (await api.get(base + '/' + id + '/estadisticas/' + tipo, opciones)).data,
}
export default publicoService
