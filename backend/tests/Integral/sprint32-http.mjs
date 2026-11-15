// HTTP real con cookies de Sanctum y CSRF; no utiliza adaptadores simulados.
import assert from 'node:assert/strict'
import { readFile, writeFile } from 'node:fs/promises'
const carpeta = process.env.BEESOCC_INTEGRAL_DIR
assert.match(carpeta || '', /beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/)
const fixture = JSON.parse(await readFile(carpeta + '/sprint32-fixture.json', 'utf8'))
const resultados = []
async function sesion(rol) {
  let cookies = {}
  const archivo = carpeta + '/sprint32-cookie-' + rol + '.json'
  try { cookies = JSON.parse(await readFile(archivo, 'utf8')) } catch { /* Primera sesión del ensayo. */ }
  async function pedir(metodo, ruta, datos, sinCsrf = false) {
    const headers = { Accept: 'application/json', Origin: 'http://beesocc23c-arbitro.localhost:5175',
      Cookie: Object.entries(cookies).map(([k,v]) => k + '=' + v).join('; ') }
    if (datos) headers['Content-Type'] = 'application/json'
    if (!sinCsrf && cookies['XSRF-TOKEN']) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies['XSRF-TOKEN'])
    const opciones = { method: metodo, headers }
    if (datos && metodo !== 'GET' && metodo !== 'HEAD') opciones.body = JSON.stringify(datos)
    const respuesta = await fetch('http://127.0.0.1:8013' + ruta, opciones)
    for (const valor of respuesta.headers.getSetCookie()) {
      const par = valor.split(';')[0]; const i = par.indexOf('='); cookies[par.slice(0,i)] = par.slice(i+1)
    }
    await writeFile(archivo, JSON.stringify(cookies))
    return { estado: respuesta.status, datos: await respuesta.json().catch(() => null) }
  }
  if (!Object.keys(cookies).some(k => k.startsWith('beesocc23c_'))) {
    assert.equal((await pedir('GET','/sanctum/csrf-cookie')).estado,204)
    assert.equal((await pedir('POST','/api/login',{email:'s32-' + rol + '@example.test',password:'PruebaArbitral32!2026'})).estado,200)
  }
  return pedir
}
function comprobar(nombre, respuesta, esperado) {
  assert.equal(respuesta.estado, esperado, nombre)
  resultados.push({prueba:nombre,estado:respuesta.estado})
  return respuesta.datos
}
const admin = await sesion('admin'), arbitro = await sesion('arbitro'), ajeno = await sesion('ajeno')
const ruta = '/api/partidos/' + fixture.partido
const gol = {tipo:'gol',minuto:10,equipo_partido_id:fixture.local,plantilla_id:fixture.plantilla_local}
async function solicitar() {
  return comprobar('Solicitud de corrección', await arbitro('POST',ruta+'/correcciones',{motivo:'Revisión ficticia 3.2',alcance:'eventos'}),201).correccion.id
}
async function aprobar(id) {
  comprobar('Aprobación del propietario', await admin('PATCH','/api/correcciones/'+id+'/autorizar',{estado:'aprobada'}),200)
}
const alta = {accion:'registrar',datos:{...gol,tipo:'autogol',plantilla_id:fixture.plantilla_visitante}}
if (process.argv[2] === 'preparar') {
  comprobar('Sesión real de Sanctum',await arbitro('GET','/api/usuario'),200)
  comprobar('CSRF activo',await admin('POST','/api/campeonatos/'+fixture.campeonato+'/arbitro',{usuario_id:fixture.arbitro_id},true),419)
  comprobar('Asignación arbitral',await admin('POST','/api/campeonatos/'+fixture.campeonato+'/arbitro',{usuario_id:fixture.arbitro_id}),201)
  comprobar('Administrador no oficializa',await admin('POST',ruta+'/finalizar',{}),403)
  comprobar('Ajeno no consulta',await ajeno('GET',ruta),403)
  comprobar('Inicio permitido',await arbitro('PATCH',ruta,{estado:'en_curso'}),200)
  const evento = comprobar('Gol deportivo real',await arbitro('POST',ruta+'/eventos',gol),201).evento
  fixture.gol = evento.id
  const oficial = comprobar('Oficialización',await arbitro('POST',ruta+'/finalizar',{}),200).partido
  assert.ok(oficial.oficializado_at); fixture.oficializado_at = oficial.oficializado_at
  comprobar('No oficializa dos veces',await arbitro('POST',ruta+'/finalizar',{}),409)
  comprobar('Acta ordinaria cerrada',await arbitro('POST',ruta+'/eventos',gol),409)
  comprobar('Administrador no modifica acta',await admin('PATCH','/api/eventos/'+fixture.gol,{minuto:25}),403)
  const rechazada = await solicitar()
  comprobar('Sin aprobación no aplica',await arbitro('POST','/api/correcciones/'+rechazada+'/aplicar',{operaciones:[alta]}),409)
  comprobar('Árbitro no aprueba',await arbitro('PATCH','/api/correcciones/'+rechazada+'/autorizar',{estado:'aprobada'}),403)
  comprobar('Ajeno no aprueba',await ajeno('PATCH','/api/correcciones/'+rechazada+'/autorizar',{estado:'aprobada'}),403)
  comprobar('Rechazo del propietario',await admin('PATCH','/api/correcciones/'+rechazada+'/autorizar',{estado:'rechazada'}),200)
  comprobar('Rechazo no se consume',await arbitro('POST','/api/correcciones/'+rechazada+'/aplicar',{operaciones:[alta]}),409)
  const aprobada = await solicitar(); await aprobar(aprobada)
  comprobar('Administrador no ejecuta',await admin('POST','/api/correcciones/'+aprobada+'/aplicar',{operaciones:[alta]}),403)
  const aplicada = comprobar('Aplicación autorizada',await arbitro('POST','/api/correcciones/'+aprobada+'/aplicar',{operaciones:[{accion:'corregir_minuto',evento_id:fixture.gol,minuto:15},alta]}),200).correccion
  assert.equal(aplicada.estado,'consumida'); assert.ok(aplicada.fecha_consumo)
  comprobar('Consumo único',await arbitro('POST','/api/correcciones/'+aprobada+'/aplicar',{operaciones:[alta]}),409)
  const marcador = comprobar('Marcador después de corrección',await arbitro('GET',ruta+'/marcador'),200)
  assert.equal(marcador.local.goles,2)
  const auditoria = comprobar('Bitácora privada',await admin('GET',ruta+'/auditoria'),200)
  const registro = auditoria.find(a => a.accion === 'aplicar_correccion')
  assert.equal(registro.usuario_id,fixture.arbitro_id)
  assert.equal(registro.anterior_json.eventos.length,1); assert.equal(registro.posterior_json.eventos.length,2)
  comprobar('Ajeno no ve auditoría',await ajeno('GET',ruta+'/auditoria'),403)
  fixture.rollback = await solicitar(); await aprobar(fixture.rollback)
  comprobar('Fallo parcial revierte lote',await arbitro('POST','/api/correcciones/'+fixture.rollback+'/aplicar',{operaciones:[alta,{accion:'eliminar',evento_id:99999999}]}),404)
  const solicitudes = comprobar('Aprobación conservada tras rollback',await arbitro('GET',ruta+'/correcciones'),200)
  assert.equal(solicitudes.find(c => c.id === fixture.rollback).estado,'aprobada')
  assert.equal((await arbitro('GET',ruta+'/marcador')).datos.local.goles,2)
  fixture.concurrencia = await solicitar(); await aprobar(fixture.concurrencia)
  await writeFile(carpeta+'/sprint32-fixture.json',JSON.stringify(fixture))
} else if (process.argv[2] === 'verificar') {
  const detalle = comprobar('Cierre conservado tras concurrencia',await arbitro('GET',ruta),200)
  assert.equal(detalle.estado,'finalizado'); assert.equal(detalle.oficializado_at,fixture.oficializado_at)
  const marcador = comprobar('Sin gol duplicado por concurrencia',await arbitro('GET',ruta+'/marcador'),200)
  assert.equal(marcador.local.goles,3)
  const auditoria = comprobar('Auditoría única por consumo concurrente',await admin('GET',ruta+'/auditoria'),200)
  assert.equal(auditoria.filter(a => a.accion === 'aplicar_correccion' && a.posterior_json.correccion.id === fixture.concurrencia).length,1)
  comprobar('Revocación',await admin('DELETE','/api/campeonatos/'+fixture.campeonato+'/arbitro',{motivo_revocacion:'Fin de ensayo 3.2'}),200)
  comprobar('Revocado sigue autenticado',await arbitro('GET','/api/usuario'),200)
  comprobar('Revocado no aplica aprobación pendiente',await arbitro('POST','/api/correcciones/'+fixture.rollback+'/aplicar',{operaciones:[alta]}),403)
  comprobar('Revocado no corrige evento',await arbitro('PATCH','/api/eventos/'+fixture.gol,{minuto:70}),403)
  comprobar('Publicación existente',await admin('PATCH','/api/campeonatos/'+fixture.campeonato+'/publicacion',{publicado:true}),200)
  const publico = await fetch('http://127.0.0.1:8013/api/publico/campeonatos/'+fixture.campeonato+'/partidos/'+fixture.partido,{headers:{Accept:'application/json'}})
  assert.equal(publico.status,200); const datos = await publico.json()
  assert.equal(datos.participantes.find(p => p.rol === 'local').goles,3)
  assert.equal(datos.anterior_json,undefined)
  resultados.push({prueba:'Endpoint público conserva marcador sin auditoría privada',estado:200})
} else throw new Error('Fase no reconocida')
await writeFile(carpeta+'/sprint32-http-'+process.argv[2]+'.json',JSON.stringify(resultados,null,2))
console.log(JSON.stringify({aprobadas:resultados.length,pruebas:resultados},null,2))
