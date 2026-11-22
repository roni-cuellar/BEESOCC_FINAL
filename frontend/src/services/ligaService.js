import api from './api.js'
import { guardarCatalogo } from './imagenCatalogo.js'
const ligaService = {
  requisitosInicio: async (id, datos) =>
    (await api.patch('/campeonatos/' + id + '/requisitos-inicio', datos)).data,
  iniciar: async (id) => (await api.post('/campeonatos/' + id + '/iniciar')).data,
  finalizar: async (id) => (await api.post('/campeonatos/' + id + '/finalizar')).data,
  auditoriaCiclo: async (id) => (await api.get('/campeonatos/' + id + '/auditoria-ciclo')).data,
  listar: async () => (await api.get('/campeonatos')).data,
  obtener: async (id) => (await api.get('/campeonatos/' + id)).data,
  crear: async (datos) => (await guardarCatalogo('/campeonatos', datos, 'logo')).campeonato,
  actualizar: async (id, datos) =>
    (await guardarCatalogo('/campeonatos/' + id, datos, 'logo', true)).campeonato,
  eliminar: async (id) => (await api.delete('/campeonatos/' + id)).data,
  publicacion: async (id, publicado) =>
    (await api.patch('/campeonatos/' + id + '/publicacion', { publicado })).data.campeonato,
  tabla: async (id) => (await api.get('/campeonatos/' + id + '/tabla-posiciones')).data,
  identidades: async (id) => (await api.get(`/campeonatos/${id}/identidades-deportivas`)).data,
  fichaJugador: async (id, jugador) =>
    (await api.get(`/campeonatos/${id}/fichas/jugadores/${jugador}`)).data,
  estadisticas: async (id, tipo) =>
    (await api.get('/campeonatos/' + id + '/estadisticas/' + tipo)).data,
  configuracion: async (id) => (await api.get('/campeonatos/' + id + '/configuracion')).data,
  crearConfiguracion: async (id, datos) =>
    (await api.post('/campeonatos/' + id + '/configuracion', datos)).data,
  actualizarConfiguracion: async (id, datos) =>
    (await api.patch('/campeonatos/' + id + '/configuracion', datos)).data,
}
export default ligaService
