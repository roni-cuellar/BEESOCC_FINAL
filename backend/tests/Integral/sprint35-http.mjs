// HTTP real: cookies y CSRF de Sanctum; el servidor verifica el aislamiento en cada petición.
import assert from 'node:assert/strict'
import { readFile, writeFile } from 'node:fs/promises'
const contrasena = process.env.BEESOCC_INTEGRAL_PASSWORD
assert.ok(contrasena, 'Define BEESOCC_INTEGRAL_PASSWORD para el laboratorio de pruebas.')

const carpeta = process.env.BEESOCC_INTEGRAL_DIR
assert.match(carpeta || '', /beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/)
const entorno = JSON.parse(await readFile(carpeta + '/entorno.json', 'utf8'))
assert.equal(entorno.DB_HOST, '127.0.0.1'); assert.equal(entorno.DB_PORT, '3317')
assert.match(entorno.DB_DATABASE, /^beesocc_23c_test_/)
assert.match(entorno.DB_USERNAME, /^beesocc23c_/)
const pruebas = []
function comprobar(nombre, respuesta, estado) {
  assert.equal(respuesta.estado, estado, nombre)
  pruebas.push({ prueba: nombre, estado })
  return respuesta.datos
}
async function sesion(rol, correo) {
  const archivo = carpeta + '/sprint35-cookie-' + rol + '.json'
  let cookies = {}
  try { cookies = JSON.parse(await readFile(archivo, 'utf8')) } catch { /* Sesión nueva del laboratorio. */ }
  async function pedir(metodo, ruta, datos, sinCsrf = false) {
    const headers = { Accept: 'application/json', Origin: 'http://beesocc23c-arbitro.localhost:5175', Cookie: Object.entries(cookies).map(([k,v]) => k + '=' + v).join('; ') }
    if (datos) headers['Content-Type'] = 'application/json'
    if (!sinCsrf && cookies['XSRF-TOKEN']) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies['XSRF-TOKEN'])
    const respuesta = await fetch('http://127.0.0.1:8013' + ruta, { method: metodo, headers, body: datos && JSON.stringify(datos) })
    for (const cookie of respuesta.headers.getSetCookie()) {
      const par = cookie.split(';')[0], indice = par.indexOf('=')
      cookies[par.slice(0,indice)] = par.slice(indice+1)
    }
    await writeFile(archivo, JSON.stringify(cookies))
    return { estado: respuesta.status, datos: await respuesta.json().catch(() => null) }
  }
  if (!Object.keys(cookies).some(k => k.startsWith('beesocc23c_'))) {
    comprobar('CSRF real ' + rol, await pedir('GET','/sanctum/csrf-cookie'),204)
    comprobar('Login real ' + rol, await pedir('POST','/api/login',{email:correo+'@beesocc23c.test',password:contrasena}),200)
  }
  return pedir
}
const admin = await sesion('admin','admin'), arbitro = await sesion('arbitro','arbitro-a'), ajeno = await sesion('ajeno','ajeno')
const ruta = '/api/partidos/1'
const operaciones = [{ accion:'corregir_minuto',evento_id:3,minuto:30 }]
if (process.argv[2] === 'activo') {
  const respuesta = await fetch('http://127.0.0.1:8013' + ruta + '/correcciones',{headers:{Accept:'application/json',Origin:'http://beesocc23c-arbitro.localhost:5175'}})
  comprobar('Invitado no consulta expediente', {estado:respuesta.status},401)
  comprobar('Escritura requiere CSRF incluso con sesión',await arbitro('POST',ruta+'/correcciones',{motivo:'Ensayo',alcance:'eventos'},true),419)
  const partido = comprobar('Resultado oficial consultado',await arbitro('GET',ruta),200)
  assert.equal(partido.estado,'finalizado'); assert.ok(partido.oficializado_at)
  const eventos = comprobar('Acta corregida desde React',await arbitro('GET',ruta+'/eventos'),200)
  assert.equal(eventos.length,1); assert.equal(eventos[0].tipo,'autogol'); assert.equal(eventos[0].minuto,22)
  const marcador = comprobar('Marcador real posterior',await arbitro('GET',ruta+'/marcador'),200)
  assert.equal(marcador.local.goles,1); assert.equal(marcador.visitante.goles,0)
  const goleadores = comprobar('Estadísticas sin gol eliminado',await admin('GET','/api/campeonatos/1/estadisticas/goleadores'),200)
  const asistencias = comprobar('Estadísticas sin asistencia eliminada',await admin('GET','/api/campeonatos/1/estadisticas/asistencias'),200)
  assert.equal(goleadores.resultados.length,0); assert.equal(asistencias.resultados.length,0)
  const correcciones = comprobar('Expedientes creados desde React',await arbitro('GET',ruta+'/correcciones'),200)
  assert.equal(correcciones.find(c=>c.id===1).estado,'consumida'); assert.equal(correcciones.find(c=>c.id===2).estado,'rechazada')
  comprobar('No reutiliza autorización consumida',await arbitro('POST','/api/correcciones/1/aplicar',{operaciones}),409)
  comprobar('No aplica solicitud rechazada',await arbitro('POST','/api/correcciones/2/aplicar',{operaciones}),409)
  comprobar('No resuelve nuevamente el rechazo',await admin('PATCH','/api/correcciones/2/autorizar',{estado:'aprobada'}),409)
  comprobar('Árbitro no decide solicitudes',await arbitro('PATCH','/api/correcciones/2/autorizar',{estado:'aprobada'}),403)
  comprobar('Administrador no solicita como árbitro',await admin('POST',ruta+'/correcciones',{motivo:'Ensayo',alcance:'eventos'}),403)
  comprobar('Administrador no aplica',await admin('POST','/api/correcciones/1/aplicar',{operaciones}),403)
  for (const sufijo of ['/correcciones','/auditoria']) comprobar('Privacidad frente a ajeno '+sufijo,await ajeno('GET',ruta+sufijo),403)
  comprobar('Ajeno no solicita',await ajeno('POST',ruta+'/correcciones',{motivo:'Ensayo',alcance:'eventos'}),403)
  comprobar('Ajeno no aplica',await ajeno('POST','/api/correcciones/1/aplicar',{operaciones}),403)
  comprobar('Expediente inexistente',await arbitro('POST','/api/correcciones/999999/aplicar',{operaciones}),404)
  comprobar('Motivo obligatorio',await arbitro('POST',ruta+'/correcciones',{motivo:'',alcance:'eventos'}),422)
  comprobar('Alcance no implementado rechazado',await arbitro('POST',ruta+'/correcciones',{motivo:'Ensayo',alcance:'resultado'}),422)
  comprobar('Escritura ordinaria cerrada',await arbitro('POST',ruta+'/eventos',{tipo:'falta',minuto:40,equipo_partido_id:1,plantilla_id:1}),409)
  comprobar('No reabre resultado oficial',await arbitro('PATCH',ruta,{estado:'en_curso'}),409)
  const auditoria = comprobar('Auditoría privada real',await admin('GET',ruta+'/auditoria'),200)
  const aplicada = auditoria.filter(a=>a.accion==='aplicar_correccion')
  assert.equal(aplicada.length,1); assert.equal(aplicada[0].usuario_id,2)
  assert.equal(aplicada[0].anterior_json.eventos.length,3); assert.equal(aplicada[0].posterior_json.eventos.length,1)
  const resolucion = auditoria.find(a=>a.accion==='resolver_correccion' && a.posterior_json.correccion.id===1)
  assert.equal(resolucion.usuario_id,1)
  const nueva = comprobar('Aprobación para fallo atómico adicional',await arbitro('POST',ruta+'/correcciones',{motivo:'Ensayo HTTP de atomicidad',alcance:'eventos'}),201).correccion
  comprobar('Administrador aprueba ensayo',await admin('PATCH','/api/correcciones/'+nueva.id+'/autorizar',{estado:'aprobada'}),200)
  const antes = (await admin('GET',ruta+'/auditoria')).datos
  comprobar('404 tardío revierte primera operación',await arbitro('POST','/api/correcciones/'+nueva.id+'/aplicar',{operaciones:[...operaciones,{accion:'eliminar',evento_id:99999999}]}),404)
  assert.deepEqual((await arbitro('GET',ruta+'/eventos')).datos,eventos)
  assert.deepEqual((await admin('GET',ruta+'/auditoria')).datos,antes)
  comprobar('422 tardío revierte primera operación',await arbitro('POST','/api/correcciones/'+nueva.id+'/aplicar',{operaciones:[...operaciones,{accion:'registrar',datos:{tipo:'inventado',equipo_partido_id:1,plantilla_id:1,minuto:20}}]}),422)
  assert.deepEqual((await arbitro('GET',ruta+'/eventos')).datos,eventos)
  assert.deepEqual((await admin('GET',ruta+'/auditoria')).datos,antes)
  assert.equal((await arbitro('GET',ruta+'/correcciones')).datos.find(c=>c.id===nueva.id).estado,'aprobada')
  await writeFile(carpeta+'/sprint35-fixture.json',JSON.stringify({pendiente:nueva.id,eventos,oficializado_at:partido.oficializado_at}))
} else if (process.argv[2] === 'revocado') {
  const fixture=JSON.parse(await readFile(carpeta+'/sprint35-fixture.json','utf8'))
  comprobar('Sesión sigue vigente tras revocar',await arbitro('GET','/api/usuario'),200)
  for (const sufijo of ['/correcciones','/auditoria']) comprobar('Revocado sin lectura '+sufijo,await arbitro('GET',ruta+sufijo),403)
  comprobar('Revocado no solicita',await arbitro('POST',ruta+'/correcciones',{motivo:'Ensayo',alcance:'eventos'}),403)
  comprobar('Revocado no consume aprobación pendiente',await arbitro('POST','/api/correcciones/'+fixture.pendiente+'/aplicar',{operaciones}),403)
  assert.deepEqual((await admin('GET',ruta+'/eventos')).datos,fixture.eventos)
  assert.equal((await admin('GET',ruta)).datos.oficializado_at,fixture.oficializado_at)
} else throw new Error('Fase inválida')
const evidencia = {entorno:'HTTP real, Sanctum y MariaDB aislada',base:entorno.DB_DATABASE,fase:process.argv[2],comprobaciones:pruebas.length,pruebas}
await writeFile(new URL('./sprint35-http-'+process.argv[2]+'.json',import.meta.url),JSON.stringify(evidencia,null,2))
console.log(JSON.stringify({ok:true,base:entorno.DB_DATABASE,fase:process.argv[2],comprobaciones:pruebas.length}))
