// HTTP real con cookies/CSRF, exclusivamente al servidor del laboratorio 8013.
import assert from 'node:assert/strict'
import { readFile, writeFile } from 'node:fs/promises'
const claveLaboratorio = process.env.BEESOCC_LAB_PASSWORD
assert.ok(typeof claveLaboratorio === 'string' && claveLaboratorio.length >= 16, 'Define BEESOCC_LAB_PASSWORD sin guardarla en archivos')
const carpeta = process.env.BEESOCC_INTEGRAL_DIR
assert.match(carpeta || '', /beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/)
const entorno = JSON.parse(await readFile(carpeta + '/entorno.json', 'utf8'))
assert.equal(entorno.DB_HOST, '127.0.0.1'); assert.equal(entorno.DB_PORT, '3317')
assert.match(entorno.DB_USERNAME, /^beesocc23c_/); assert.match(entorno.DB_DATABASE, /^beesocc_23c_test_/)
const fixture = JSON.parse(await readFile(carpeta + '/sprint45-fixture.json', 'utf8'))
const pruebas = []
function comprobar(nombre, r, estado = 200) {
  assert.equal(r.estado, estado, nombre + ': ' + JSON.stringify(r.datos))
  pruebas.push({ prueba: nombre, estado })
  return r.datos
}
async function sesion(rol) {
  const cookies = {}
  async function pedir(metodo, ruta, datos, sinCsrf = false) {
    const headers = { Accept: 'application/json', Origin: 'http://beesocc23c-' + rol + '.localhost:5175', Cookie: Object.entries(cookies).map(([k, v]) => k + '=' + v).join('; ') }
    if (datos) headers['Content-Type'] = 'application/json'
    if (!sinCsrf && cookies['XSRF-TOKEN']) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies['XSRF-TOKEN'])
    const r = await fetch('http://127.0.0.1:8013' + ruta, { method: metodo, headers, body: datos && JSON.stringify(datos) })
    for (const valor of r.headers.getSetCookie()) { const par = valor.split(';')[0], i = par.indexOf('='); cookies[par.slice(0, i)] = par.slice(i + 1) }
    return { estado: r.status, datos: await r.json().catch(() => null) }
  }
  comprobar('CSRF real ' + rol, await pedir('GET', '/sanctum/csrf-cookie'), 204)
  comprobar('Login real ' + rol, await pedir('POST', '/api/login', { email: fixture.usuarios[rol].email, password: claveLaboratorio }))
  return pedir
}
const admin = await sesion('admin'), arbitro = await sesion('arbitro'), ajeno = await sesion('ajeno')
const modo = process.argv[2]
async function cuadro(t) {
  const fases = comprobar('Lista de fases real', await admin('GET', `/api/campeonatos/${t.id}/fases`))
  assert.equal(fases.length, 1)
  return comprobar('Detalle de cuadro real', await admin('GET', `/api/fases/${fases[0].id}`))
}
async function oficializar(t, partido, local = 1, visitante = 0) {
  comprobar('Iniciar encuentro real', await arbitro('PATCH', `/api/partidos/${partido.id}`, { estado: 'en_curso' }))
  const goles = []
  for (const [rol, cantidad] of [['local', local], ['visitante', visitante]]) {
    const participante = partido.participantes.find(p => p.rol === rol)
    for (let i = 0; i < cantidad; i++) goles.push(comprobar('Registrar gol real', await arbitro('POST', `/api/partidos/${partido.id}/eventos`, { tipo: 'gol', minuto: 15 + i, equipo_partido_id: participante.id, plantilla_id: t.plantillas[participante.campeonato_equipo_id] }), 201).evento)
  }
  comprobar('Oficializar encuentro real', await arbitro('POST', `/api/partidos/${partido.id}/finalizar`))
  return goles
}
const avanzar = async l => comprobar('Avance administrativo real', await admin('POST', `/api/llaves/${l.id}/avanzar`)).llave
const uno = { modalidad: 'unico', desempate: 'penales' }

if (modo === 'semifinal') {
  const t = fixture.torneos.copa
  let c = await cuadro(t)
  assert.equal(c.equipos.length, 6); assert.equal(c.rondas.length, 3)
  assert.equal(c.rondas[0].llaves.filter(l => l.metodo_resolucion === 'bye').length, 2)
  assert.equal(c.rondas[0].llaves.flatMap(l => l.partidos).length, 2)
  assert.equal(c.rondas[1].modalidad, 'ida_vuelta'); assert.equal(c.rondas[1].desempate, 'prorroga_penales')
  const sinSesion = await fetch(`http://127.0.0.1:8013/api/fases/${c.id}`, { headers: { Accept: 'application/json', Origin: 'http://beesocc23c-ajeno.localhost:5175' } })
  comprobar('Invitado sin cuadro', { estado: sinSesion.status }, 401)
  comprobar('Ajeno sin cuadro', await ajeno('GET', `/api/fases/${c.id}`), 403)
  comprobar('Ajeno sin auditoría', await ajeno('GET', `/api/fases/${c.id}/auditoria`), 403)
  comprobar('Árbitro autorizado consulta', await arbitro('GET', `/api/fases/${c.id}`))
  comprobar('Fase inexistente', await admin('GET', '/api/fases/9999999'), 404)
  comprobar('Grupos rechazados', await admin('POST', `/api/campeonatos/${t.id}/fases`, { nombre: 'Grupo prohibido', tipo: 'grupos' }), 422)
  const auditoria = comprobar('Auditoría real de generación React', await admin('GET', `/api/fases/${c.id}/auditoria`))
  assert.equal(auditoria.filter(a => a.accion === 'generar_llaves').length, 1, 'Doble clic React genera una sola auditoría')
  const vista = auditoria.find(a => a.accion === 'generar_llaves').posterior_json
  comprobar('Segunda generación bloqueada', await admin('POST', `/api/fases/${c.id}/generar-llaves`, { siembra: vista.siembra, rondas: vista.rondas, confirmacion: vista.confirmacion }), 409)
  const pendiente = c.rondas[0].llaves.find(l => l.estado === 'pendiente')
  comprobar('Árbitro no avanza', await arbitro('POST', `/api/llaves/${pendiente.id}/avanzar`), 403)
  comprobar('CSRF requerido', await admin('POST', `/api/llaves/${pendiente.id}/avanzar`, {}, true), 419)
  // El primer partido debe haberse oficializado desde React, no se sustituye esa prueba.
  assert.equal(pendiente.partidos[0].estado, 'finalizado'); assert.ok(pendiente.partidos[0].oficializado_at)
  for (const l of c.rondas[0].llaves.filter(l => l.estado === 'pendiente')) {
    if (l.partidos[0].estado === 'programado') await oficializar(t, l.partidos[0])
    await avanzar(l)
  }
  c = await cuadro(t)
  for (const [indice, l] of c.rondas[1].llaves.entries()) {
    await oficializar(t, l.partidos[0], 1, 0)
    comprobar('Serie incompleta no avanza', await admin('POST', `/api/llaves/${l.id}/avanzar`), 409)
    await oficializar(t, l.partidos[1], indice === 0 ? 1 : 0, 0)
    if (indice === 0) {
      comprobar('Empate global no avanza', await admin('POST', `/api/llaves/${l.id}/avanzar`), 409)
      const datos = { penales_plaza_1: 4, penales_plaza_2: 3, motivo: 'Ensayo de rechazo' }
      comprobar('Propietario no certifica penales', await admin('POST', `/api/llaves/${l.id}/penales`, datos), 403)
      comprobar('Tanda empatada inválida', await arbitro('POST', `/api/llaves/${l.id}/penales`, { ...datos, penales_plaza_2: 4 }), 422)
    } else await avanzar(l)
  }
  await writeFile(carpeta + '/sprint45-semifinal.json', JSON.stringify({ fase: c.id, llave_penales: c.rondas[1].llaves[0].id }))
} else if (modo === 'correccion') {
  const t = fixture.torneos.copa
  let c = await cuadro(t)
  const l = c.rondas[1].llaves[0]
  assert.equal(l.estado, 'resuelta'); assert.equal(l.penales_plaza_1, 4); assert.equal(l.penales_plaza_2, 3)
  const audit = comprobar('Auditoría de tanda desde React', await admin('GET', `/api/fases/${c.id}/auditoria`))
  assert.equal(audit.filter(a => a.accion === 'registrar_penales').length, 1)
  await oficializar(t, c.rondas[2].llaves[0].partidos[0]); await avanzar(c.rondas[2].llaves[0])
  c = await cuadro(t); assert.equal(c.estado, 'finalizada')
  assert.equal(c.rondas.flatMap(r => r.llaves.flatMap(l => l.partidos)).length, 7)

  const tc = fixture.torneos.correccion
  const f = comprobar('Fase para corrección', await admin('POST', `/api/campeonatos/${tc.id}/fases`, { nombre: 'Corrección de clasificado real', tipo: 'eliminacion' }), 201).fase
  const datos = { siembra: tc.siembra, rondas: [uno, uno] }
  comprobar('Siembra duplicada inválida', await admin('POST', `/api/fases/${f.id}/previsualizar-llaves`, { ...datos, siembra: [tc.siembra[0], tc.siembra[0], ...tc.siembra.slice(2)] }), 422)
  const v = comprobar('Previsualizar cuadro de corrección', await admin('POST', `/api/fases/${f.id}/previsualizar-llaves`, datos))
  comprobar('Confirmación adulterada rechazada', await admin('POST', `/api/fases/${f.id}/generar-llaves`, { ...datos, confirmacion: '0'.repeat(64) }), 409)
  comprobar('Recuperación con confirmación correcta', await admin('POST', `/api/fases/${f.id}/generar-llaves`, { ...datos, confirmacion: v.confirmacion }), 201)
  let cc = await cuadro(tc)
  const [a, b] = cc.rondas[0].llaves
  const goles = await oficializar(tc, a.partidos[0], 2, 1)
  await oficializar(tc, b.partidos[0]); await avanzar(a); await avanzar(b)
  cc = await cuadro(tc)
  const final = cc.rondas[1].llaves[0].partidos[0]
  const local = a.partidos[0].participantes.find(p => p.rol === 'local')
  const operaciones = goles.filter(g => g.equipo_partido_id === local.id).map(g => ({ accion: 'eliminar', evento_id: g.id }))
  const correccion = comprobar('Solicitud arbitral de corrección', await arbitro('POST', `/api/partidos/${a.partidos[0].id}/correcciones`, { motivo: 'Eliminar dos goles duplicados ficticios y revisar clasificado.', alcance: 'eventos' }), 201).correccion
  comprobar('Aprobación administrativa real', await admin('PATCH', `/api/correcciones/${correccion.id}/autorizar`, { estado: 'aprobada' }))
  comprobar('Ajeno no consume autorización', await ajeno('POST', `/api/correcciones/${correccion.id}/aplicar`, { operaciones }), 403)
  // Se conserva el lote para aplicarlo desde el expediente React del árbitro.
  await writeFile(carpeta + '/sprint45-correccion.json', JSON.stringify({ fase: cc.id, partido: a.partidos[0].id, final_id: final.id, final_antes: final.participantes, correccion: correccion.id, operaciones, ganador_antes: cc.rondas[0].llaves[0].ganador_fase_equipo_id }))
  const tl = fixture.torneos.liga
  comprobar('Calendario de liga real', await admin('POST', `/api/campeonatos/${tl.id}/generar-calendario`), 201)
  const partidos = comprobar('Encuentros de liga real', await admin('GET', `/api/campeonatos/${tl.id}/partidos`))
  assert.equal(partidos.length, 3); assert.ok(partidos.every(p => !p.fase_id && !p.llave_id))
  await oficializar(tl, partidos[0])
  const tabla = comprobar('Tabla todos contra todos real', await admin('GET', `/api/campeonatos/${tl.id}/tabla-posiciones`))
  assert.equal(tabla.tabla[0].PTS, 3)
} else if (modo === 'verificar') {
  const t = fixture.torneos.correccion
  const ref = JSON.parse(await readFile(carpeta + '/sprint45-correccion.json', 'utf8'))
  const cc = await cuadro(t)
  assert.notEqual(cc.rondas[0].llaves[0].ganador_fase_equipo_id, ref.ganador_antes)
  assert.equal(cc.rondas[1].llaves[0].partidos[0].id, ref.final_id)
  assert.notDeepEqual(cc.rondas[1].llaves[0].partidos[0].participantes, ref.final_antes)
  const correcciones = comprobar('Expediente consumido desde React', await arbitro('GET', `/api/partidos/${ref.partido}/correcciones`))
  assert.equal(correcciones.find(c => c.id === ref.correccion).estado, 'consumida')
  comprobar('No consumir dos veces', await arbitro('POST', `/api/correcciones/${ref.correccion}/aplicar`, { operaciones: ref.operaciones }), 409)
  const audit = comprobar('Auditoría de cuadro corregido', await admin('GET', `/api/fases/${cc.id}/auditoria`))
  assert.ok(audit.some(a => a.accion === 'actualizar_plaza'))
  const ac = comprobar('Auditoría del partido corregido', await arbitro('GET', `/api/partidos/${ref.partido}/auditoria`))
  assert.ok(ac.some(a => a.accion === 'aplicar_correccion'))
} else if (modo === 'revocado') {
  const c = await cuadro(fixture.torneos.copa)
  comprobar('Revocar árbitro real', await admin('DELETE', `/api/campeonatos/${fixture.torneos.copa.id}/arbitro`, { motivo_revocacion: 'Fin del ensayo aislado Sprint 4.5.' }))
  comprobar('Revocado sin cuadro', await arbitro('GET', `/api/fases/${c.id}`), 403)
  comprobar('Revocado sin auditoría', await arbitro('GET', `/api/fases/${c.id}/auditoria`), 403)
  comprobar('Revocado sin acta', await arbitro('GET', `/api/partidos/${c.rondas[0].llaves[1].partidos[0].id}`), 403)
} else throw new Error('Modo inválido')
const resultado = { ok: true, base: entorno.DB_DATABASE, modo, comprobaciones: pruebas.length, pruebas }
await writeFile(new URL('./sprint45-http-' + modo + '.json', import.meta.url), JSON.stringify(resultado, null, 2))
console.log(JSON.stringify({ ok: true, modo, comprobaciones: pruebas.length }))
