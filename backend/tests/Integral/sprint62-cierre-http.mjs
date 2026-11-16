// HTTP de red real; las cookies y claves solo viven en memoria o en TEMP privado.
import assert from 'node:assert/strict'
import { createHmac } from 'node:crypto'
import { readFile, writeFile } from 'node:fs/promises'
import { loginConEspera } from './sprint52-cliente.mjs'

const carpeta = process.env.BEESOCC_INTEGRAL_DIR
assert.match(carpeta || '', /beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/)
const entorno = JSON.parse(await readFile(carpeta + '/entorno.json', 'utf8'))
assert.equal(entorno.DB_HOST, '127.0.0.1'); assert.equal(entorno.DB_PORT, '3317')
assert.match(entorno.DB_USERNAME, /^beesocc23c_/)
assert.match(entorno.DB_DATABASE, /^beesocc_23c_test_/)
const fixture = JSON.parse(await readFile(carpeta + '/sprint62-fixture.json', 'utf8'))
const clave = createHmac('sha256', entorno.APP_KEY).update('sprint62-login').digest('hex')
const modo = process.argv[2]
assert.ok(['preparar', 'completar', 'resultados', 'bloqueos'].includes(modo))
try {
  const anterior = JSON.parse(await readFile(`${carpeta}/sprint62-cierre-http-${modo}.json`, 'utf8'))
  assert.notEqual(anterior.ok, true, 'Este modo ya fue aprobado; conserva su evidencia y no lo repitas')
} catch (error) {
  if (error.code !== 'ENOENT') throw error
}
const pruebas = []
function comprobar(nombre, r, esperado = 200) {
  // No imprimir cuerpos que puedan contener información de sesión.
  assert.equal(r.estado, esperado, `${nombre}: HTTP ${r.estado}, esperado ${esperado}; mensaje: ${r.datos?.message || ''}`)
  pruebas.push({ prueba: nombre, estado: esperado })
  return r.datos
}
async function sesion(rol) {
  const archivoSesion = `${carpeta}/sprint62-cierre-sesion-${rol}.json`
  let cookies = {}
  try { cookies = JSON.parse(await readFile(archivoSesion, 'utf8')) } catch { /* Primera sesión del laboratorio. */ }
  async function pedir(metodo, ruta, datos, sinCsrf = false, puerto = 8013) {
    assert.ok([8013, 8014].includes(puerto)); assert.ok(ruta.startsWith('/'))
    const headers = { Accept: 'application/json', Origin: `http://beesocc23c-${rol}.localhost:5175`, Cookie: Object.entries(cookies).map(([k, v]) => k + '=' + v).join('; ') }
    if (datos !== undefined) headers['Content-Type'] = 'application/json'
    if (!sinCsrf && cookies['XSRF-TOKEN']) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies['XSRF-TOKEN'])
    const r = await fetch(`http://127.0.0.1:${puerto}${ruta}`, { method: metodo, headers, body: datos === undefined ? undefined : JSON.stringify(datos), signal: AbortSignal.timeout(30000) })
    for (const valor of r.headers.getSetCookie()) {
      const par = valor.split(';')[0], i = par.indexOf('='); cookies[par.slice(0, i)] = par.slice(i + 1)
    }
    await writeFile(archivoSesion, JSON.stringify(cookies))
    return { estado: r.status, datos: await r.json().catch(() => null), reintentarEn: Number(r.headers.get('retry-after')) }
  }
  if (Object.keys(cookies).length && (await pedir('GET', '/api/usuario')).estado === 200) return pedir
  comprobar('CSRF real ' + rol, await pedir('GET', '/sanctum/csrf-cookie'), 204)
  const datosLogin = { email: fixture.usuarios[rol].email, password: clave }
  const login = await loginConEspera(pedir, datosLogin, segundos => {
    pruebas.push({ prueba: 'Límite real de login conservado', estado: 429 })
    console.log(JSON.stringify({ esperando_limite_login_segundos: segundos, modo }))
  })
  comprobar('Login real ' + rol, login)
  return pedir
}
const admin = await sesion('admin'), arbitro = await sesion('arbitro')
const guardar = (nombre, datos) => writeFile(`${carpeta}/sprint62-${nombre}.json`, JSON.stringify(datos, null, 2))
const leer = async nombre => JSON.parse(await readFile(`${carpeta}/sprint62-${nombre}.json`, 'utf8'))
async function crear(nombre, cantidad, formato = 'grupos_eliminacion') {
  const c = comprobar('Crear campeonato ficticio ' + nombre, await admin('POST', '/api/campeonatos', { nombre: 'Cierre 6.2 ' + nombre, temporada: '2026', formato }), 201).campeonato
  const ids = [], plantillas = {}
  for (const equipo of fixture.equipos.slice(0, cantidad)) {
    const i = comprobar('Inscribir equipo ficticio', await admin('POST', `/api/campeonatos/${c.id}/inscripciones-equipos`, { equipo_id: equipo.equipo_id }), 201).inscripcion
    ids.push(i.id)
    plantillas[i.id] = comprobar('Crear plantilla ficticia', await admin('POST', `/api/inscripciones-equipos/${i.id}/plantillas`, { jugador_id: equipo.jugador_id, dorsal: 1, posicion: 'Delantero', fecha_ingreso: '2026-01-01' }), 201).plantilla.id
  }

  return { id: c.id, ids, plantillas }
}
async function fase(t, cantidad = 2, cupo = 1, metodo = 'manual') {
  const f = comprobar('Crear fase real de grupos', await admin('POST', `/api/campeonatos/${t.id}/fases`, { nombre: 'Grupos ficticios', tipo: 'grupos' }), 201).fase
  const grupos = Array.from({ length: cantidad }, () => [])
  t.ids.forEach((id, i) => grupos[i % cantidad].push(id))
  t.fase = f.id
  t.datos = { metodo, cantidad_grupos: cantidad, clasificados_por_grupo: cupo, ...(metodo === 'manual' ? { grupos } : {}) }
  return t
}
async function vista(t) {
  const v = comprobar('Previsualizar distribución firmada', await admin('POST', `/api/fases/${t.fase}/previsualizar-grupos`, t.datos))
  const { fase_id, ...envio } = v
  assert.equal(fase_id, t.fase)
  t.envio = envio
  return envio
}
async function confirmar(t) {
  await vista(t)
  return comprobar('Confirmar distribución exacta', await admin('POST', `/api/fases/${t.fase}/confirmar-grupos`, t.envio), 201).fase
}
async function calendario(t) {
  const c = comprobar('Generar calendario real de grupos', await admin('POST', `/api/fases/${t.fase}/generar-calendario-grupos`), 201)
  const pares = new Set(), jornadas = new Set()
  for (const p of c.partidos) {
    assert.equal(p.fase_id, t.fase); assert.equal(p.llave_id, null)
    const grupo = t.envio.grupos.find(g => g.includes(p.participantes[0].campeonato_equipo_id))
    assert.ok(p.participantes.every(x => grupo.includes(x.campeonato_equipo_id)))
    const par = p.participantes.map(x => x.campeonato_equipo_id).sort((a, b) => a - b).join(':')
    assert.ok(!pares.has(par)); pares.add(par)
    for (const x of p.participantes) {
      const clave = `${p.grupo_id}:${p.jornada}:${x.campeonato_equipo_id}`
      assert.ok(!jornadas.has(clave)); jornadas.add(clave)
    }
  }
  return c
}
async function iniciar(t, p, local = 1, visitante = 0) {
  comprobar('Iniciar partido como árbitro vigente', await arbitro('PATCH', `/api/partidos/${p.id}`, { estado: 'en_curso' }))
  const eventos = []
  for (const [rol, cantidad] of [['local', local], ['visitante', visitante]]) {
    const x = p.participantes.find(x => x.rol === rol)
    for (let i = 0; i < cantidad; i++) eventos.push(comprobar('Registrar gol por HTTP', await arbitro('POST', `/api/partidos/${p.id}/eventos`, { tipo: 'gol', minuto: 15 + i, equipo_partido_id: x.id, plantilla_id: t.plantillas[x.campeonato_equipo_id] }), 201).evento)
  }
  return eventos
}
const finalizar = async p => comprobar('Oficializar resultado deportivo', await arbitro('POST', `/api/partidos/${p.id}/finalizar`))
async function oficializar(t, p, local = 1, visitante = 0) {
  const eventos = await iniciar(t, p, local, visitante); await finalizar(p); return eventos
}
const clasificacion = async t => comprobar('Consultar clasificación oficial', await admin('GET', `/api/fases/${t.fase}/clasificacion-grupos`))
const auditoria = async t => comprobar('Consultar auditoría de fase', await admin('GET', `/api/fases/${t.fase}/auditoria`))
const archivoCasos = `${carpeta}/sprint62-cierre-casos.json`
if (modo === 'preparar') {
  const liga = comprobar('Consultar campeonato creado desde React', await admin('GET','/api/campeonatos')).find(c => c.nombre === 'Cierre 6.2 Liga ficticia')
  assert.ok(liga)
  const casos = [{id:liga.id, ids:[], plantillas:{}, formato:'todos_contra_todos'}]
  for (const [nombre, formato, cantidad] of [['Directa ficticia','eliminacion_directa',2],['Grupos ficticios','grupos_eliminacion',4]]) {
    const t = await crear(nombre,cantidad,formato)
    t.formato = formato
    comprobar('Configurar mínimo explícito por API',await admin('PATCH',`/api/campeonatos/${t.id}/requisitos-inicio`,{modalidad:'Modalidad ficticia',minimo_jugadores:1}))
    casos.push(t)
  }
  const t = casos[0]
  for (const e of fixture.equipos.slice(0,2)) {
    const i = comprobar('Inscribir equipo para la liga React',await admin('POST',`/api/campeonatos/${t.id}/inscripciones-equipos`,{equipo_id:e.equipo_id}),201).inscripcion
    t.ids.push(i.id)
    t.plantillas[i.id]=comprobar('Preparar plantilla por HTTP',await admin('POST',`/api/inscripciones-equipos/${i.id}/plantillas`,{jugador_id:e.jugador_id,dorsal:1,posicion:'Ficticia',fecha_ingreso:'2026-01-01'}),201).plantilla.id
  }
  await writeFile(archivoCasos,JSON.stringify(casos,null,2))
  console.log(JSON.stringify({casos:casos.map(t=>({id:t.id,formato:t.formato}))}))
} else {
  const casos=JSON.parse(await readFile(archivoCasos,'utf8'))
  if(modo==='completar') for(const t of casos){
    const asignaciones=comprobar('Consultar árbitro',await admin('GET',`/api/campeonatos/${t.id}/arbitro`))
    // La preparación inicial mantiene el arbitraje incompleto para probar el rechazo desde React.
    if(!asignaciones.asignacion) comprobar('Asignar árbitro real',await admin('POST',`/api/campeonatos/${t.id}/arbitro`,{usuario_id:fixture.usuarios.arbitro.id}),201)
    if(t.formato==='todos_contra_todos') comprobar('Calendario liga',await admin('POST',`/api/campeonatos/${t.id}/generar-calendario`),201)
    else if(t.formato==='eliminacion_directa'){
      const f=comprobar('Crear final directa',await admin('POST',`/api/campeonatos/${t.id}/fases`,{tipo:'eliminacion',nombre:'Final ficticia'}),201).fase
      const datos={siembra:t.ids,rondas:[{modalidad:'unico',desempate:'penales'}]}
      const v=comprobar('Previsualizar final',await admin('POST',`/api/fases/${f.id}/previsualizar-llaves`,datos))
      comprobar('Generar cuadro',await admin('POST',`/api/fases/${f.id}/generar-llaves`,{...datos,confirmacion:v.confirmacion}),201)
      t.fase=f.id
    }else{await fase(t);await confirmar(t);await calendario(t)}
  }
  if(modo==='resultados') for(const t of casos){
    const c=comprobar('Verificar inicio realizado desde React',await admin('GET',`/api/campeonatos/${t.id}`))
    assert.equal(c.estado,'en_curso')
    const ruta=`/api/campeonatos/${t.id}`
    comprobar('Alta bloqueada tras inicio',await admin('POST',`/api/inscripciones-equipos/${t.ids[0]}/plantillas`,{jugador_id:fixture.equipos[6].jugador_id,dorsal:25,posicion:'Ficticia',fecha_ingreso:'2026-01-01'}),409)
    comprobar('Dorsal bloqueado tras inicio',await admin('PATCH',`/api/plantillas/${t.plantillas[t.ids[0]]}`,{dorsal:25}),409)
    comprobar('Retiro bloqueado tras inicio',await admin('PATCH',`/api/inscripciones-equipos/${t.ids[0]}`,{estado:'retirado'}),409)
    comprobar('Cierre prematuro rechazado',await admin('POST',ruta+'/finalizar'),409)
    const partidos=comprobar('Calendario a oficializar',await admin('GET',ruta+'/partidos'))
    for(const p of partidos) await oficializar(t,p)
    if(t.formato==='grupos_eliminacion'){
      const v=await clasificacion(t)
      comprobar('Clasificar oficialmente',await admin('POST',`/api/fases/${t.fase}/clasificar-grupos`,{confirmacion:v.confirmacion}))
      const cr=comprobar('Previsualizar eliminación',await admin('POST',`/api/fases/${t.fase}/previsualizar-eliminacion`,{metodo:'automatico',rondas:[{modalidad:'unico',desempate:'penales'}]}))
      const datos=Object.fromEntries(['metodo','rondas','sorteo','confirmacion_sorteo','cruces','confirmacion'].map(k=>[k,cr[k]]))
      const f=comprobar('Generar final desde grupos',await admin('POST',`/api/fases/${t.fase}/generar-eliminacion`,datos),201).fase
      t.faseEliminacion=f.id
      const nuevos=comprobar('Leer final generada',await admin('GET',ruta+'/partidos')).filter(p=>p.fase_id===f.id)
      for(const p of nuevos) await oficializar(t,p)
    }
    if(t.formato!=='todos_contra_todos'){
      const f=comprobar('Consultar llave final',await admin('GET',`/api/fases/${t.faseEliminacion||t.fase}`))
      comprobar('Resolver campeón por resultado oficial',await admin('POST',`/api/llaves/${f.rondas[0].llaves[0].id}/avanzar`))
    }
  }
  if(modo==='bloqueos') for(const t of casos){
    const c=comprobar('Verificar cierre realizado desde React',await admin('GET',`/api/campeonatos/${t.id}`));assert.equal(c.estado,'finalizado')
    const antes=comprobar('Auditoría previa a rechazos',await admin('GET',`/api/campeonatos/${t.id}/auditoria-ciclo`))
    comprobar('No reiniciar',await admin('POST',`/api/campeonatos/${t.id}/iniciar`),409)
    comprobar('No finalizar dos veces',await admin('POST',`/api/campeonatos/${t.id}/finalizar`),409)
    comprobar('No editar requisitos',await admin('PATCH',`/api/campeonatos/${t.id}/requisitos-inicio`,{modalidad:'Cambio ficticio',minimo_jugadores:2}),409)
    comprobar('No cambiar dorsal finalizado',await admin('PATCH',`/api/plantillas/${t.plantillas[t.ids[0]]}`,{dorsal:25}),409)
    const partidos=comprobar('Consultar históricos',await admin('GET',`/api/campeonatos/${t.id}/partidos`))
    comprobar('Árbitro no agrega eventos después del cierre',await arbitro('POST',`/api/partidos/${partidos[0].id}/eventos`,{tipo:'gol',minuto:40,equipo_partido_id:partidos[0].participantes[0].id,plantilla_id:t.plantillas[t.ids[0]]}),409)
    const despues=comprobar('Auditoría sin cambios parciales',await admin('GET',`/api/campeonatos/${t.id}/auditoria-ciclo`));assert.deepEqual(despues,antes)
    assert.equal(despues.filter(a=>a.accion==='iniciar_campeonato').length,1);assert.equal(despues.filter(a=>a.accion==='finalizar_campeonato').length,1)
  }
  await writeFile(archivoCasos,JSON.stringify(casos,null,2))
}
await writeFile(`${carpeta}/sprint62-cierre-http-${modo}.json`,JSON.stringify({ok:true,modo,comprobaciones:pruebas.length,pruebas},null,2))
console.log(JSON.stringify({ok:true,modo,comprobaciones:pruebas.length}))