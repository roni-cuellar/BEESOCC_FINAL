// Solo respuestas simuladas en memoria: ninguna petición alcanza Laravel o MySQL.
import { useState } from 'react'
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
const partido = { id: 3, campeonato_id: 4, jornada: 1, estado: 'finalizado', tipo_resultado: 'deportivo', oficializado_at: '2026-11-14T23:00:00Z', fecha_hora: '2026-11-14T18:00:00Z', lugar: 'Cancha de prueba', participantes }
const plantilla = (id, equipo, nombre) => ({ id, jugador_id: id, campeonato_equipo_id: equipo, dorsal: id, fecha_ingreso: '2026-01-01', fecha_salida: null, jugador: { id, nombre, apellido: 'Prueba' } })
const plantillas = { 10: [plantilla(1, 10, 'Ana'), plantilla(2, 10, 'Camila')], 20: [plantilla(3, 20, 'Luis')] }
let eventos = [{ id: 1, tipo: 'gol', minuto: 25, equipo_partido_id: 10, plantilla_id: 1, gol_relacionado_id: null, plantilla: plantillas[10][0], equipo_partido: participantes[0] }]
const correcciones = []
const historial = []
let siguienteError = null
let revocado = false
let escrituras = 0
const rechazar = (status, message) => { throw { response: { status, data: { message, ...(status === 422 ? { errors: { motivo: ['Verifica los datos de la corrección.'] } } : {}) } } } }
function auditar(accion, motivo, anterior, posterior) {
  historial.push({ id: historial.length + 1, usuario_id: useAuthStore.getState().usuario.id, fecha: new Date().toISOString(), accion, motivo, anterior_json: anterior, posterior_json: posterior })
}
api.defaults.adapter = async config => {
  await new Promise(resolve => setTimeout(resolve, 200))
  const usuario = useAuthStore.getState().usuario
  if (!usuario) rechazar(401, 'Sin sesión.')
  if (revocado && usuario.id !== 1) rechazar(403, 'Asignación revocada.')
  const { url, method } = config
  const datos = config.data ? JSON.parse(config.data) : {}
  let respuesta
  if (method !== 'get') {
    escrituras++
    document.getElementById('escrituras-simuladas').textContent = 'Escrituras simuladas: ' + escrituras
    if (siguienteError) { const status = siguienteError; siguienteError = null; rechazar(status, 'El expediente cambió. Actualiza el acta.') }
  }
  if (method === 'get' && url === '/campeonatos/4') respuesta = { id: 4, nombre: 'Copa de prueba aislada', administrador_id: 1 }
  else if (method === 'get' && url === '/equipos') respuesta = []
  else if (method === 'get' && url === '/campeonatos/4/inscripciones-equipos') respuesta = []
  else if (method === 'get' && url === '/partidos/3') respuesta = partido
  else if (method === 'get' && url === '/mis-arbitrajes/4/partidos') respuesta = [partido]
  else if (method === 'get' && url === '/partidos/3/plantillas-arbitrales') respuesta = plantillas
  else if (method === 'get' && url === '/partidos/3/eventos') respuesta = eventos
  else if (method === 'get' && url === '/partidos/3/marcador') respuesta = {
    local: { nombre: 'Águilas de Bogotá', goles: eventos.filter(e => ['gol', 'autogol'].includes(e.tipo) && e.equipo_partido_id === 10).length },
    visitante: { nombre: 'Leones de Medellín', goles: eventos.filter(e => ['gol', 'autogol'].includes(e.tipo) && e.equipo_partido_id === 20).length },
  }
  else if (method === 'get' && url === '/partidos/3/correcciones') respuesta = correcciones
  else if (method === 'get' && url === '/partidos/3/auditoria') respuesta = historial
  else if (method === 'post' && url === '/partidos/3/correcciones') {
    if (usuario.id === 1) rechazar(403, 'Solo árbitro.')
    const correccion = { id: correcciones.length + 1, partido_id: 3, solicitado_por_id: usuario.id, motivo: datos.motivo, alcance: 'eventos', estado: 'solicitada', autorizado_por_id: null, fecha_resolucion: null, fecha_consumo: null }
    correcciones.push(correccion); auditar('solicitar_correccion', datos.motivo, null, { correccion }); respuesta = { correccion }
  } else if (/^\/correcciones\/\d+\/(autorizar|aplicar)$/.test(url)) {
    const correccion = correcciones.find(c => c.id === Number(url.split('/')[2]))
    if (!correccion) rechazar(404, 'No existe.')
    const anterior = structuredClone({ correccion, eventos })
    if (url.endsWith('autorizar') && method === 'patch') {
      if (usuario.id !== 1) rechazar(403, 'Solo administrador.')
      if (correccion.estado !== 'solicitada') rechazar(409, 'Ya resuelta.')
      Object.assign(correccion, { estado: datos.estado, autorizado_por_id: 1, fecha_resolucion: new Date().toISOString() })
      auditar('resolver_correccion', correccion.motivo, anterior, structuredClone({ correccion }))
    } else if (url.endsWith('aplicar') && method === 'post') {
      if (usuario.id !== correccion.solicitado_por_id) rechazar(403, 'Solo solicitante.')
      if (correccion.estado !== 'aprobada') rechazar(409, 'No aprobada o consumida.')
      for (const operacion of datos.operaciones) {
        if (operacion.accion === 'corregir_minuto') eventos.find(e => e.id === operacion.evento_id).minuto = operacion.minuto
        else if (operacion.accion === 'eliminar') eventos = eventos.filter(e => e.id !== operacion.evento_id)
        else eventos.push({ id: Math.max(0, ...eventos.map(e => e.id)) + 1, ...operacion.datos, plantilla: Object.values(plantillas).flat().find(p => p.id === operacion.datos.plantilla_id), equipo_partido: participantes.find(p => p.id === operacion.datos.equipo_partido_id) })
      }
      Object.assign(correccion, { estado: 'consumida', fecha_consumo: new Date().toISOString() })
      auditar('aplicar_correccion', correccion.motivo, anterior, structuredClone({ correccion, eventos }))
    } else rechazar(404, 'Ruta no simulada.')
    respuesta = { correccion }
  } else rechazar(404, 'Ruta no simulada.')
  return { data: structuredClone(respuesta), status: 200, headers: {}, config }
}
useAuthStore.setState({ usuario: { id: 7, name: 'Árbitro de prueba' }, verificado: true })

export default function Escenario() {
  const [vista, setVista] = useState({ arbitral: true, version: 0 })
  function cambiar(arbitral, id = 7) {
    revocado = false
    useAuthStore.setState({ usuario: { id: arbitral ? id : 1, name: 'Usuario de prueba' }, verificado: true })
    setVista(actual => ({ arbitral, version: actual.version + 1 }))
  }
  return <>
    <aside className="panel"><strong>PRUEBA SIMULADA. Sin conexión a Laravel ni MySQL.</strong><p id="escrituras-simuladas">Escrituras simuladas: {escrituras}</p>
      <div className="acciones"><button className="boton boton-borde" onClick={() => cambiar(true)}>Vista árbitro solicitante</button><button className="boton boton-borde" onClick={() => cambiar(false)}>Vista administrador</button><button className="boton boton-borde" onClick={() => cambiar(true, 9)}>Vista árbitro sustituto</button>
        <button className="boton boton-borde" onClick={() => { revocado = true; window.dispatchEvent(new Event('focus')) }}>Simular revocación</button>
        {[401, 403, 404, 409, 422].map(status => <button key={status} className="boton boton-borde" onClick={() => { siguienteError = status }}>Próximo {status}</button>)}
      </div>
    </aside>
    <MemoryRouter key={vista.version} initialEntries={[(vista.arbitral ? '/mis-arbitrajes/' : '/ligas/') + '4/partidos/3']}><AppRoutes /></MemoryRouter>
  </>
}
