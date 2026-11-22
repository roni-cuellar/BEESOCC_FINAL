import api from './api.js'
const ruta = (id) => '/campeonatos/' + id + '/arbitro'
const arbitroService = {
  vigente: async (id) => (await api.get(ruta(id))).data.asignacion,
  historial: async (id) => (await api.get(ruta(id) + '/historial')).data.asignaciones,
  buscarUsuario: async (id, email) =>
    (await api.post(ruta(id) + '/buscar-usuario', { email })).data.usuario,
  asignar: async (id, usuario_id) => (await api.post(ruta(id), { usuario_id })).data,
  reemplazar: async (id, usuario_id, motivo_revocacion) =>
    (await api.patch(ruta(id), { usuario_id, motivo_revocacion })).data,
  revocar: async (id, motivo_revocacion) =>
    (await api.delete(ruta(id), { data: { motivo_revocacion } })).data,
  misArbitrajes: async () => (await api.get('/mis-arbitrajes')).data.asignaciones,
  partidos: async (id) => (await api.get('/mis-arbitrajes/' + id + '/partidos')).data,
  plantillas: async (id) => (await api.get('/partidos/' + id + '/plantillas-arbitrales')).data,
  panel: async (id) => (await api.get(`/partidos/${id}/panel-arbitral`)).data,
  preparar: async (id, datos) => (await api.put(`/partidos/${id}/panel-arbitral`, datos)).data,
  reloj: async (id, datos) => (await api.patch(`/partidos/${id}/panel-arbitral/reloj`, datos)).data,
}
export default arbitroService
