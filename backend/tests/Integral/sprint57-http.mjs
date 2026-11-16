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
const fixture = JSON.parse(await readFile(carpeta + '/sprint57-fixture.json', 'utf8'))
const clave = createHmac('sha256', entorno.APP_KEY).update('sprint57-login').digest('hex')
const modo = process.argv[2]
assert.ok(['inscribir', 'preparar', 'restantes', 'liguilla-empate', 'liguilla-resolver', 'corregir-clasificacion', 'corregir-cuadro', 'preparar-avance', 'seguridad', 'revocar', 'compatibilidad'].includes(modo))
try {
  const anterior = JSON.parse(await readFile(`${carpeta}/sprint57-http-${modo}.json`, 'utf8'))
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
  const archivoSesion = `${carpeta}/sprint57-sesion-${rol}.json`
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
const guardar = (nombre, datos) => writeFile(`${carpeta}/sprint57-${nombre}.json`, JSON.stringify(datos, null, 2))
const leer = async nombre => JSON.parse(await readFile(`${carpeta}/sprint57-${nombre}.json`, 'utf8'))
async function crear(nombre, cantidad, formato = 'grupos_eliminacion') {
  const c = comprobar('Crear campeonato ficticio ' + nombre, await admin('POST', '/api/campeonatos', { nombre: 'Laboratorio 5.7 ' + nombre, temporada: '2026', formato }), 201).campeonato
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
async function invertirGol(t, p) {
  const eventos = comprobar('Leer eventos oficiales', await arbitro('GET', `/api/partidos/${p.id}/eventos`))
  const gol = eventos.find(e => e.tipo === 'gol')
  assert.ok(gol)
  const rival = p.participantes.find(x => x.id !== gol.equipo_partido_id)
  const solicitudes = comprobar('Consultar expedientes existentes', await arbitro('GET', `/api/partidos/${p.id}/correcciones`))
  let c = solicitudes.find(x => x.estado === 'aprobada')
  if (!c) {
    c = comprobar('Solicitar corrección oficial ficticia', await arbitro('POST', `/api/partidos/${p.id}/correcciones`, { motivo: 'Reasignación ficticia para integración 5.7', alcance: 'eventos' }), 201).correccion
    comprobar('Aprobar expediente', await admin('PATCH', `/api/correcciones/${c.id}/autorizar`, { estado: 'aprobada' }))
  }
  return { id: c.id, operaciones: [{ accion: 'eliminar', evento_id: gol.id }, { accion: 'registrar', datos: { tipo: 'gol', minuto: 23, equipo_partido_id: rival.id, plantilla_id: t.plantillas[rival.campeonato_equipo_id] } }] }
}
const aplicar = c => arbitro('POST', `/api/correcciones/${c.id}/aplicar`, { operaciones: c.operaciones })
const leerFase = async id => comprobar('Leer fase real', await admin('GET', `/api/fases/${id}`))
const idsCuadro = f => f.rondas.flatMap(r => r.llaves.flatMap(l => [l.id, ...l.partidos.flatMap(p => [p.id, ...p.participantes.map(x => x.id)])]))

// Preparaciones largas por red real; la navegación usa la aplicación normal.
try {
  if (modo === 'compatibilidad') {
    const casos = await leer('casos')
    const liga = await crear('Liga compatible', 3, 'todos_contra_todos')
    comprobar('Calendario todos contra todos', await admin('POST', `/api/campeonatos/${liga.id}/generar-calendario`), 201)
    liga.partidos = comprobar('Partidos regulares compatibles', await admin('GET', `/api/campeonatos/${liga.id}/partidos`))
    assert.equal(liga.partidos.length, 3)
    assert.ok(liga.partidos.every(p => p.fase_id === null && p.grupo_id === null && p.llave_id === null))
    await oficializar(liga, liga.partidos[0])
    assert.equal(comprobar('Tabla compatible', await admin('GET', `/api/campeonatos/${liga.id}/tabla-posiciones`)).tabla[0].PTS, 3)
    casos.liga = liga; await guardar('casos', casos)
    const copa = await crear('Eliminación compatible', 4, 'eliminacion_directa')
    copa.fase = comprobar('Fase eliminatoria compatible', await admin('POST', `/api/campeonatos/${copa.id}/fases`, { nombre: 'Eliminación', tipo: 'eliminacion' }), 201).fase.id
    const datos = { siembra: copa.ids, rondas: Array.from({ length: 2 }, () => ({ modalidad: 'unico', desempate: 'penales' })) }
    const v = comprobar('Previsualizar eliminación directa', await admin('POST', `/api/fases/${copa.fase}/previsualizar-llaves`, datos))
    comprobar('Generar eliminación directa', await admin('POST', `/api/fases/${copa.fase}/generar-llaves`, { ...datos, confirmacion: v.confirmacion }), 201)
    const f = await leerFase(copa.fase)
    await oficializar(copa, f.rondas[0].llaves[0].partidos[0])
    casos.copa = copa; await guardar('casos', casos)
    casos.manual = await crear('Distribución manual', 4); await guardar('casos', casos)
  }
  if (modo === 'seguridad') {
    const t = await leer('principal'), antes = await clasificacion(t)
    const anonimo = await fetch(`http://127.0.0.1:8013/api/fases/${t.fase}`, { headers: { Accept: 'application/json' } })
    comprobar('Sin sesión no accede a fase privada', { estado: anonimo.status }, 401)
    comprobar('Usuario ajeno no consulta datos privados', await ajeno('GET', `/api/fases/${t.fase}`), 403)
    comprobar('Árbitro no genera grupos', await arbitro('POST', `/api/fases/${t.fase}/generar-calendario-grupos`), 403)
    comprobar('Recurso inexistente', await admin('GET', '/api/fases/999999'), 404)
    comprobar('CSRF real obligatorio', await admin('POST', `/api/fases/${t.fase}/clasificar-grupos`, {}, true), 419)
    comprobar('Contrato inválido', await admin('POST', `/api/fases/${t.fase}/previsualizar-eliminacion`, { metodo: 'invalido' }), 422)
    comprobar('Segunda generación rechazada', await admin('POST', `/api/fases/${t.fase}/generar-eliminacion`, { metodo: 'automatico', rondas: [{ modalidad: 'unico', desempate: 'penales' }, { modalidad: 'unico', desempate: 'penales' }], sorteo: ['g1', 'g2'], confirmacion_sorteo: '0'.repeat(64), cruces: [['g1', 'g2']], confirmacion: '0'.repeat(64) }), 409)
    const cuadro = await leerFase(t.eliminacion)
    const final = cuadro.rondas.at(-1).llaves[0].partidos[0]
    assert.ok(final, 'Avance desde React debe haber generado la final')
    if (final.estado === 'programado') await iniciar(t, final, 0, 0)
    const c = await invertirGol(t, t.partidos[2])
    const eventosAntes = comprobar('Snapshot de eventos antes del conflicto', await arbitro('GET', `/api/partidos/${t.partidos[2].id}/eventos`))
    comprobar('Corrección incompatible bloqueada', await aplicar(c), 409)
    const eventosDespues = comprobar('Eventos conservados tras rollback', await arbitro('GET', `/api/partidos/${t.partidos[2].id}/eventos`))
    assert.deepEqual(eventosDespues, eventosAntes)
    assert.equal((await clasificacion(t)).huella, antes.huella)
    assert.deepEqual(idsCuadro(await leerFase(t.eliminacion)), idsCuadro(cuadro))
    const solicitudes = comprobar('Autorización no consumida', await arbitro('GET', `/api/partidos/${t.partidos[2].id}/correcciones`))
    const aprobada = solicitudes.find(x => x.id === c.id)
    assert.equal(aprobada.estado, 'aprobada'); assert.equal(aprobada.fecha_consumo, null)
  }
  if (modo === 'revocar') {
    const t = await leer('principal')
    comprobar('Revocar autorización ficticia', await admin('DELETE', `/api/campeonatos/${t.id}/arbitro`, { motivo_revocacion: 'Fin de autorización ficticia Sprint 5.7' }))
    comprobar('Árbitro revocado no consulta fase', await arbitro('GET', `/api/fases/${t.fase}`), 403)
    comprobar('Árbitro revocado no modifica actas', await arbitro('PATCH', `/api/partidos/${t.partidos[0].id}`, { estado: 'en_curso' }), 403)
  }
  if (modo.startsWith('corregir-')) {
    const t = await leer('principal'), antes = await clasificacion(t)
    const f = modo === 'corregir-cuadro' ? await leerFase(antes.transicion_eliminacion.fase_eliminacion_id) : null
    const c = await invertirGol(t, t.partidos[1])
    comprobar('Aplicar corrección compatible y transaccional', await aplicar(c))
    const despues = await clasificacion(t)
    assert.notEqual(antes.huella, despues.huella)
    if (f) {
      const posterior = await leerFase(f.id)
      assert.deepEqual(idsCuadro(posterior), idsCuadro(f))
      t.eliminacion = f.id; await guardar('principal', t)
      assert.equal(despues.clasificacion_vigente, true)
    } else assert.equal(despues.clasificacion_vigente, false)
    assert.ok((await auditoria(t)).length)
  }
  if (modo.startsWith('liguilla-')) {
    const casos = await leer('casos'), t = casos.liguilla
    const f = await leerFase(t.fase), grupo = f.grupos[0]
    const expedientes = comprobar('Consultar expedientes de liguilla real', await admin('GET', `/api/grupos/${grupo.id}/desempates`))
    const expediente = expedientes.reduce((actual, siguiente) => siguiente.id > actual.id ? siguiente : actual)
    assert.equal(expediente.llave_id, null)
    t.extras = expediente.partidos
    for (const p of t.extras) {
      assert.equal(p.estado, 'programado')
      if (modo === 'liguilla-empate') await oficializar(t, p, 0, 0)
      else {
        const local = p.participantes.find(x => x.rol === 'local').campeonato_equipo_id
        const visita = p.participantes.find(x => x.rol === 'visitante').campeonato_equipo_id
        await oficializar(t, p, local < visita ? 1 : 0, local < visita ? 0 : 1)
      }
    }
    const c = await clasificacion(t)
    assert.equal(c.lista_para_clasificar, modo === 'liguilla-resolver')
    assert.equal(c.tablas[0].tabla.every(x => x.PJ === 2), true)
    casos.liguilla = t; await guardar('casos', casos)
  }
  if (modo === 'preparar-avance') {
    const t = await leer('principal'), f = await leerFase(t.eliminacion)
    const p = f.rondas[0].llaves.flatMap(l => l.partidos).find(p => p.estado === 'programado')
    assert.ok(p)
    const g3 = t.partidos[2].participantes.find(x => x.rol === 'local').campeonato_equipo_id
    const visitaG3 = p.participantes.some(x => x.rol === 'visitante' && x.campeonato_equipo_id === g3)
    await oficializar(t, p, visitaG3 ? 0 : 1, visitaG3 ? 1 : 0)
    t.semifinal = p.id; await guardar('principal', t)
  }
  if (modo === 'inscribir') {
    const campeonatos = comprobar('Consultar campeonato creado desde React', await admin('GET', '/api/campeonatos'))
    const c = campeonatos.find(c => c.nombre === 'Copa real 5.7 navegador')
    assert.ok(c)
    const t = { id: c.id, ids: [], plantillas: {}, eventos: {} }
    for (const equipo of fixture.equipos.slice(0, 6)) {
      const i = comprobar('Inscripción preparatoria por HTTP', await admin('POST', `/api/campeonatos/${c.id}/inscripciones-equipos`, { equipo_id: equipo.equipo_id }), 201).inscripcion
      t.ids.push(i.id)
      t.plantillas[i.id] = comprobar('Plantilla preparatoria por HTTP', await admin('POST', `/api/inscripciones-equipos/${i.id}/plantillas`, { jugador_id: equipo.jugador_id, dorsal: 1, posicion: 'Delantero', fecha_ingreso: '2026-01-01' }), 201).plantilla.id
    }
    comprobar('Asignación arbitral preparatoria', await admin('POST', `/api/campeonatos/${c.id}/arbitro`, { usuario_id: fixture.usuarios.arbitro.id }), 201)
    await guardar('principal', t)
  }
  if (modo === 'preparar') {
    const casos = {}
    for (const [nombre, cantidad, cupos] of [['mini', 4, 2], ['liguilla', 3, 2]]) {
      const t = await fase(await crear(nombre, cantidad), 1, cupos)
      await confirmar(t); t.partidos = (await calendario(t)).partidos
      const marcadores = { '0:1': [1, 0], '0:2': [0, 3], '1:2': [2, 0], '0:3': [5, 0], '1:3': [4, 2], '2:3': [3, 1] }
      for (const p of t.partidos) {
        const local = t.ids.indexOf(p.participantes.find(x => x.rol === 'local').campeonato_equipo_id)
        const visita = t.ids.indexOf(p.participantes.find(x => x.rol === 'visitante').campeonato_equipo_id)
        let goles = nombre === 'mini' ? marcadores[[local, visita].sort((a, b) => a - b).join(':')] : [0, 0]
        if (local > visita) goles = [...goles].reverse()
        await oficializar(t, p, ...goles)
      }
      casos[nombre] = t; await guardar('casos', casos)
      const c = await clasificacion(t)
      if (nombre === 'mini') assert.deepEqual(c.candidatos.map(x => x.campeonato_equipo_id), [t.ids[2], t.ids[1]])
      else assert.equal(c.lista_para_clasificar, false)
    }
  }
  if (modo === 'restantes') {
    const t = await leer('principal')
    const fases = comprobar('Fase creada en navegador', await admin('GET', `/api/campeonatos/${t.id}/fases`))
    t.fase = fases.find(f => f.tipo === 'grupos').id
    const f = comprobar('Calendario confirmado en React', await admin('GET', `/api/fases/${t.fase}`))
    t.partidos = f.grupos.flatMap(g => g.partidos)
    assert.equal(t.partidos.length, 3)
    t.eventos[t.partidos[2].id] = await oficializar(t, t.partidos[2])
    await guardar('principal', t)
  }
  await guardar('http-' + modo, { ok: true, modo, comprobaciones: pruebas.length, pruebas })
  console.log(JSON.stringify({ ok: true, modo, comprobaciones: pruebas.length }))
} catch (error) {
  await guardar('http-' + modo, { ok: false, modo, comprobaciones: pruebas.length, pruebas, error: error.message })
  throw error
}
