import api from './api'

const raiz = api.defaults.baseURL.replace(/\/api$/, '')
const csrf = () => api.get(raiz + '/sanctum/csrf-cookie', { baseURL: '' })

const authService = {
  registrar: async (datos) => {
    await csrf()
    return (await api.post('/registro', datos)).data.usuario
  },
  login: async (datos) => {
    await csrf()
    return (await api.post('/login', datos)).data.usuario
  },
  usuario: async () => (await api.get('/usuario')).data.usuario,
  logout: async () => {
    await csrf()
    await api.post('/logout')
  },
}
export default authService
