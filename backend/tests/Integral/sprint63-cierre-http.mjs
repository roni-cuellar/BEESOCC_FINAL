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
const fixture = JSON.parse(await readFile(carpeta + '/cierre63-fixture.json', 'utf8'))
const clave = createHmac('sha256', entorno.APP_KEY).update('cierre63-login').digest('hex')
const modo = process.argv[2]
assert.ok(['preparar','preparar_avance','completar_grupos','preparar_dp03','incompatible','verificar'].includes(modo))
try {
  const anterior = JSON.parse(await readFile(`${carpeta}/cierre63-http-${modo}.json`, 'utf8'))
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
  const archivoSesion = `${carpeta}/cierre63-sesion-${rol}.json`
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
const admin = await sesion('admin'), arbitro = await sesion('arbitro'), ajeno = await sesion('ajeno')
const guardar = (nombre, datos) => writeFile(`${carpeta}/cierre63-${nombre}.json`, JSON.stringify(datos, null, 2))
const leer = async nombre => JSON.parse(await readFile(`${carpeta}/cierre63-${nombre}.json`, 'utf8'))
async function crear(nombre, cantidad, formato = 'grupos_eliminacion') {
  const c = comprobar('Crear campeonato ficticio ' + nombre, await admin('POST', '/api/campeonatos', { nombre: 'Cierre 6.3 ' + nombre, temporada: '2026', formato }), 201).campeonato
  const ids = [], plantillas = {}
  for (const equipo of fixture.equipos.slice(0, cantidad)) {
    const i = comprobar('Inscribir equipo ficticio', await admin('POST', `/api/campeonatos/${c.id}/inscripciones-equipos`, { equipo_id: equipo.equipo_id }), 201).inscripcion
    ids.push(i.id)
    plantillas[i.id] = comprobar('Crear plantilla ficticia', await admin('POST', `/api/inscripciones-equipos/${i.id}/plantillas`, { jugador_id: equipo.jugador_id, dorsal: 1, posicion: 'Delantero', fecha_ingreso: '2026-01-01' }), 201).plantilla.id
  }
  comprobar('Asignar árbitro por HTTP', await admin('POST', `/api/campeonatos/${c.id}/arbitro`, { usuario_id: fixture.usuarios.arbitro.id }), 201)
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
const archivoCasos=`${carpeta}/cierre63-casos.json`
const a3 = (p,beneficiado,extra={}) => admin('POST',`/api/partidos/${p.id}/resultado-administrativo`,{beneficiado_id:beneficiado,sancionado_id:p.participantes.find(x=>x.campeonato_equipo_id!==beneficiado).campeonato_equipo_id,motivo:'Decisión ficticia preparada por HTTP',...extra})
const casos=modo==='preparar'?{}:JSON.parse(await readFile(archivoCasos,'utf8'))
if(modo==='preparar'){
 for(const [clave,n,formato] of [['liga',3,'todos_contra_todos'],['directa',4,'eliminacion_directa'],['grupos',4,'grupos_eliminacion'],['doble',2,'eliminacion_directa']]){
  const t=await crear(clave,n,formato);t.formato=formato
  comprobar('Configurar requisitos ficticios',await admin('PATCH',`/api/campeonatos/${t.id}/requisitos-inicio`,{modalidad:'Modalidad ficticia',minimo_jugadores:1}))
  if(formato==='todos_contra_todos')comprobar('Calendario liga',await admin('POST',`/api/campeonatos/${t.id}/generar-calendario`),201)
  else if(formato==='grupos_eliminacion'){await fase(t);await confirmar(t);await calendario(t)}
  else{
   t.fase=comprobar('Crear fase directa',await admin('POST',`/api/campeonatos/${t.id}/fases`,{tipo:'eliminacion',nombre:'Eliminación ficticia'}),201).fase.id
   const datos={siembra:t.ids,rondas:Array.from({length:Math.ceil(Math.log2(n))},()=>({modalidad:'unico',desempate:'penales'}))}
   const v=comprobar('Previsualizar cuadro',await admin('POST',`/api/fases/${t.fase}/previsualizar-llaves`,datos))
   comprobar('Generar cuadro',await admin('POST',`/api/fases/${t.fase}/generar-llaves`,{...datos,confirmacion:v.confirmacion}),201)
  }
  comprobar('Iniciar campeonato ficticio',await admin('POST',`/api/campeonatos/${t.id}/iniciar`))
  t.partidos=comprobar('Consultar partidos',await admin('GET',`/api/campeonatos/${t.id}/partidos`))
  casos[clave]=t
 }
 const l=casos.liga,p=l.partidos[0];await iniciar(l,p,1,0)
 l.retirar=l.ids.find(id=>!p.participantes.some(x=>x.campeonato_equipo_id===id))
 casos.directa.descalificar=casos.directa.partidos[0].participantes.find(x=>x.rol==='visitante').campeonato_equipo_id
 console.log(JSON.stringify(Object.fromEntries(Object.entries(casos).map(([k,t])=>[k,{id:t.id,partidos:t.partidos.map(p=>p.id),fase:t.fase,retirar:t.retirar,descalificar:t.descalificar}]))))
}
if(modo==='preparar_avance'){
 const t=casos.directa,p=t.partidos[1]
 const primero=comprobar('Verificar administrativo registrado desde React',await admin('GET',`/api/partidos/${t.partidos[0].id}`));assert.equal(primero.tipo_resultado,'administrativo')
 comprobar('Resolver semifinal secundaria por HTTP',await a3(p,p.participantes.find(x=>x.rol==='local').campeonato_equipo_id))
}
if(modo==='completar_grupos'){
 const t=casos.grupos,p=t.partidos[1]
 const primero=comprobar('Verificar administrativo de grupos desde React',await admin('GET',`/api/partidos/${t.partidos[0].id}`));assert.equal(primero.tipo_resultado,'administrativo')
 comprobar('Resolver otro grupo por HTTP',await a3(p,p.participantes.find(x=>x.rol==='local').campeonato_equipo_id))
 const v=await clasificacion(t);assert.equal(v.lista_para_clasificar,true)
 comprobar('Confirmar clasificación oficial por API',await admin('POST',`/api/fases/${t.fase}/clasificar-grupos`,{confirmacion:v.confirmacion}))
}
if(modo==='preparar_dp03'){
 const t=casos.doble,p=t.partidos[0]
 comprobar('Oficial previo a doble sanción',await a3(p,p.participantes[0].campeonato_equipo_id))
 for(const id of t.ids)comprobar('Descalificación ficticia secundaria',await admin('POST',`/api/inscripciones-equipos/${id}/decision-administrativa`,{tipo:'descalificacion',motivo:'Sanción ficticia para validar DP-03'}))
}
if(modo==='incompatible'){
 const t=casos.directa
 const partidos=comprobar('Leer final ya generada desde React',await admin('GET',`/api/campeonatos/${t.id}/partidos`))
 const final=partidos.find(p=>p.jornada===2);assert.ok(final)
 comprobar('Árbitro inicia final para probar incompatibilidad',await arbitro('PATCH',`/api/partidos/${final.id}`,{estado:'en_curso'}))
 const p=t.partidos[1]
 const cor=comprobar('Solicitar rectificación incompatible preparada',await admin('POST',`/api/partidos/${p.id}/correcciones`,{alcance:'resultado',motivo:'Rectificación ficticia sobre destino iniciado'}),201).correccion
 comprobar('Aprobar expediente secundario',await admin('PATCH',`/api/correcciones/${cor.id}/autorizar`,{estado:'aprobada'}))
 t.incompatible={partido:comprobar('Capturar partido antes de rechazo',await admin('GET',`/api/partidos/${p.id}`)),final:comprobar('Capturar final antes de rechazo',await admin('GET',`/api/partidos/${final.id}`)),auditoria:comprobar('Capturar auditoría antes de rechazo',await admin('GET',`/api/partidos/${p.id}/auditoria`)),autorizacion:cor.id}
 console.log(JSON.stringify({partido:p.id,final:final.id,autorizacion:cor.id,beneficiar:p.participantes.find(x=>x.rol==='visitante').campeonato_equipo_id,sancionar:p.participantes.find(x=>x.rol==='local').campeonato_equipo_id}))
}
if(modo==='verificar'){
 const t=casos.liga,p=t.partidos[0]
 const r=comprobar('Marcador efectivo y deportivo separados',await admin('GET',`/api/partidos/${p.id}/marcador`));assert.equal(r.tipo_resultado,'administrativo');assert.equal(r.marcador_deportivo.local,1);assert.equal(r.marcador_deportivo.visitante,0);assert.equal(r.local.goles+r.visitante.goles,3)
 const goles=comprobar('Conservar único gol deportivo real',await admin('GET',`/api/campeonatos/${t.id}/estadisticas/goleadores`));assert.equal(goles.resultados.reduce((n,f)=>n+f.total,0),1)
 const cs=comprobar('Expediente de React consumido',await admin('GET',`/api/partidos/${p.id}/correcciones`));assert.equal(cs.filter(c=>c.alcance==='resultado'&&c.estado==='consumida').length,1)
 comprobar('Árbitro no registra administrativo',await arbitro('POST',`/api/partidos/${p.id}/resultado-administrativo`,{beneficiado_id:p.participantes[0].campeonato_equipo_id,sancionado_id:p.participantes[1].campeonato_equipo_id,motivo:'Intento ficticio sin permiso'}),403)
 const ins=comprobar('Retiro registrado desde React',await admin('GET',`/api/inscripciones-equipos/${t.retirar}`));assert.equal(ins.estado,'retirado');assert.ok(ins.motivo_inactividad)
 for(const clave of ['directa','grupos','doble']){const c=casos[clave];const g=comprobar('Sin goles individuales administrativos '+clave,await admin('GET',`/api/campeonatos/${c.id}/estadisticas/goleadores`));assert.equal(g.resultados.length,0)}
 const dg=comprobar('Descalificación registrada desde React',await admin('GET',`/api/inscripciones-equipos/${casos.directa.descalificar}`));assert.equal(dg.estado,'descalificado');assert.ok(dg.motivo_inactividad)
 const clas=await clasificacion(casos.grupos);assert.equal(clas.clasificacion_vigente,true);assert.equal(clas.candidatos.length,2)
 const tabla=comprobar('Tabla refleja 3–0 oficial',await admin('GET',`/api/grupos/${casos.grupos.partidos[0].grupo_id}/tabla?vista=oficial`));assert.equal(tabla.tabla[0].PTS,3);assert.equal(tabla.tabla[0].GF,3)
 const d=casos.doble;const f=comprobar('Doble descalificación no inventa ganador',await admin('GET',`/api/fases/${d.fase}`));const llave=f.rondas[0].llaves[0];assert.equal(llave.estado,'pendiente');assert.equal(llave.ganador_fase_equipo_id,null)
 comprobar('Bloqueo DP-03 también por API',await admin('POST',`/api/llaves/${llave.id}/avanzar`),409)
 const b=casos.directa.incompatible;assert.ok(b)
 assert.deepEqual(comprobar('Partido sin cambios parciales',await admin('GET',`/api/partidos/${b.partido.id}`)),b.partido)
 assert.deepEqual(comprobar('Final conserva participantes e IDs',await admin('GET',`/api/partidos/${b.final.id}`)),b.final)
 assert.deepEqual(comprobar('Rechazo no agrega auditoría de éxito',await admin('GET',`/api/partidos/${b.partido.id}/auditoria`)),b.auditoria)
 const correcciones=comprobar('Autorización incompatible no consumida',await admin('GET',`/api/partidos/${b.partido.id}/correcciones`));assert.equal(correcciones.find(c=>c.id===b.autorizacion).estado,'aprobada');assert.equal(correcciones.find(c=>c.id===b.autorizacion).fecha_consumo,null)
}
await writeFile(archivoCasos,JSON.stringify(casos,null,2))
await writeFile(`${carpeta}/cierre63-http-${modo}.json`,JSON.stringify({ok:true,modo,comprobaciones:pruebas.length,pruebas},null,2))
console.log(JSON.stringify({ok:true,modo,comprobaciones:pruebas.length}))