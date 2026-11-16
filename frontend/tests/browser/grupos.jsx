// Adaptador cerrado: ninguna petición de este recorrido llega a Laravel o MariaDB.
import { useState } from 'react'
import { MemoryRouter } from 'react-router-dom'
import AppRoutes from '../../src/routes/AppRoutes'
import api from '../../src/services/api'
import useAuthStore from '../../src/stores/useAuthStore'
import { escenarioGrupos } from '../fixtures/grupos'
import '../../src/styles/global.css'
import '../../src/styles/inicio.css'
import '../../src/styles/aplicacion.css'
import '../../src/styles/tailwind.css'

let d = escenarioGrupos('inicio'), creada = false, distribucion, siguienteError, revocado = false, escrituras = 0
const fallar = (status, message) => { throw { response: { status, data: { message } } } }
const cambiarEtapa = etapa => { d = escenarioGrupos(etapa === 'listas' ? 'clasificados' : etapa); if (etapa === 'listas') d.clasificacion.clasificacion_vigente = false; creada = true; revocado = false; siguienteError = null }
const auditar = accion => d.historial.push({ id: d.historial.length + 1, usuario_id: 1, entidad_tipo: 'fase', entidad_id: 7, accion, motivo: 'Demostración ficticia de interfaz.', fecha: '2026-11-15T20:00:00Z', anterior_json: null, posterior_json: {} })
api.defaults.adapter = async config => {
  await new Promise(r => setTimeout(r, 100))
  if (revocado) fallar(403, 'Permiso revocado en la API simulada.')
  const { method, url } = config, datos = config.data ? JSON.parse(config.data) : {}
  if (method !== 'get') {
    if (siguienteError) { const status = siguienteError; siguienteError = null; fallar(status, 'La propuesta cambió. Actualiza y vuelve a previsualizar.') }
    escrituras++; document.getElementById('escrituras-grupos').textContent = `Solicitudes simuladas: ${escrituras}`
  }
  let respuesta
  if (method === 'get' && url === '/campeonatos/44') respuesta = d.liga
  else if (method === 'get' && url === '/campeonatos/44/inscripciones-equipos') respuesta = d.inscripciones
  else if (method === 'get' && url === '/campeonatos/44/fases') respuesta = creada ? [d.fase, ...(d.clasificacion.transicion_eliminacion.fase_eliminacion_id ? [d.eliminacion] : [])] : []
  else if (method === 'post' && url === '/campeonatos/44/fases') { creada = true; d.fase.nombre = datos.nombre; auditar('crear_fase_grupos'); respuesta = { fase: d.fase } }
  else if (method === 'get' && url === '/fases/7') respuesta = d.fase
  else if (method === 'get' && url === '/fases/8') respuesta = d.eliminacion
  else if (method === 'get' && /\/fases\/(7|8)\/auditoria/.test(url)) respuesta = d.historial
  else if (method === 'get' && url === '/fases/7/clasificacion-grupos') respuesta = d.clasificacion
  else if (method === 'get' && /\/grupos\/\d+\/tabla/.test(url)) respuesta = { ...d.clasificacion.tablas.find(t => String(t.grupo_id) === url.split('/')[2]), vista: config.params.vista }
  else if (method === 'get' && /\/grupos\/\d+\/desempates/.test(url)) respuesta = url.includes('/11/') && d.clasificacion.tablas[0]?.desempates_usados.includes(1) ? [d.caso] : []
  else if (method === 'get' && /\/(mis-arbitrajes|campeonatos)\/44\/partidos/.test(url)) respuesta = d.fase.grupos.flatMap(g => g.partidos)
  else if (method === 'get' && /\/partidos\/\d+\/marcador/.test(url)) respuesta = d.marcadores[url.split('/')[2]]
  else if (method === 'get' && /\/partidos\/\d+$/.test(url)) respuesta = [...d.fase.grupos.flatMap(g => g.partidos), ...d.caso.partidos].find(p => String(p.id) === url.split('/')[2])
  else if (method === 'get' && /\/partidos\/\d+\/(eventos|correcciones|auditoria)$/.test(url)) respuesta = []
  else if (method === 'get' && /\/partidos\/\d+\/plantillas/.test(url)) respuesta = {}
  else if (method === 'post' && url === '/fases/7/previsualizar-grupos') {
    distribucion = { fase_id: 7, ...datos, grupos: datos.grupos || (datos.cantidad_grupos === 3 ? [[1, 4], [2, 5], [3, 6]] : [[1, 3, 5], [2, 4, 6]]), confirmacion: d.firma }
    respuesta = distribucion
  } else if (method === 'post' && url === '/fases/7/confirmar-grupos') {
    if (!distribucion || datos.confirmacion !== distribucion.confirmacion) fallar(409, 'Propuesta ficticia caducada.')
    const base = escenarioGrupos('calendario')
    d.fase.equipos = base.fase.equipos
    d.fase.grupos = distribucion.grupos.map((ids, i) => ({ id: 11 + i, codigo: `G${i + 1}`, fase_id: 7, equipos: base.fase.equipos.filter(e => ids.includes(e.campeonato_equipo_id)), partidos: [] }))
    d.clasificacion.tablas = d.fase.grupos.map((g, i) => ({ ...base.clasificacion.tablas[i], calendario_generado: false, tabla: g.equipos.map(e => ({ ...base.clasificacion.tablas[0].tabla[0], nombre: e.inscripcion.equipo.nombre, campeonato_equipo_id: e.campeonato_equipo_id, posicion: null })), pendientes_resolucion: [] }))
    auditar('confirmar_grupos'); respuesta = { fase: d.fase }
  } else if (method === 'post' && url === '/fases/7/generar-calendario-grupos') {
    cambiarEtapa('calendario'); auditar('generar_calendario_grupos'); respuesta = { partidos_creados: 3, partidos: d.fase.grupos.flatMap(g => g.partidos) }
  } else if (method === 'post' && /\/grupos\/\d+\/previsualizar-desempate/.test(url)) respuesta = { grupo_id: 11, clave: datos.clave, inscripciones: [1, 4], posicion_inicio: 1, tipo: 'partido_extra', huella: 'ficticia', confirmacion: d.firma }
  else if (method === 'post' && /\/grupos\/\d+\/generar-desempate/.test(url)) { cambiarEtapa('penales'); auditar('generar_desempate_grupo'); respuesta = { desempate: d.caso } }
  else if (method === 'post' && url === '/llaves/20/penales') { Object.assign(d.caso.llave, datos); auditar('registrar_penales'); respuesta = { llave: d.caso.llave } }
  else if (method === 'post' && url === '/llaves/20/avanzar') { cambiarEtapa('listas'); auditar('resolver_llave'); respuesta = { llave: d.caso.llave } }
  else if (method === 'post' && url === '/fases/7/clasificar-grupos') { d.clasificacion.clasificacion_vigente = true; auditar('clasificar_grupos'); respuesta = d.clasificacion }
  else if (method === 'post' && url === '/fases/7/previsualizar-eliminacion') respuesta = { ...d.previa, ...datos }
  else if (method === 'post' && url === '/fases/7/generar-eliminacion') { cambiarEtapa('cuadro'); auditar('generar_eliminacion_grupos'); respuesta = { fase: d.eliminacion } }
  else fallar(404, 'Ruta no cubierta por la simulación cerrada.')
  return { config, headers: {}, status: 200, data: structuredClone(respuesta) }
}
useAuthStore.setState({ usuario: { id: 1, name: 'Usuario ficticio' }, verificado: true })

export default function Escenario() {
  const [revision, setRevision] = useState(0)
  const [perfil, setPerfil] = useState('admin')
  const [etapa, setEtapa] = useState('inicio')
  function escenario(valor) { cambiarEtapa(valor); setEtapa(valor); setRevision(r => r + 1) }
  return <><aside className="panel relative z-[60] lg:ml-64"><strong>Recorrido visual 5.6 · API simulada · sin Laravel ni MariaDB</strong><p id="escrituras-grupos">Solicitudes simuladas: {escrituras}</p><div className="acciones">
    <label>Escenario simulado<select value={etapa} onChange={e => escenario(e.target.value)}><option value="inicio">Configuración</option><option value="empate">Empate pendiente</option><option value="penales">Partido extra oficial</option><option value="listas">Candidatos listos</option><option value="clasificados">Clasificados oficiales</option><option value="cuadro">Cuadro generado</option></select></label>
    <button className="boton boton-borde" onClick={() => setPerfil(p => p === 'admin' ? 'arbitro' : 'admin')}>Cambiar a {perfil === 'admin' ? 'árbitro' : 'administrador'}</button>
    <button className="boton boton-borde" onClick={() => { siguienteError = 409 }}>Simular próximo 409</button>
    <button className="boton boton-borde" onClick={() => { revocado = true; window.dispatchEvent(new Event('focus')) }}>Simular revocación</button>
  </div></aside><MemoryRouter key={`${perfil}:${revision}`} initialEntries={[perfil === 'admin' ? '/ligas/44/grupos' : '/mis-arbitrajes/44/grupos']}><AppRoutes /></MemoryRouter></>
}
