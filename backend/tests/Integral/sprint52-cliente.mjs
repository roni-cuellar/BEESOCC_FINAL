import assert from 'node:assert/strict'

// Solo el login admite un reintento; nunca se repiten escrituras deportivas.
export async function loginConEspera(pedir, datos, registrar, esperar = ms => new Promise(r => setTimeout(r, ms))) {
  let respuesta = await pedir('POST', '/api/login', datos)
  if (respuesta.estado === 429) {
    assert.ok(Number.isInteger(respuesta.reintentarEn) && respuesta.reintentarEn > 0 && respuesta.reintentarEn <= 60, 'Retry-After fuera del límite seguro')
    registrar(respuesta.reintentarEn)
    await esperar(respuesta.reintentarEn * 1000)
    respuesta = await pedir('POST', '/api/login', datos)
  }
  assert.equal(respuesta.estado, 200, 'No se pudo iniciar sesión tras respetar el límite; detener')
  return respuesta
}

export function verificarBarrera(resultado) {
  assert.equal(resultado.ok, true, 'La barrera no acreditó la concurrencia')
  assert.equal(resultado.solicitudes_http_en_lock_wait, 2, 'Deben existir dos solicitudes HTTP esperando en InnoDB')
  assert.equal(new Set(resultado.conexiones).size, 2, 'Las solicitudes deben usar conexiones distintas')
}
