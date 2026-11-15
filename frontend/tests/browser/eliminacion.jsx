// Datos ficticios y adaptador cerrado: este escenario nunca llama a Laravel.
import { useState } from 'react'
import { MemoryRouter } from 'react-router-dom'
import AppRoutes from '../../src/routes/AppRoutes'
import useAuthStore from '../../src/stores/useAuthStore'
import api from '../../src/services/api'
import '../../src/styles/global.css'
import '../../src/styles/inicio.css'
import '../../src/styles/aplicacion.css'
import '../../src/styles/tailwind.css'

const liga = { id: 44, nombre: 'Copa BEESOCC · demostración', temporada: '2026', estado: 'planificacion', formato: 'eliminacion_directa' }
const inscripciones = ['Águilas de Bogotá', 'Leones de Medellín', 'Tigres de Cali'].map((nombre, i) => ({ id: i + 1, estado: 'activo', equipo: { id: i + 1, nombre } }))
let fase = null, historial = [], vista = null, envios = 0, revocado = false, siguienteError = null
const rechazar = (status, message) => { throw { response: { status, data: { message } } } }
const plaza = (id, posicion, fase_equipo_id, origen_tipo, origen_llave_id = null) => ({ id, posicion, fase_equipo_id, origen_tipo, origen_llave_id })
const partido = { id: 30, campeonato_id: 44, jornada: 1, estado: 'finalizado', numero_serie: 1, llave_id: 12, oficializado_at: '2026-11-15T20:00:00Z', tipo_resultado: 'deportivo', participantes: [] }
const registrar = (accion, datos) => historial.push({ id: historial.length + 1, usuario_id: 1, entidad_tipo: 'fase', entidad_id: 7, accion, motivo: 'Escenario ficticio para revisar la interfaz.', fecha: '2026-11-15T20:00:00Z', anterior_json: null, posterior_json: structuredClone(datos) })
api.defaults.adapter = async config => {
  await new Promise(resolve => setTimeout(resolve, 100))
  if (revocado) rechazar(403, 'Asignación revocada.')
  const { url, method } = config
  const datos = config.data ? JSON.parse(config.data) : {}
  let respuesta
  if (method !== 'get') {
    if (siguienteError) { const status = siguienteError; siguienteError = null; rechazar(status, 'Conflicto simulado: vuelve a consultar el cuadro.') }
    envios++
    document.getElementById('envios-cuadro').textContent = 'Escrituras simuladas: ' + envios
  }
  if (method === 'get' && url === '/campeonatos/44') respuesta = liga
  else if (method === 'get' && url === '/campeonatos/44/inscripciones-equipos') respuesta = inscripciones
  else if (method === 'get' && url === '/campeonatos/44/fases') respuesta = fase ? [fase] : []
  else if (method === 'post' && url === '/campeonatos/44/fases') {
    fase = { id: 7, campeonato_id: 44, nombre: datos.nombre, tipo: 'eliminacion', estado: 'planificacion', equipos: [], rondas: [] }
    registrar('crear_fase', fase); respuesta = { fase }
  } else if (method === 'get' && url === '/fases/7') respuesta = fase
  else if (method === 'get' && url === '/fases/7/auditoria') respuesta = historial
  else if (method === 'get' && url === '/mis-arbitrajes/44/partidos') respuesta = [partido]
  else if (method === 'get' && url === '/partidos/30/marcador') respuesta = { local: { nombre: inscripciones[1].equipo.nombre, goles: 1 }, visitante: { nombre: inscripciones[2].equipo.nombre, goles: 1 } }
  else if (method === 'post' && url === '/fases/7/previsualizar-llaves') {
    vista = { ...datos, fase_id: 7, cruces: [{ numero: 1, siembras: [1, 4], inscripciones: [datos.siembra[0], null] }, { numero: 2, siembras: [2, 3], inscripciones: [datos.siembra[1], datos.siembra[2]] }], confirmacion: 'a'.repeat(64) }
    respuesta = vista
  } else if (method === 'post' && url === '/fases/7/generar-llaves') {
    if (!vista || datos.confirmacion !== vista.confirmacion || JSON.stringify(datos.siembra) !== JSON.stringify(vista.siembra)) rechazar(409, 'La vista previa no coincide.')
    // Respuesta deportiva fija para probar la presentación; no sustituye reglas del backend.
    fase.equipos = inscripciones.map(i => ({ id: i.id + 100, inscripcion: i }))
    fase.rondas = [
      { id: 1, numero: 1, nombre: 'Semifinal', estado: 'en_curso', ...datos.rondas[0], llaves: [
        { id: 11, numero: 1, estado: 'resuelta', ganador_fase_equipo_id: 101, metodo_resolucion: 'bye', plazas: [plaza(1, 1, 101, 'directo'), plaza(2, 2, null, 'bye')], partidos: [] },
        { id: 12, numero: 2, estado: 'pendiente', ganador_fase_equipo_id: null, penales_plaza_1: null, penales_plaza_2: null, plazas: [plaza(3, 1, 102, 'directo'), plaza(4, 2, 103, 'directo')], partidos: [partido] },
      ] },
      { id: 2, numero: 2, nombre: 'Final', estado: 'planificacion', ...datos.rondas[1], llaves: [
        { id: 13, numero: 1, estado: 'pendiente', ganador_fase_equipo_id: null, plazas: [plaza(5, 1, 101, 'ganador_llave', 11), plaza(6, 2, null, 'ganador_llave', 12)], partidos: [] },
      ] },
    ]
    registrar('generar_llaves', vista); respuesta = { fase }
  } else if (method === 'post' && url === '/llaves/12/penales') {
    const llave = fase.rondas[0].llaves[1]
    Object.assign(llave, { penales_plaza_1: datos.penales_plaza_1, penales_plaza_2: datos.penales_plaza_2 })
    registrar('registrar_penales', llave); respuesta = { llave }
  } else if (method === 'post' && url === '/llaves/12/avanzar') {
    const llave = fase.rondas[0].llaves[1]
    if (llave.penales_plaza_1 == null) rechazar(409, 'Empate oficial: falta una tanda válida certificada por el árbitro.')
    Object.assign(llave, { estado: 'resuelta', ganador_fase_equipo_id: 102, metodo_resolucion: 'penales' })
    fase.rondas[1].llaves[0].plazas[1].fase_equipo_id = 102
    registrar('resolver_llave', llave); respuesta = { llave }
  } else rechazar(404, 'Ruta no cubierta por este escenario aislado: ' + url)
  return { data: structuredClone(respuesta), status: 200, headers: {}, config }
}
useAuthStore.setState({ usuario: { id: 1, name: 'Usuario de prueba' }, verificado: true })

export default function Escenario() {
  const [perfil, setPerfil] = useState('admin')
  return <>
    <aside className="panel relative z-[60] lg:ml-64"><strong>Prueba simulada Sprint 4.4 · sin conexión a Laravel ni MariaDB</strong><p id="envios-cuadro">Escrituras simuladas: {envios}</p>
      <button className="boton boton-borde" onClick={() => setPerfil(p => p === 'admin' ? 'arbitro' : 'admin')}>Cambiar a {perfil === 'admin' ? 'árbitro' : 'administrador'}</button>
      <button className="boton boton-borde" onClick={() => { siguienteError = 409 }}>Simular próximo 409</button>
      <button className="boton boton-borde" onClick={() => { revocado = true; window.dispatchEvent(new Event('focus')) }}>Simular revocación</button>
    </aside>
    <MemoryRouter key={perfil} initialEntries={[perfil === 'admin' ? '/ligas/44/cuadro' : '/mis-arbitrajes/44/cuadro']}><AppRoutes /></MemoryRouter>
  </>
}
