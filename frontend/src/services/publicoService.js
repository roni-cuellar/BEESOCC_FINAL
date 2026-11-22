import api from './api'

// Las vistas de espectadores usan únicamente los contratos públicos de lectura.
const opciones = { withCredentials: false, withXSRFToken: false }
const base = '/publico/campeonatos'
const publicoService = {
  listar: async () => (await api.get(base, opciones)).data,
  porCodigo: async (codigo, signal) =>
    (await api.get(base + '/por-codigo', { ...opciones, params: { codigo }, signal })).data,
  obtener: async (id) => (await api.get(base + '/' + id, opciones)).data,
  equipos: async (id) => (await api.get(base + '/' + id + '/equipos', opciones)).data,
  identidades: async (id) => (await api.get(`${base}/${id}/identidades-deportivas`, opciones)).data,
  plantilla: async (id, inscripcion) =>
    (await api.get(base + '/' + id + '/equipos/' + inscripcion + '/plantilla', opciones)).data,
  jugador: async (id, jugador) =>
    (await api.get(base + '/' + id + '/jugadores/' + jugador, opciones)).data,
  partidos: async (id) => (await api.get(base + '/' + id + '/partidos', opciones)).data,
  tabla: async (id) => (await api.get(base + '/' + id + '/tabla', opciones)).data,
  deporte: async (id) => (await api.get(base + '/' + id + '/deporte', opciones)).data,
  estadisticas: async (id, tipo) =>
    (await api.get(base + '/' + id + '/estadisticas/' + tipo, opciones)).data,
}
export default publicoService
