
import axios from 'axios'

// Configuración de la API
const direccion = (import.meta.env?.VITE_API_URL || '/api').replace(/\/+$/, '')

const api = axios.create({
  baseURL: direccion.endsWith('/api') ? direccion : direccion + '/api',
  headers: {
    Accept: 'application/json',
  },
  timeout: 20000,
  withCredentials: true,
  withXSRFToken: true,
})


function textoError(mensaje) {
  if (
    typeof mensaje !== 'string' ||
    /SQLSTATE|Illuminate|Stack trace|Exception|vendor[\\/]/i.test(mensaje)
  ) {
    return 'No se pudo completar la operación.'
  }

  const campos = {
    name: 'nombre',
    email: 'correo electrónico',
    password: 'contraseña',
    password_confirmation: 'confirmación de contraseña',
    campeonato_equipo_id: 'equipo inscrito',
    equipo_partido_id: 'equipo participante',
    gol_relacionado_id: 'gol relacionado',
    campeonato_id: 'campeonato',
    jugador_id: 'jugador',
    plantilla_id: 'jugador de la plantilla',
    equipo_id: 'equipo',
    local_id: 'equipo local',
    visitante_id: 'equipo visitante',
    fecha_nacimiento: 'fecha de nacimiento',
    fecha_ingreso: 'fecha de ingreso',
    fecha_salida: 'fecha de salida',
    fecha_hora: 'fecha y hora',
    numero_vueltas: 'vueltas',
    orden_desempate: 'orden de desempate',
  }

  return mensaje.replace(
    /\b[a-z_]+\b/g,
    campo => Object.hasOwn(campos, campo) ? campos[campo] : campo
  )
}

// Obtener un mensaje de error de las peticiones
export function mensajeError(error) {
  if (!error.response) {
    return 'No se pudo conectar. Intenta de nuevo.'
  }

  const { status, data } = error.response

  if (status >= 500) {
    return 'No se pudo completar la solicitud. Intenta de nuevo.'
  }

  if (status === 401) return 'Tu sesión terminó. Inicia sesión de nuevo.'
  if (status === 403) return 'No tienes permiso para realizar esta operación.'
  if (status === 419) return 'La sesión de seguridad venció. Intenta de nuevo.'
  if (status === 429) return 'Demasiados intentos. Espera un minuto.'

  if (status === 404) {
    return 'No encontramos este registro.'
  }

  if (status === 422 && data?.errors) {
    return Object.values(data.errors)
      .flat()
      .map(textoError)
      .join(' ')
  }

  if (data?.message) {
    return textoError(data.message)
  }

  if (status === 409) {
    return 'La operación no está permitida para este registro.'
  }

  return 'No se pudo completar la operación.'
}

// Obtener la URL completa de las imágenes
export function urlImagen(ruta) {
  if (!ruta || typeof ruta !== 'string') {
    return ''
  }

  const texto = ruta.trim()

  // Imágenes externas como Cloudinary
  if (/^https?:\/\//i.test(texto)) {
    return texto
  }

  // Evitar protocolos no permitidos
  if (texto.startsWith('//') || /^[a-z]+:/i.test(texto)) {
    return ''
  }

  // Imágenes almacenadas en Laravel
  const base = api.defaults.baseURL.startsWith('http')
    ? new URL(api.defaults.baseURL).origin
    : ''

  return base + (texto.startsWith('/') ? texto : '/storage/' + texto)
}

export default api
