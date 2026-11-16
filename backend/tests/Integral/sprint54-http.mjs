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
const fixture = JSON.parse(await readFile(carpeta + '/sprint54-fixture.json', 'utf8'))
const clave = createHmac('sha256', entorno.APP_KEY).update('sprint54-login').digest('hex')
const modo = process.argv[2]
assert.ok(['funcional', 'carreras', 'compatibilidad'].includes(modo))
try {
  const anterior = JSON.parse(await readFile(`${carpeta}/sprint54-http-${modo}.json`, 'utf8'))
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
const guardar = (nombre, datos) => writeFile(`${carpeta}/sprint54-${nombre}.json`, JSON.stringify(datos, null, 2))
const leer = async nombre => JSON.parse(await readFile(`${carpeta}/sprint54-${nombre}.json`, 'utf8'))
async function crear(nombre, cantidad, formato = 'grupos_eliminacion') {
  const c = comprobar('Crear campeonato ficticio ' + nombre, await admin('POST', '/api/campeonatos', { nombre: 'Laboratorio 5.4 ' + nombre, temporada: '2026', formato }), 201).campeonato
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

const obtenerFase = async t => comprobar('Consultar fase y participantes', await admin('GET', `/api/fases/${t.fase}`))
const tabla = async grupo => comprobar('Tabla regular oficial', await admin('GET', `/api/grupos/${grupo}/tabla?vista=oficial`))
async function escenario(nombre, cantidad = 4, grupos = 2, cupo = 1, marcador = () => [1, 0]) {
  const t = await fase(await crear(nombre, cantidad), grupos, cupo)
  await confirmar(t)
  t.partidos = (await calendario(t)).partidos
  t.eventos = {}
  for (const p of t.partidos) t.eventos[p.id] = await oficializar(t, p, ...marcador(p, t))
  return t
}
async function clasificar(t) {
  const v = await clasificacion(t)
  return comprobar('Confirmar clasificados oficiales', await admin('POST', `/api/fases/${t.fase}/clasificar-grupos`, { confirmacion: v.confirmacion }))
}
async function vistaExtra(grupo, pendiente) {
  return comprobar('Vista de desempate sin escritura', await admin('POST', `/api/grupos/${grupo}/previsualizar-desempate`, { clave: pendiente.clave }))
}
async function generarExtra(grupo, v) {
  return comprobar('Generar desempate confirmado', await admin('POST', `/api/grupos/${grupo}/generar-desempate`, { clave: v.clave, confirmacion: v.confirmacion }), 201).desempate
}
async function vistaCuadro(t, metodo = 'automatico') {
  const c = await clasificacion(t)
  const rondas = Array.from({ length: Math.ceil(Math.log2(c.candidatos.length)) }, () => ({ modalidad: 'unico', desempate: 'penales' }))
  return comprobar('Previsualizar cuadro completo ' + metodo, await admin('POST', `/api/fases/${t.fase}/previsualizar-eliminacion`, { metodo, rondas }))
}
const envioCuadro = v => Object.fromEntries(['metodo', 'rondas', 'sorteo', 'confirmacion_sorteo', 'cruces', 'confirmacion'].map(k => [k, v[k]]))
async function generarCuadro(t, v) {
  return comprobar('Generar cuadro desde procedencias', await admin('POST', `/api/fases/${t.fase}/generar-eliminacion`, envioCuadro(v)), 201).fase
}
async function prepararCorreccion(t, p) {
  const c = comprobar('Solicitar corrección oficial', await arbitro('POST', `/api/partidos/${p.id}/correcciones`, { motivo: 'Reasignar gol ficticio de laboratorio 5.4', alcance: 'eventos' }), 201).correccion
  comprobar('Autorizar corrección oficial', await admin('PATCH', `/api/correcciones/${c.id}/autorizar`, { estado: 'aprobada' }))
  const visitante = p.participantes.find(x => x.rol === 'visitante')
  return { id: c.id, operaciones: [{ accion: 'eliminar', evento_id: t.eventos[p.id][0].id }, { accion: 'registrar', datos: { tipo: 'gol', minuto: 23, equipo_partido_id: visitante.id, plantilla_id: t.plantillas[visitante.campeonato_equipo_id] } }] }
}
const aplicar = c => arbitro('POST', `/api/correcciones/${c.id}/aplicar`, { operaciones: c.operaciones })

async function carrera(caso, t, primera, segunda) {
  const php = process.env.BEESOCC_PHP_BINARY
  assert.match(php || '', /^[A-Za-z]:[\\/]/)
  const prefijo = `${carpeta}/sprint54-barrera-${caso}-${t.id}`
  const proceso = spawn(php, [fileURLToPath(new URL('./sprint54-barrera.php', import.meta.url)), caso, String(t.id)], { env: process.env, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] })
  let error = ''
  proceso.stderr.on('data', x => { error += x })
  const terminado = new Promise((resolve, reject) => { proceso.on('error', reject); proceso.on('exit', codigo => codigo === 0 ? resolve() : reject(new Error('Barrera no acreditada: ' + error))) })
  terminado.catch(() => {})
  try {
    const limite = Date.now() + 15000
    let lista = false
    while (Date.now() < limite) {
      try { await access(prefijo + '-lista.json'); lista = true; break } catch { await new Promise(r => setTimeout(r, 30)) }
    }
    assert.ok(lista, 'La barrera no se inició')
    const respuestas = await Promise.all([primera(), segunda()])
    await terminado
    const evidencia = JSON.parse(await readFile(prefijo + '-resultado.json', 'utf8'))
    verificarBarrera(evidencia)
    return { ...evidencia, respuestas: respuestas.map(r => r.estado), resultados: respuestas }
  } finally { if (proceso.exitCode === null) proceso.kill() }
}

try {
  if (modo === 'funcional') {
    const extra = await escenario('Partido extra con penales', 4, 2, 1, (p, t) => p.participantes.some(x => x.campeonato_equipo_id === t.ids[0]) ? [0, 0] : [1, 0])
    const grupo = extra.partidos.find(p => p.participantes.some(x => x.campeonato_equipo_id === extra.ids[0])).grupo_id
    const antes = await tabla(grupo)
    const v = await vistaExtra(grupo, antes.pendientes_resolucion[0])
    comprobar('Firma de desempate alterada', await admin('POST', `/api/grupos/${grupo}/generar-desempate`, { clave: v.clave, confirmacion: '0'.repeat(64) }), 409)
    const caso = await generarExtra(grupo, v)
    comprobar('Desempate duplicado bloqueado', await admin('POST', `/api/grupos/${grupo}/generar-desempate`, { clave: v.clave, confirmacion: v.confirmacion }), 409)
    await oficializar(extra, caso.partidos[0], 1, 1)
    comprobar('Avance sin tanda bloqueado', await admin('POST', `/api/llaves/${caso.llave_id}/avanzar`), 409)
    const penales = { penales_plaza_1: 4, penales_plaza_2: 3, motivo: 'Tanda ficticia certificada 5.4' }
    comprobar('Administrador no certifica tanda', await admin('POST', `/api/llaves/${caso.llave_id}/penales`, penales), 403)
    comprobar('Árbitro ajeno no certifica tanda', await ajeno('POST', `/api/llaves/${caso.llave_id}/penales`, penales), 403)
    comprobar('Tanda certificada por árbitro', await arbitro('POST', `/api/llaves/${caso.llave_id}/penales`, penales))
    comprobar('Avance administrativo del extra', await admin('POST', `/api/llaves/${caso.llave_id}/avanzar`))
    const despues = await tabla(grupo)
    assert.equal(despues.definitiva, true)
    for (const f of despues.tabla) { assert.equal(f.PJ, 1); assert.equal(f.GF, 0); assert.equal(f.PTS, 1) }
    const goles = comprobar('Estadísticas deportivas sin penales', await admin('GET', `/api/campeonatos/${extra.id}/estadisticas/goleadores`))
    assert.equal(goles.resultados.reduce((n, f) => n + Number(f.total), 0), 3)
    await clasificar(extra)
    const cuadroExtra = await generarCuadro(extra, await vistaCuadro(extra))
    const final = cuadroExtra.rondas[0].llaves[0]
    assert.ok(final.plazas.every(p => p.origen_tipo === 'grupo_puesto' && p.origen_puesto === 1))
    await oficializar(extra, final.partidos[0])
    comprobar('Avanzar ganador oficial de final', await admin('POST', `/api/llaves/${final.id}/avanzar`))
    assert.equal(comprobar('Fase eliminatoria finalizada', await admin('GET', `/api/fases/${cuadroExtra.id}`)).estado, 'finalizada')

    const multiple = await escenario('Liguilla reiterada confirmada', 3, 1, 2, () => [0, 0])
    const gm = multiple.partidos[0].grupo_id
    let tm = await tabla(gm)
    const liguilla = await generarExtra(gm, await vistaExtra(gm, tm.pendientes_resolucion[0]))
    assert.equal(liguilla.partidos.length, 3)
    for (const p of liguilla.partidos) await oficializar(multiple, p, 0, 0)
    tm = await tabla(gm)
    assert.equal(tm.pendientes_resolucion[0].generable, true)
    assert.equal(comprobar('No hay segunda liguilla automática', await admin('GET', `/api/grupos/${gm}/desempates`)).length, 1)
    const segunda = await generarExtra(gm, await vistaExtra(gm, tm.pendientes_resolucion[0]))
    for (const p of segunda.partidos) {
      const l = p.participantes.find(x => x.rol === 'local').campeonato_equipo_id
      const r = p.participantes.find(x => x.rol === 'visitante').campeonato_equipo_id
      await oficializar(multiple, p, l < r ? 1 : 0, l < r ? 0 : 1)
    }
    tm = await tabla(gm)
    assert.ok(tm.tabla.every(f => f.PJ === 2 && f.GF === 0 && f.PTS === 2))
    assert.equal((await clasificar(multiple)).candidatos.length, 2)

    // Los tres primeros tienen 6 puntos, DG 3 y GF 6. Sus directos los separan.
    const mini = await escenario('Minitabla de tres', 4, 1, 2, (p, t) => {
      const l = t.ids.indexOf(p.participantes.find(x => x.rol === 'local').campeonato_equipo_id)
      const r = t.ids.indexOf(p.participantes.find(x => x.rol === 'visitante').campeonato_equipo_id)
      const resultados = { '0:1': [1, 0], '0:2': [0, 3], '1:2': [2, 0], '0:3': [5, 0], '1:3': [4, 2], '2:3': [3, 1] }
      const par = resultados[`${Math.min(l, r)}:${Math.max(l, r)}`]
      return l < r ? par : [...par].reverse()
    })
    // La minitabla debe ordenar C, B y A sin partidos adicionales.
    const cm = await clasificacion(mini)
    assert.equal(cm.lista_para_clasificar, true)
    assert.deepEqual(cm.tablas[0].tabla.slice(0, 3).map(f => [f.PTS, f.DG, f.GF]), [[6, 3, 6], [6, 3, 6], [6, 3, 6]])
    assert.deepEqual(cm.candidatos.map(f => f.campeonato_equipo_id), [mini.ids[2], mini.ids[1]])
    await clasificar(mini)

    const descansos = await escenario('Descansos y cruces manuales', 6, 3, 2)
    await clasificar(descansos)
    const vd = await vistaCuadro(descansos)
    assert.equal(vd.descansos.length, 2)
    assert.ok(vd.descansos.every(o => o.endsWith(':1')))
    const manual = { ...envioCuadro(vd), metodo: 'manual', cruces: [...vd.cruces].reverse() }
    delete manual.confirmacion
    const vm = comprobar('Confirmar previsualización manual', await admin('POST', `/api/fases/${descansos.fase}/previsualizar-eliminacion`, manual))
    comprobar('Cruces repetidos inválidos', await admin('POST', `/api/fases/${descansos.fase}/previsualizar-eliminacion`, { ...manual, cruces: Array(vd.cruces.length).fill(vd.cruces[0]) }), 422)
    comprobar('Sorteo alterado inválido', await admin('POST', `/api/fases/${descansos.fase}/generar-eliminacion`, { ...envioCuadro(vm), sorteo: [...vm.sorteo].reverse() }), 409)
    const fd = await generarCuadro(descansos, vm)
    assert.equal(fd.rondas[0].llaves.filter(l => l.metodo_resolucion === 'bye').length, 2)
    assert.ok(fd.rondas[0].llaves.every(l => l.plazas.some(p => p.origen_tipo === 'bye') || l.plazas[0].origen_grupo_id !== l.plazas[1].origen_grupo_id))

    const correccion = await escenario('Propagación y rollback')
    await clasificar(correccion)
    const fc = await generarCuadro(correccion, await vistaCuadro(correccion))
    const llave = fc.rondas[0].llaves[0]
    const idsAntes = llave.partidos[0].participantes.map(x => x.id).sort()
    const c = await prepararCorreccion(correccion, correccion.partidos[0])
    comprobar('Propagar corrección hacia partido programado', await aplicar(c))
    const actual = comprobar('IDs conservados tras propagación', await admin('GET', `/api/fases/${fc.id}`))
    const partido = actual.rondas[0].llaves[0].partidos[0]
    assert.equal(partido.id, llave.partidos[0].id)
    assert.equal(actual.rondas[0].llaves[0].id, llave.id)
    assert.deepEqual(partido.participantes.map(x => x.id).sort(), idsAntes)
    assert.notDeepEqual(partido.participantes.map(x => x.campeonato_equipo_id), llave.partidos[0].participantes.map(x => x.campeonato_equipo_id))
    comprobar('Iniciar partido descendiente', await arbitro('PATCH', `/api/partidos/${partido.id}`, { estado: 'en_curso' }))
    const otro = await prepararCorreccion(correccion, correccion.partidos[1])
    const antesConflicto = await clasificacion(correccion)
    comprobar('Conflicto deportivo revierte corrección', await aplicar(otro), 409)
    assert.equal((await clasificacion(correccion)).huella, antesConflicto.huella)
    const solicitudes = comprobar('Autorización permanece aprobada', await arbitro('GET', `/api/partidos/${correccion.partidos[1].id}/correcciones`))
    assert.equal(solicitudes.find(x => x.id === otro.id).estado, 'aprobada')
    assert.ok((await auditoria(correccion)).some(a => a.accion === 'actualizar_clasificacion_grupos'))

    comprobar('Permiso ajeno denegado', await ajeno('POST', `/api/fases/${correccion.fase}/generar-eliminacion`, {}), 403)
    comprobar('Recurso inexistente', await admin('GET', '/api/fases/999999999'), 404)
    comprobar('CSRF obligatorio', await admin('POST', `/api/fases/${correccion.fase}/generar-eliminacion`, {}, true), 419)
    const invitado = await fetch('http://127.0.0.1:8013/api/fases/' + correccion.fase, { headers: { Accept: 'application/json' } })
    comprobar('Sesión obligatoria', { estado: invitado.status }, 401)
    await guardar('casos', { extra, multiple, mini, descansos, correccion })
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
  } else {
    const otroAdmin = await sesion('admin')
    const carreras = []
    const t = await escenario('Carrera de desempate', 2, 1, 2, () => [0, 0])
    const g = t.partidos[0].grupo_id
    const v = await vistaExtra(g, (await tabla(g)).pendientes_resolucion[0])
    const datos = { clave: v.clave, confirmacion: v.confirmacion }
    let r = await carrera('desempate', t, () => admin('POST', `/api/grupos/${g}/generar-desempate`, datos), () => otroAdmin('POST', `/api/grupos/${g}/generar-desempate`, datos, false, 8014))
    assert.deepEqual([...r.respuestas].sort(), [201, 409])
    assert.equal(comprobar('Un solo expediente tras carrera', await admin('GET', `/api/grupos/${g}/desempates`)).length, 1)
    carreras.push(r)
    const e = await escenario('Carrera de generación')
    await clasificar(e)
    const ve = envioCuadro(await vistaCuadro(e))
    r = await carrera('eliminacion', e, () => admin('POST', `/api/fases/${e.fase}/generar-eliminacion`, ve), () => otroAdmin('POST', `/api/fases/${e.fase}/generar-eliminacion`, ve, false, 8014))
    assert.deepEqual([...r.respuestas].sort(), [201, 409])
    assert.equal((await auditoria(e)).filter(a => a.accion === 'generar_eliminacion_grupos').length, 1)
    carreras.push(r)
    for (const generar of [false, true]) {
      const t = await escenario(generar ? 'Generación contra corrección' : 'Clasificación contra corrección')
      const c = await prepararCorreccion(t, t.partidos[0])
      if (generar) await clasificar(t)
      const v = generar ? envioCuadro(await vistaCuadro(t)) : { confirmacion: (await clasificacion(t)).confirmacion }
      const caso = generar ? 'generacion_correccion' : 'clasificacion_correccion'
      // Árbitro y administración usan sesiones y procesos de servidor distintos.
      r = await carrera(caso, t, () => admin('POST', `/api/fases/${t.fase}/${generar ? 'generar-eliminacion' : 'clasificar-grupos'}`, v), () => arbitro('POST', `/api/correcciones/${c.id}/aplicar`, { operaciones: c.operaciones }, false, 8014))
      assert.equal(r.respuestas[1], 200)
      assert.ok([generar ? 201 : 200, 409].includes(r.respuestas[0]))
      const vigente = await clasificacion(t)
      if (generar && r.respuestas[0] === 201) {
        assert.equal(vigente.clasificacion_vigente, true)
        const f = comprobar('Cuadro concurrente conserva clasificados actuales', await admin('GET', `/api/fases/${vigente.transicion_eliminacion.fase_eliminacion_id}`))
        assert.deepEqual(f.rondas[0].llaves[0].partidos[0].participantes.map(x => x.campeonato_equipo_id).sort(), vigente.candidatos.map(x => x.campeonato_equipo_id).sort())
      } else assert.equal(vigente.clasificacion_vigente, false)
      const solicitudes = comprobar('Corrección concurrente consumida una vez', await arbitro('GET', `/api/partidos/${t.partidos[0].id}/correcciones`))
      assert.equal(solicitudes.find(x => x.id === c.id).estado, 'consumida')
      carreras.push(r)
    }
    await guardar('concurrencia', { ok: true, carreras: carreras.map(({ resultados, ...r }) => r) })
  }
  await guardar('http-' + modo, { ok: true, modo, comprobaciones: pruebas.length, pruebas })
  console.log(JSON.stringify({ ok: true, modo, comprobaciones: pruebas.length }))
} catch (error) {
  await guardar('http-' + modo, { ok: false, modo, comprobaciones: pruebas.length, pruebas, error: error.message })
  throw error
}
