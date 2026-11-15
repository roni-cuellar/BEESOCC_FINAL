// Peticiones reales a Laravel/Sanctum sobre la copia aislada, sin adaptadores simulados.
import assert from 'node:assert/strict'
import { readFile, writeFile } from 'node:fs/promises'
const claveLaboratorio = process.env.BEESOCC_LAB_PASSWORD
assert.ok(typeof claveLaboratorio === 'string' && claveLaboratorio.length >= 16, 'Define BEESOCC_LAB_PASSWORD sin guardarla en archivos')
const carpeta = process.env.BEESOCC_INTEGRAL_DIR
assert.match(carpeta || '', /beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/)
const entorno = JSON.parse(await readFile(carpeta + '/entorno.json','utf8'))
assert.equal(entorno.DB_PORT,'3317'); assert.match(entorno.DB_DATABASE,/^beesocc_23c_test_/)
const fixture = JSON.parse(await readFile(carpeta + '/sprint42-fixture.json','utf8'))
const pruebas = []
function comprobar(nombre, respuesta, estado=200) {
  assert.equal(respuesta.estado,estado,nombre + ': ' + JSON.stringify(respuesta.datos))
  pruebas.push({prueba:nombre,estado})
  return respuesta.datos
}
async function sesion(rol) {
  const cookies={}
  async function pedir(metodo,ruta,datos,sinCsrf=false) {
    const headers={Accept:'application/json',Origin:'http://beesocc23c-arbitro.localhost:5175',Cookie:Object.entries(cookies).map(([k,v])=>k+'='+v).join('; ')}
    if(datos) headers['Content-Type']='application/json'
    if(!sinCsrf && cookies['XSRF-TOKEN']) headers['X-XSRF-TOKEN']=decodeURIComponent(cookies['XSRF-TOKEN'])
    const respuesta=await fetch('http://127.0.0.1:8013'+ruta,{method:metodo,headers,body:datos && JSON.stringify(datos)})
    for(const valor of respuesta.headers.getSetCookie()) { const par=valor.split(';')[0],i=par.indexOf('='); cookies[par.slice(0,i)]=par.slice(i+1) }
    return {estado:respuesta.status,datos:await respuesta.json().catch(()=>null)}
  }
  comprobar('CSRF '+rol,await pedir('GET','/sanctum/csrf-cookie'),204)
  comprobar('Login '+rol,await pedir('POST','/api/login',{email:fixture[rol].email,password:claveLaboratorio}))
  return pedir
}
const admin=await sesion('admin'), arbitro=await sesion('arbitro'), ajeno=await sesion('ajeno')
async function torneo(nombre,cantidad,rondas,formato='eliminacion_directa') {
  const campeonato=comprobar('Crear '+nombre,await admin('POST','/api/campeonatos',{nombre,temporada:'2026',formato}),201).campeonato
  const plantillas={},siembra=[]
  for(const equipo of fixture.equipos.slice(0,cantidad)) {
    const inscripcion=comprobar('Inscribir equipo ficticio',await admin('POST',`/api/campeonatos/${campeonato.id}/inscripciones-equipos`,{equipo_id:equipo.equipo}),201).inscripcion
    siembra.push(inscripcion.id)
    plantillas[inscripcion.id]=comprobar('Plantilla ficticia',await admin('POST',`/api/inscripciones-equipos/${inscripcion.id}/plantillas`,{jugador_id:equipo.jugador,dorsal:1,posicion:'Delantero',fecha_ingreso:'2026-01-01'}),201).plantilla.id
  }
  comprobar('Asignación real',await admin('POST',`/api/campeonatos/${campeonato.id}/arbitro`,{usuario_id:fixture.arbitro.id}),201)
  if(formato==='todos_contra_todos') return {campeonato,siembra,plantillas}
  const fase=comprobar('Crear fase',await admin('POST',`/api/campeonatos/${campeonato.id}/fases`,{nombre:'Fase de prueba',tipo:'eliminacion'}),201).fase
  const datos={siembra,rondas}
  const vista=comprobar('Vista previa sin partidos',await admin('POST',`/api/fases/${fase.id}/previsualizar-llaves`,datos))
  comprobar('Confirmar cuadro',await admin('POST',`/api/fases/${fase.id}/generar-llaves`,{...datos,confirmacion:vista.confirmacion}),201)
  comprobar('No generar dos veces',await admin('POST',`/api/fases/${fase.id}/generar-llaves`,{...datos,confirmacion:vista.confirmacion}),409)
  return {campeonato,fase,datos,vista,plantillas,siembra}
}
async function cuadro(t) { return comprobar('Consulta privada del cuadro',await admin('GET',`/api/fases/${t.fase.id}`)) }
async function oficializar(t,partido,local=1,visitante=0) {
  comprobar('Iniciar',await arbitro('PATCH',`/api/partidos/${partido.id}`,{estado:'en_curso'}))
  const goles=[]
  for(const [rol,cantidad] of [['local',local],['visitante',visitante]]) {
    const participante=partido.participantes.find(p=>p.rol===rol)
    for(let i=0;i<cantidad;i++) goles.push(comprobar('Gol real',await arbitro('POST',`/api/partidos/${partido.id}/eventos`,{tipo:'gol',minuto:15,equipo_partido_id:participante.id,plantilla_id:t.plantillas[participante.campeonato_equipo_id]}),201).evento)
  }
  comprobar('Oficializar',await arbitro('POST',`/api/partidos/${partido.id}/finalizar`))
  return goles
}
async function avanzar(llave) { return comprobar('Avanzar según resultado oficial',await admin('POST',`/api/llaves/${llave.id}/avanzar`)).llave }
async function solicitar(t,partido) {
  const correccion=comprobar('Solicitar revisión de clasificado',await arbitro('POST',`/api/partidos/${partido.id}/correcciones`,{motivo:'Revisión ficticia 4.2 del clasificado',alcance:'eventos'}),201).correccion
  comprobar('Aprobar revisión',await admin('PATCH',`/api/correcciones/${correccion.id}/autorizar`,{estado:'aprobada'}))
  return correccion
}
const uno={modalidad:'unico',desempate:'penales'},doble={modalidad:'ida_vuelta',desempate:'prorroga_penales'}
const completo=await torneo('Copa completa ficticia 4.2',6,[uno,doble,uno])
let c=await cuadro(completo)
assert.equal(c.rondas.length,3); assert.equal(c.rondas[0].llaves.filter(l=>l.metodo_resolucion==='bye').length,2)
assert.equal(c.rondas[0].llaves.flatMap(l=>l.partidos).length,2)
comprobar('Árbitro consulta cuadro',await arbitro('GET',`/api/fases/${c.id}`))
comprobar('Ajeno sin cuadro',await ajeno('GET',`/api/fases/${c.id}`),403)
comprobar('Ajeno sin auditoría',await ajeno('GET',`/api/fases/${c.id}/auditoria`),403)
const pendiente=c.rondas[0].llaves.find(l=>l.estado==='pendiente')
comprobar('No avanza programado',await admin('POST',`/api/llaves/${pendiente.id}/avanzar`),409)
comprobar('Árbitro no avanza',await arbitro('POST',`/api/llaves/${pendiente.id}/avanzar`),403)
comprobar('CSRF requerido',await admin('POST',`/api/llaves/${pendiente.id}/avanzar`,{},true),419)
for(const llave of c.rondas[0].llaves.filter(l=>l.estado==='pendiente')) { await oficializar(completo,llave.partidos[0]); await avanzar(llave) }
c=await cuadro(completo)
for(const [indice,llave] of c.rondas[1].llaves.entries()) {
  await oficializar(completo,llave.partidos[0],1,0)
  comprobar('No avanza serie incompleta',await admin('POST',`/api/llaves/${llave.id}/avanzar`),409)
  await oficializar(completo,llave.partidos[1],indice===0?2:0,indice===0?1:0)
  if(indice===0) {
    comprobar('Empate global sin gol visitante',await admin('POST',`/api/llaves/${llave.id}/avanzar`),409)
    const datos={penales_plaza_1:4,penales_plaza_2:3,motivo:'Prórroga disputada; global empatado y tanda ficticia certificada.'}
    comprobar('Administrador no certifica tanda',await admin('POST',`/api/llaves/${llave.id}/penales`,datos),403)
    comprobar('Totales empatados inválidos',await arbitro('POST',`/api/llaves/${llave.id}/penales`,{...datos,penales_plaza_2:4}),422)
    comprobar('Tanda arbitral',await arbitro('POST',`/api/llaves/${llave.id}/penales`,datos))
  }
  await avanzar(llave)
}
c=await cuadro(completo)
const final=c.rondas[2].llaves[0]
await oficializar(completo,final.partidos[0],0,0)
comprobar('Final empatada no clasifica',await admin('POST',`/api/llaves/${final.id}/avanzar`),409)
comprobar('Penales de final',await arbitro('POST',`/api/llaves/${final.id}/penales`,{penales_plaza_1:3,penales_plaza_2:5,motivo:'Penales directos ficticios de final.'}))
await avanzar(final)
c=await cuadro(completo); assert.equal(c.estado,'finalizada')
assert.equal(c.rondas.flatMap(r=>r.llaves.flatMap(l=>l.partidos)).length,7)
comprobar('Final no avanza dos veces',await admin('POST',`/api/llaves/${final.id}/avanzar`),409)
const historial=comprobar('Auditoría del cuadro completo',await admin('GET',`/api/fases/${c.id}/auditoria`))
assert.equal(historial.filter(a=>a.accion==='registrar_penales').length,2)
console.log('Eliminación completa con descansos, ida/vuelta y penales: aprobada.')

const liga=await torneo('Liga ficticia compatible 4.2',3,[],'todos_contra_todos')
comprobar('Calendario todos contra todos',await admin('POST',`/api/campeonatos/${liga.campeonato.id}/generar-calendario`),201)
const partidosLiga=comprobar('Partidos de liga intactos',await admin('GET',`/api/campeonatos/${liga.campeonato.id}/partidos`))
assert.equal(partidosLiga.length,3); assert.ok(partidosLiga.every(p=>p.fase_id===null && p.llave_id===null))
comprobar('Tabla de liga operativa',await admin('GET',`/api/campeonatos/${liga.campeonato.id}/tabla-posiciones`))
comprobar('No generar grupos',await admin('POST',`/api/campeonatos/${liga.campeonato.id}/fases`,{nombre:'Intento grupos',tipo:'grupos'}),422)

// Escenario separado: el avance y la corrección competirán en procesos PHP independientes.
const carrera=await torneo('Carrera ficticia 4.2',4,[uno,uno])
let cuadroCarrera=await cuadro(carrera)
const [a,b]=cuadroCarrera.rondas[0].llaves
const golesA=await oficializar(carrera,a.partidos[0],2,1)
await oficializar(carrera,b.partidos[0],1,0)
await avanzar(b)
const correccion=await solicitar(carrera,a.partidos[0])
const operaciones=golesA.filter(e=>e.equipo_partido_id===a.partidos[0].participantes.find(p=>p.rol==='local').id).map(e=>({accion:'eliminar',evento_id:e.id}))
await writeFile(carpeta+'/sprint42-carrera.json',JSON.stringify({campeonato:carrera.campeonato.id,fase:carrera.fase.id,llave:a.id,otra_llave:b.id,partido:a.partidos[0].id,correccion:correccion.id,operaciones,admin:fixture.admin.id,arbitro:fixture.arbitro.id}))
await writeFile(new URL('./sprint42-http.json',import.meta.url),JSON.stringify({ok:true,base:entorno.DB_DATABASE,comprobaciones:pruebas.length,pruebas,campeonato_completo:completo.campeonato.id,campeonato_liga:liga.campeonato.id},null,2))
console.log(JSON.stringify({ok:true,base:entorno.DB_DATABASE,comprobaciones:pruebas.length,preparada_concurrencia:true}))
