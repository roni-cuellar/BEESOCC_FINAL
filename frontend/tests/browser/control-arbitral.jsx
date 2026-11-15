// Escenario de navegador aislado: todas las peticiones se resuelven en memoria.
import { MemoryRouter } from 'react-router-dom'
import api from '../../src/services/api'
import useAuthStore from '../../src/stores/useAuthStore'
import AppRoutes from '../../src/routes/AppRoutes'
import '../../src/styles/global.css'
import '../../src/styles/inicio.css'
import '../../src/styles/aplicacion.css'
import '../../src/styles/tailwind.css'

const participante = (id, rol, nombre) => ({ id, rol, campeonato_equipo_id: id, campeonato_equipo: { equipo: { id, nombre } } })
const participantes = [participante(10, 'local', 'Águilas de Bogotá'), participante(20, 'visitante', 'Leones de Medellín')]
const partido = { id: 3, campeonato_id: 4, jornada: 1, estado: 'programado', fecha_hora: '2026-11-14T18:00:00Z', lugar: 'Cancha de prueba', participantes }
const plantilla = (id, equipo, nombre) => ({ id, jugador_id: id, campeonato_equipo_id: equipo, dorsal: id, fecha_ingreso: '2026-01-01', fecha_salida: null, jugador: { id, nombre, apellido: 'Prueba' } })
const plantillas = { 10: [plantilla(1, 10, 'Ana'), plantilla(2, 10, 'Camila')], 20: [plantilla(3, 20, 'Luis')] }
let revocado = false
let siguienteError = null
let enviados = 0
let secuencia = 0
let eventos = []
const rechazar = status => { throw { response: { status, data: { message: 'Conflicto de prueba. Actualiza el acta.' } } } }
api.defaults.adapter = async config => {
  await new Promise(resolve => setTimeout(resolve, 150))
  if (revocado) rechazar(403)
  const { url, method } = config
  const datos = config.data ? JSON.parse(config.data) : {}
  const marcador = rol => {
    const equipo = participantes.find(p => p.rol === rol)
    return { id: equipo.id, nombre: equipo.campeonato_equipo.equipo.nombre, goles: eventos.filter(e => ['gol', 'autogol'].includes(e.tipo) && e.equipo_partido_id === equipo.id).length }
  }
  let respuesta
  if (method !== 'get' && siguienteError) { const codigo = siguienteError; siguienteError = null; rechazar(codigo) }
  if (method === 'get' && url === '/mis-arbitrajes') respuesta = { asignaciones: [{ id: 1, fecha_inicio: '2026-11-12', campeonato: { id: 4, nombre: 'Copa de prueba aislada', temporada: '2026', estado: 'en_curso' } }] }
  else if (method === 'get' && url === '/mis-arbitrajes/4/partidos') respuesta = [partido]
  else if (method === 'get' && url === '/partidos/3') respuesta = partido
  else if (method === 'get' && url === '/partidos/3/marcador') respuesta = { local: marcador('local'), visitante: marcador('visitante') }
  else if (method === 'get' && url === '/partidos/3/plantillas-arbitrales') respuesta = plantillas
  else if (method === 'get' && url === '/partidos/3/eventos') respuesta = eventos
  else if (method === 'get' && ['/partidos/3/correcciones', '/partidos/3/auditoria'].includes(url)) respuesta = []
  else if (method === 'post' && url === '/partidos/3/finalizar') {
    partido.estado = 'finalizado'; partido.tipo_resultado = 'deportivo'; partido.oficializado_at = new Date().toISOString(); respuesta = { partido }
  }
  else if (method === 'patch' && url === '/partidos/3') { partido.estado = datos.estado; respuesta = { partido } }
  else if (method === 'post' && url === '/partidos/3/eventos') {
    enviados++
    const evento = { id: ++secuencia, ...datos, plantilla: Object.values(plantillas).flat().find(p => p.id === datos.plantilla_id), equipo_partido: participantes.find(p => p.id === datos.equipo_partido_id) }
    eventos.push(evento); respuesta = { evento }
    document.getElementById('envios').textContent = 'POST de eventos: ' + enviados
  } else if (/^\/eventos\/\d+$/.test(url)) {
    const id = Number(url.split('/').at(-1))
    if (method === 'delete') { eventos = eventos.filter(e => e.id !== id); respuesta = {} }
    else if (method === 'patch') { const evento = eventos.find(e => e.id === id); evento.minuto = datos.minuto; respuesta = { evento } }
    else rechazar(404)
  } else rechazar(404)
  return { data: structuredClone(respuesta), status: 200, headers: {}, config }
}
useAuthStore.setState({ usuario: { id: 7, name: 'Árbitro de prueba' }, verificado: true })

export default function Escenario() {
  return <>
    <aside className="panel"><strong>Prueba aislada en memoria. Sin conexión al backend.</strong><p id="envios">POST de eventos: 0</p>
      <button className="boton boton-borde" onClick={() => { revocado = true; window.dispatchEvent(new Event('focus')) }}>Simular revocación</button>
      <button className="boton boton-borde" onClick={() => { siguienteError = 409 }}>Simular próximo 409</button>
    </aside>
    <MemoryRouter initialEntries={['/mis-arbitrajes']}><AppRoutes /></MemoryRouter>
  </>
}
