// Laboratorio conservado de 6.3: no restaura datos ni ejecuta migraciones.
import assert from 'node:assert/strict'
import { readFile, writeFile } from 'node:fs/promises'
import { createHmac } from 'node:crypto'
import { loginConEspera } from './sprint52-cliente.mjs'

const carpeta = process.env.BEESOCC_INTEGRAL_DIR
assert.match(carpeta || '', /beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/)
const entorno = JSON.parse(await readFile(`${carpeta}/entorno.json`, 'utf8'))
assert.equal(entorno.DB_HOST, '127.0.0.1'); assert.equal(entorno.DB_PORT, '3317')
assert.match(entorno.DB_DATABASE, /^beesocc_23c_test_/)
assert.match(entorno.DB_USERNAME, /^beesocc23c_/)
const casos = JSON.parse(await readFile(`${carpeta}/cierre63-casos.json`, 'utf8'))
const modo = process.argv[2]
assert.ok(['preparar', 'cuadro', 'ocultar'].includes(modo))
const evidencia = `${carpeta}/sprint64-${modo}.json`
try { await readFile(evidencia); throw new Error('Modo aprobado existente: no repetir') }
catch (e) { if (e.code !== 'ENOENT') throw e }
let cookies = JSON.parse(await readFile(`${carpeta}/cierre63-sesion-admin.json`, 'utf8'))
const pruebas = []
async function pedir(metodo, ruta, datos, privada = false) {
  assert.ok(ruta.startsWith('/api/') || ruta === '/sanctum/csrf-cookie')
  const headers = { Accept: 'application/json', Origin: 'http://beesocc23c-admin.localhost:5175' }
  if (privada) {
    headers.Cookie = Object.entries(cookies).map(([k, v]) => `${k}=${v}`).join('; ')
    if (cookies['XSRF-TOKEN']) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies['XSRF-TOKEN'])
  }
  if (datos !== undefined) headers['Content-Type'] = 'application/json'
  const r = await fetch('http://127.0.0.1:8013' + ruta, { method: metodo, headers, body: datos === undefined ? undefined : JSON.stringify(datos), signal: AbortSignal.timeout(30000) })
  if (privada) for (const valor of r.headers.getSetCookie()) {
    const par = valor.split(';')[0], i = par.indexOf('='); cookies[par.slice(0, i)] = par.slice(i + 1)
  }
  return { estado: r.status, datos: await r.json().catch(() => null), reintentarEn: Number(r.headers.get('retry-after')) }
}
function comprobar(nombre, r, estado = 200) {
  assert.equal(r.estado, estado, `${nombre}: estado inesperado ${r.estado}`)
  pruebas.push({ nombre, estado })
  return r.datos
}
function comprobarPrivacidad(datos) {
  for (const [campo, valor] of Object.entries(datos || {})) {
    assert.ok(!['email', 'password', 'fecha_nacimiento', 'administrador_id', 'motivo_administrativo', 'motivo_inactividad', 'motivo', 'auditoria', 'autorizacion_id', 'confirmacion', 'huella', 'clave'].includes(campo), 'Campo privado: ' + campo)
    if (valor && typeof valor === 'object') comprobarPrivacidad(valor)
  }
}
const admin = (metodo, ruta, datos) => pedir(metodo, ruta, datos, true)
if ((await admin('GET', '/api/usuario')).estado !== 200) {
  comprobar('CSRF real del administrador ficticio', await admin('GET', '/sanctum/csrf-cookie'), 204)
  const clave = createHmac('sha256', entorno.APP_KEY).update('cierre63-login').digest('hex')
  await loginConEspera(admin, { email: 'admin@cierre63.example.test', password: clave }, () => {})
}
comprobar('Visitante sin sesión', await pedir('GET', '/api/usuario'), 401)
const rutas = ['', '/deporte', '/equipos', '/partidos', '/estadisticas/goleadores']
if (modo === 'preparar') {
  for (const clave of ['liga', 'directa', 'grupos']) {
    const t = casos[clave], base = `/api/publico/campeonatos/${t.id}`
    comprobar('Publicar solo campeonato ficticio ' + clave, await admin('PATCH', `/api/campeonatos/${t.id}/publicacion`, { publicado: true }))
    for (const ruta of rutas) {
      const datos = comprobar('Consulta pública ' + clave + ruta, await pedir('GET', base + ruta))
      comprobarPrivacidad(datos)
      if (ruta === '/deporte') {
        assert.equal(datos.campeon, null)
        if (clave === 'grupos') assert.equal(datos.fases[0].clasificacion.clasificacion_vigente, true)
        if (clave === 'directa') assert.equal(datos.fases[0].rondas[1].llaves[0].partidos.length, 1)
      }
    }
    const partidos = comprobar('Calendario y resultado administrativo ' + clave, await pedir('GET', base + '/partidos'))
    assert.ok(partidos.some(p => p.tipo_resultado === 'administrativo' && p.marcador_deportivo))
    if (clave === 'liga') comprobar('Tabla pública liga', await pedir('GET', base + '/tabla'))
  }
  comprobar('Contrato de solo lectura', await pedir('POST', `/api/publico/campeonatos/${casos.grupos.id}/deporte`, {}), 405)
  comprobar('API privada conserva Sanctum', await pedir('GET', `/api/campeonatos/${casos.grupos.id}/fases`), 401)
  comprobar('Partido de otro campeonato no se filtra', await pedir('GET', `/api/publico/campeonatos/${casos.liga.id}/partidos/${casos.directa.partidos[0].id}`), 404)
}
if (modo === 'ocultar') {
  const id = casos.grupos.id
  comprobar('Ocultar campeonato ficticio', await admin('PATCH', `/api/campeonatos/${id}/publicacion`, { publicado: false }))
  for (const ruta of [...rutas, '/tabla']) comprobar('Ocultamiento revoca ' + ruta, await pedir('GET', `/api/publico/campeonatos/${id}${ruta}`), 404)
  const catalogo = comprobar('Catálogo excluye oculto', await pedir('GET', '/api/publico/campeonatos'))
  assert.ok(!catalogo.some(c => c.id === id))
}
if (modo === 'cuadro') {
  const fase = casos.grupos.fase
  const vista = comprobar('Preparar cuadro ficticio desde clasificados', await admin('POST', `/api/fases/${fase}/previsualizar-eliminacion`, { metodo: 'automatico', rondas: [{ modalidad: 'unico', desempate: 'penales' }] }))
  const datos = Object.fromEntries(['metodo', 'rondas', 'sorteo', 'confirmacion_sorteo', 'cruces', 'confirmacion'].map(k => [k, vista[k]]))
  comprobar('Generar solo cuadro ficticio', await admin('POST', `/api/fases/${fase}/generar-eliminacion`, datos), 201)
  const publico = comprobar('Procedencias públicas del cuadro derivado', await pedir('GET', `/api/publico/campeonatos/${casos.grupos.id}/deporte`))
  comprobarPrivacidad(publico)
  const eliminacion = publico.fases.find(f => f.tipo === 'eliminacion')
  assert.equal(eliminacion.rondas[0].llaves[0].plazas[0].origen_tipo, 'grupo_puesto')
}
await writeFile(`${carpeta}/cierre63-sesion-admin.json`, JSON.stringify(cookies))
const resultado = { ok: true, modo, comprobaciones: pruebas.length, pruebas }
await writeFile(evidencia, JSON.stringify(resultado, null, 2))
console.log(JSON.stringify({ ok: true, modo, comprobaciones: pruebas.length }))
