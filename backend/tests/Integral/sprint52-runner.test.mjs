import test from 'node:test'
import assert from 'node:assert/strict'
import { loginConEspera, verificarBarrera } from './sprint52-cliente.mjs'

test('429 espera Retry-After y realiza un solo reintento de login', async () => {
  const respuestas = [{ estado: 429, reintentarEn: 12 }, { estado: 200 }]
  const llamadas = [], esperas = [], limites = []
  const respuesta = await loginConEspera(async (...args) => { llamadas.push(args); return respuestas.shift() }, { email: 'ficticio@example.test' }, s => limites.push(s), async ms => { esperas.push(ms) })
  assert.equal(respuesta.estado, 200)
  assert.deepEqual(esperas, [12000]); assert.deepEqual(limites, [12])
  assert.deepEqual(llamadas.map(c => c.slice(0, 2)), [['POST', '/api/login'], ['POST', '/api/login']])
})

test('login correcto reutilizable no espera ni repite', async () => {
  let llamadas = 0
  await loginConEspera(async () => { llamadas++; return { estado: 200 } }, {}, () => assert.fail(), () => assert.fail())
  assert.equal(llamadas, 1)
})

test('Retry-After inválido o excesivo detiene sin repetir', async () => {
  for (const reintentarEn of [0, -1, NaN, 61, 0.5]) {
    let llamadas = 0
    await assert.rejects(loginConEspera(async () => { llamadas++; return { estado: 429, reintentarEn } }, {}, () => assert.fail(), () => assert.fail()), /Retry-After/)
    assert.equal(llamadas, 1)
  }
})

test('segundo 429 o fallo de autenticación detiene sin bucle', async () => {
  let llamadas = 0
  await assert.rejects(loginConEspera(async () => { llamadas++; return { estado: 429, reintentarEn: 1 } }, {}, () => {}, async () => {}), /No se pudo iniciar/)
  assert.equal(llamadas, 2)
  llamadas = 0
  await assert.rejects(loginConEspera(async () => { llamadas++; return { estado: 401 } }, {}, () => assert.fail(), () => assert.fail()), /No se pudo iniciar/)
  assert.equal(llamadas, 1)
})

test('201 y 409 no sustituyen la prueba de dos conexiones bloqueadas', () => {
  assert.throws(() => verificarBarrera({ ok: false, solicitudes_http_en_lock_wait: 0, conexiones: [] }))
  assert.throws(() => verificarBarrera({ ok: true, solicitudes_http_en_lock_wait: 1, conexiones: [1] }))
  assert.throws(() => verificarBarrera({ ok: true, solicitudes_http_en_lock_wait: 2, conexiones: [1, 1] }))
  verificarBarrera({ ok: true, solicitudes_http_en_lock_wait: 2, conexiones: [1, 2] })
})
