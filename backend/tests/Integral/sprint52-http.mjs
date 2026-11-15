// HTTP de red real; las cookies y claves solo viven en memoria o en TEMP privado.
import assert from 'node:assert/strict'
import { createHmac } from 'node:crypto'
import { readFile, writeFile, access } from 'node:fs/promises'
import { spawn } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { loginConEspera, verificarBarrera } from './sprint52-cliente.mjs'

const carpeta = process.env.BEESOCC_INTEGRAL_DIR
assert.match(carpeta || '', /beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/)
const entorno = JSON.parse(await readFile(carpeta + '/entorno.json', 'utf8'))
assert.equal(entorno.DB_HOST, '127.0.0.1'); assert.equal(entorno.DB_PORT, '3317')
assert.match(entorno.DB_USERNAME, /^beesocc23c_/)
assert.match(entorno.DB_DATABASE, /^beesocc_23c_test_/)
const fixture = JSON.parse(await readFile(carpeta + '/sprint52-fixture.json', 'utf8'))
const clave = createHmac('sha256', entorno.APP_KEY).update('sprint52-login').digest('hex')
const modo = process.argv[2]
assert.ok(['grupos', 'compatibilidad', 'correcciones', 'carreras'].includes(modo))
const pruebas = []
function comprobar(nombre, r, esperado = 200) {
  // No imprimir cuerpos que puedan contener información de sesión.
  assert.equal(r.estado, esperado, `${nombre}: HTTP ${r.estado}, esperado ${esperado}; mensaje: ${r.datos?.message || ''}`)
  pruebas.push({ prueba: nombre, estado: esperado })
  return r.datos
}
async function sesion(rol) {
  const cookies = {}
  async function pedir(metodo, ruta, datos, sinCsrf = false, puerto = 8013) {
    assert.ok([8013, 8014].includes(puerto)); assert.ok(ruta.startsWith('/'))
    const headers = { Accept: 'application/json', Origin: `http://beesocc23c-${rol}.localhost:5175`, Cookie: Object.entries(cookies).map(([k, v]) => k + '=' + v).join('; ') }
    if (datos !== undefined) headers['Content-Type'] = 'application/json'
    if (!sinCsrf && cookies['XSRF-TOKEN']) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies['XSRF-TOKEN'])
    const r = await fetch(`http://127.0.0.1:${puerto}${ruta}`, { method: metodo, headers, body: datos === undefined ? undefined : JSON.stringify(datos), signal: AbortSignal.timeout(30000) })
    for (const valor of r.headers.getSetCookie()) {
      const par = valor.split(';')[0], i = par.indexOf('='); cookies[par.slice(0, i)] = par.slice(i + 1)
    }
    return { estado: r.status, datos: await r.json().catch(() => null), reintentarEn: Number(r.headers.get('retry-after')) }
  }
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
const guardar = (nombre, datos) => writeFile(`${carpeta}/sprint52-${nombre}.json`, JSON.stringify(datos, null, 2))
const leer = async nombre => JSON.parse(await readFile(`${carpeta}/sprint52-${nombre}.json`, 'utf8'))
async function crear(nombre, cantidad, formato = 'grupos_eliminacion') {
  const c = comprobar('Crear campeonato ficticio ' + nombre, await admin('POST', '/api/campeonatos', { nombre: 'Laboratorio 5.2 ' + nombre, temporada: '2026', formato }), 201).campeonato
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
let adminConcurrente
async function carrera(caso, t, ruta, datos, esperado) {
  const php = process.env.BEESOCC_PHP_BINARY
  assert.match(php || '', /^[A-Za-z]:[\\/]/)
  const otroAdmin = adminConcurrente ??= await sesion('admin')
  const nombreBarrera = `barrera-${caso}-${t.id}`
  const proceso = spawn(php, [fileURLToPath(new URL('./sprint52-barrera.php', import.meta.url)), caso, String(t.id)], { env: { ...process.env, BEESOCC_INTEGRAL_CONFIG: carpeta + '/entorno.json' }, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] })
  let error = ''
  proceso.stderr.on('data', x => { error += x })
  const terminado = new Promise((resolve, reject) => { proceso.on('error', reject); proceso.on('exit', codigo => codigo === 0 ? resolve() : reject(new Error('Falló barrera ' + caso + ': ' + error))) })
  // Evita un rechazo sin observador mientras se espera la admisión.
  terminado.catch(() => {})
  try {
    const limite = Date.now() + 15000
    let lista = false
    while (Date.now() < limite) {
      try { await access(`${carpeta}/sprint52-${nombreBarrera}-lista.json`); lista = true; break } catch { await new Promise(r => setTimeout(r, 30)) }
    }
    assert.ok(lista, 'La barrera no se inició')
    const respuestas = await Promise.all([admin('POST', ruta, datos), otroAdmin('POST', ruta, datos, false, 8014)])
    await terminado
    assert.deepEqual(respuestas.map(r => r.estado).sort((a, b) => a - b), [esperado, 409])
    for (const r of respuestas) comprobar('Carrera HTTP ' + caso, r, r.estado)
    const prueba = await leer(nombreBarrera + '-resultado')
    verificarBarrera(prueba)
    return prueba
  } finally { if (proceso.exitCode === null) proceso.kill() }
}

try {
  if (modo === 'grupos') {
    const automatico = await fase(await crear('Automática 7 equipos', 7), 3, 1, 'automatico')
    await vista(automatico)
    assert.deepEqual(automatico.envio.grupos.map(g => g.length), [3, 2, 2])
    assert.equal(new Set(automatico.envio.grupos.flat()).size, 7)
    const sinGrupos = comprobar('Vista previa no persiste distribución', await admin('GET', `/api/fases/${automatico.fase}`))
    assert.equal(sinGrupos.grupos.length, 0)
    comprobar('Firma alterada rechazada', await admin('POST', `/api/fases/${automatico.fase}/confirmar-grupos`, { ...automatico.envio, confirmacion: '0'.repeat(64) }), 409)
    comprobar('Confirmar sorteo sin repetirlo', await admin('POST', `/api/fases/${automatico.fase}/confirmar-grupos`, automatico.envio), 201)
    comprobar('Distribución duplicada rechazada', await admin('POST', `/api/fases/${automatico.fase}/confirmar-grupos`, automatico.envio), 409)
    const ca = await calendario(automatico)
    assert.equal(ca.partidos_creados, 5); assert.equal(ca.descansos.length, 3)
    comprobar('Calendario duplicado rechazado', await admin('POST', `/api/fases/${automatico.fase}/generar-calendario-grupos`), 409)
    assert.equal((await clasificacion(automatico)).lista_para_clasificar, false)
    const manual = await fase(await crear('Manual 4 equipos', 4))
    const ruta = `/api/fases/${manual.fase}/previsualizar-grupos`
    comprobar('Inscripción repetida rechazada', await admin('POST', ruta, { ...manual.datos, grupos: [[manual.ids[0], manual.ids[0]], manual.ids.slice(2)] }), 422)
    comprobar('Inscripción ajena rechazada', await admin('POST', ruta, { ...manual.datos, grupos: [[automatico.ids[0], manual.ids[2]], [manual.ids[1], manual.ids[3]]] }), 422)
    comprobar('Cantidad de grupos inviable', await admin('POST', ruta, { ...manual.datos, cantidad_grupos: 5 }), 422)
    const fm = await confirmar(manual)
    assert.deepEqual(fm.grupos.map(g => g.equipos.map(x => x.campeonato_equipo_id)), manual.envio.grupos)
    const cm = await calendario(manual)
    assert.equal(cm.partidos_creados, 2)
    const [p, q] = cm.partidos
    comprobar('Clasificación sin resultados bloqueada', await admin('POST', `/api/fases/${manual.fase}/clasificar-grupos`, { confirmacion: '0'.repeat(64) }), 409)
    const eventos = await iniciar(manual, p)
    const provisional = comprobar('Consulta provisional real', await admin('GET', `/api/grupos/${p.grupo_id}/tabla`))
    assert.equal(provisional.vista, 'provisional'); assert.equal(provisional.definitiva, false); assert.equal(provisional.partidos_computados, 0)
    await finalizar(p)
    const oficial = comprobar('Tabla oficial real 3/1/0', await admin('GET', `/api/grupos/${p.grupo_id}/tabla?vista=oficial`))
    assert.equal(oficial.definitiva, true)
    assert.deepEqual(Object.fromEntries(['PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS'].map(k => [k, oficial.tabla[0][k]])), { PJ: 1, PG: 1, PE: 0, PP: 0, GF: 1, GC: 0, DG: 1, PTS: 3 })
    await oficializar(manual, q, 2, 1)
    const v = await clasificacion(manual)
    assert.equal(v.lista_para_clasificar, true); assert.equal(v.candidatos.length, 2)
    assert.ok(v.candidatos.every(c => c.origen_tipo === 'grupo_puesto' && c.origen_puesto === 1))
    assert.equal(v.transicion_eliminacion.habilitada, false)
    const clasificada = comprobar('Confirmar clasificación uniforme', await admin('POST', `/api/fases/${manual.fase}/clasificar-grupos`, { confirmacion: v.confirmacion }))
    assert.equal(clasificada.clasificacion_vigente, true)
    comprobar('Clasificación duplicada rechazada', await admin('POST', `/api/fases/${manual.fase}/clasificar-grupos`, { confirmacion: v.confirmacion }), 409)
    const audit = await auditoria(manual)
    for (const accion of ['confirmar_grupos', 'generar_calendario_grupos', 'clasificar_grupos']) assert.equal(audit.filter(a => a.accion === accion).length, 1)
    comprobar('Árbitro consulta tabla', await arbitro('GET', `/api/grupos/${p.grupo_id}/tabla`))
    comprobar('Árbitro consulta historial', await arbitro('GET', `/api/fases/${manual.fase}/auditoria`))
    for (const [ruta, datos] of [[`/api/fases/${manual.fase}/confirmar-grupos`, manual.envio], [`/api/fases/${manual.fase}/generar-calendario-grupos`, {}], [`/api/fases/${manual.fase}/clasificar-grupos`, { confirmacion: v.confirmacion }]]) {
      comprobar('Árbitro sin configuración ni clasificación', await arbitro('POST', ruta, datos), 403)
      comprobar('Ajeno sin escritura', await ajeno('POST', ruta, datos), 403)
    }
    comprobar('Ajeno sin tabla', await ajeno('GET', `/api/grupos/${p.grupo_id}/tabla`), 403)
    comprobar('Ajeno sin auditoría', await ajeno('GET', `/api/fases/${manual.fase}/auditoria`), 403)
    const invitado = await fetch(`http://127.0.0.1:8013/api/grupos/${p.grupo_id}/tabla`, { headers: { Accept: 'application/json' } })
    comprobar('Sin sesión no expone tabla', { estado: invitado.status }, 401)
    comprobar('Grupo inexistente', await admin('GET', '/api/grupos/9999999/tabla'), 404)
    comprobar('CSRF obligatorio', await admin('POST', ruta, manual.datos, true), 419)
    comprobar('Cruces no implementados no se generan', await admin('POST', `/api/fases/${manual.fase}/generar-llaves`, { siembra: manual.ids, rondas: [{ modalidad: 'unico', desempate: 'penales' }], confirmacion: '0'.repeat(64) }), 409)
    await guardar('manual', { ...manual, partidos: cm.partidos, eventos, clasificacion: v })

    const empate = await fase(await crear('Empate absoluto', 4))
    await confirmar(empate)
    for (const p of (await calendario(empate)).partidos) await oficializar(empate, p, 0, 0)
    const ve = await clasificacion(empate)
    assert.equal(ve.lista_para_clasificar, false); assert.ok(ve.tablas.every(t => t.tabla.every(f => f.PTS === 1 && f.PE === 1 && f.posicion === null)))
    assert.ok(ve.tablas.every(t => t.pendientes_resolucion[0].motivo === 'partido_extra_pendiente'))
    comprobar('Sin partido extra no clasifica', await admin('POST', `/api/fases/${empate.fase}/clasificar-grupos`, { confirmacion: '0'.repeat(64) }), 409)
    const multiple = await fase(await crear('Empate múltiple', 3), 1, 2)
    await confirmar(multiple)
    for (const p of (await calendario(multiple)).partidos) await oficializar(multiple, p, 0, 0)
    const vm = await clasificacion(multiple)
    assert.equal(vm.lista_para_clasificar, false); assert.equal(vm.tablas[0].pendientes_resolucion[0].motivo, 'empate_multiple_pendiente_DP01_DP05')
    const cinco = await fase(await crear('Cinco grupos configurables', 5), 5)
    await confirmar(cinco)
    const cc = await calendario(cinco)
    assert.equal(cc.partidos_creados, 0); assert.equal(cc.descansos.length, 5)
    const vc = await clasificacion(cinco)
    assert.equal(vc.lista_para_clasificar, true); assert.equal(vc.candidatos.length, 5)
    await guardar('casos', { automatico, manual, empate, multiple, cinco })
  } else if (modo === 'compatibilidad') {
    const liga = await crear('Liga compatible', 3, 'todos_contra_todos')
    comprobar('Calendario todos contra todos', await admin('POST', `/api/campeonatos/${liga.id}/generar-calendario`), 201)
    const pl = comprobar('Partidos de liga', await admin('GET', `/api/campeonatos/${liga.id}/partidos`))
    assert.equal(pl.length, 3); assert.ok(pl.every(p => p.fase_id === null && p.grupo_id === null && p.llave_id === null))
    await oficializar(liga, pl[0])
    const tl = comprobar('Tabla de liga conserva puntos', await admin('GET', `/api/campeonatos/${liga.id}/tabla-posiciones`))
    assert.equal(tl.tabla[0].PTS, 3)
    const copa = await crear('Eliminación compatible', 6, 'eliminacion_directa')
    copa.fase = comprobar('Fase eliminatoria compatible', await admin('POST', `/api/campeonatos/${copa.id}/fases`, { nombre: 'Eliminación', tipo: 'eliminacion' }), 201).fase.id
    const unico = { modalidad: 'unico', desempate: 'penales' }
    const datos = { siembra: copa.ids, rondas: [unico, { modalidad: 'ida_vuelta', desempate: 'penales' }, unico] }
    const v = comprobar('Vista previa eliminatoria compatible', await admin('POST', `/api/fases/${copa.fase}/previsualizar-llaves`, datos))
    comprobar('Generación eliminatoria compatible', await admin('POST', `/api/fases/${copa.fase}/generar-llaves`, { ...datos, confirmacion: v.confirmacion }), 201)
    let cuadro = comprobar('Cuadro de seis equipos y descansos', await admin('GET', `/api/fases/${copa.fase}`))
    assert.equal(cuadro.rondas.length, 3); assert.equal(cuadro.rondas[0].llaves.filter(l => l.metodo_resolucion === 'bye').length, 2)
    for (const l of cuadro.rondas[0].llaves.filter(l => l.estado === 'pendiente')) {
      await oficializar(copa, l.partidos[0]); comprobar('Avance eliminatorio compatible', await admin('POST', `/api/llaves/${l.id}/avanzar`))
    }
    cuadro = comprobar('Semifinales ida/vuelta conectadas', await admin('GET', `/api/fases/${copa.fase}`))
    assert.equal(cuadro.rondas[1].modalidad, 'ida_vuelta')
    const l = cuadro.rondas[1].llaves[0]
    await oficializar(copa, l.partidos[0]); await oficializar(copa, l.partidos[1])
    comprobar('Empate global mantiene bloqueo', await admin('POST', `/api/llaves/${l.id}/avanzar`), 409)
    comprobar('Administrador no certifica penales', await admin('POST', `/api/llaves/${l.id}/penales`, { penales_plaza_1: 4, penales_plaza_2: 3, motivo: 'Ensayo ficticio 5.2' }), 403)
    comprobar('Árbitro certifica tanda existente', await arbitro('POST', `/api/llaves/${l.id}/penales`, { penales_plaza_1: 4, penales_plaza_2: 3, motivo: 'Ensayo ficticio 5.2' }))
    comprobar('Avance tras tanda autorizada', await admin('POST', `/api/llaves/${l.id}/avanzar`))
  } else if (modo === 'correcciones') {
    const t = await leer('manual'), p = t.partidos[0]
    const c = comprobar('Solicitar corrección deportiva de grupos', await arbitro('POST', `/api/partidos/${p.id}/correcciones`, { motivo: 'Corregir gol ficticio asignado al equipo incorrecto.', alcance: 'eventos' }), 201).correccion
    comprobar('Autorizar corrección por propietario', await admin('PATCH', `/api/correcciones/${c.id}/autorizar`, { estado: 'aprobada' }))
    const visitante = p.participantes.find(x => x.rol === 'visitante')
    const operaciones = [{ accion: 'eliminar', evento_id: t.eventos[0].id }, { accion: 'registrar', datos: { tipo: 'gol', minuto: 20, equipo_partido_id: visitante.id, plantilla_id: t.plantillas[visitante.campeonato_equipo_id] } }]
    comprobar('Aplicar corrección deportiva real', await arbitro('POST', `/api/correcciones/${c.id}/aplicar`, { operaciones }))
    const nueva = await clasificacion(t)
    assert.equal(nueva.clasificacion_vigente, false); assert.notEqual(nueva.huella, t.clasificacion.huella)
    assert.notDeepEqual(nueva.candidatos, t.clasificacion.candidatos)
    comprobar('Confirmación obsoleta rechazada', await admin('POST', `/api/fases/${t.fase}/clasificar-grupos`, { confirmacion: t.clasificacion.confirmacion }), 409)
    assert.equal(comprobar('Reclasificar tras recarga', await admin('POST', `/api/fases/${t.fase}/clasificar-grupos`, { confirmacion: nueva.confirmacion })).clasificacion_vigente, true)
    assert.equal((await auditoria(t)).filter(a => a.accion === 'invalidar_clasificacion_grupos').length, 1)
    comprobar('Revocar árbitro ficticio', await admin('DELETE', `/api/campeonatos/${t.id}/arbitro`, { motivo_revocacion: 'Fin del ensayo de grupos 5.2.' }))
    comprobar('Árbitro revocado pierde tabla', await arbitro('GET', `/api/grupos/${p.grupo_id}/tabla`), 403)
    comprobar('Árbitro revocado pierde auditoría', await arbitro('GET', `/api/fases/${t.fase}/auditoria`), 403)
  } else if (modo === 'carreras') {
    const t = await fase(await crear('Carreras específicas de grupos', 4))
    await vista(t)
    const carreras = []
    carreras.push(await carrera('distribucion', t, `/api/fases/${t.fase}/confirmar-grupos`, t.envio, 201))
    const f = comprobar('Distribución concurrente única', await admin('GET', `/api/fases/${t.fase}`))
    assert.equal(f.grupos.length, 2); assert.equal(f.equipos.length, 4)
    carreras.push(await carrera('calendario', t, `/api/fases/${t.fase}/generar-calendario-grupos`, undefined, 201))
    const partidos = comprobar('Calendario concurrente único', await admin('GET', `/api/campeonatos/${t.id}/partidos`))
    assert.equal(partidos.length, 2)
    for (const p of partidos) await oficializar(t, p)
    const v = await clasificacion(t)
    carreras.push(await carrera('clasificacion', t, `/api/fases/${t.fase}/clasificar-grupos`, { confirmacion: v.confirmacion }, 200))
    const a = await auditoria(t)
    for (const accion of ['confirmar_grupos', 'generar_calendario_grupos', 'clasificar_grupos']) assert.equal(a.filter(x => x.accion === accion).length, 1)
    assert.equal((await clasificacion(t)).clasificacion_vigente, true)
    await guardar('concurrencia', { ok: true, carreras, auditorias_unicas: true, grupos: 2, miembros: 4, partidos: 2 })
  }
  await guardar('http-' + modo, { ok: true, modo, comprobaciones: pruebas.length, pruebas })
  console.log(JSON.stringify({ ok: true, modo, comprobaciones: pruebas.length }))
} catch (error) {
  await guardar('http-' + modo, { ok: false, modo, comprobaciones: pruebas.length, pruebas, error: error.message })
  throw error
}
