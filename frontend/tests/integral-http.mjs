// Peticiones HTTP reales al laboratorio del Sprint 2.3C. Nunca usa adaptadores simulados.
import assert from 'node:assert/strict'
import { readFile, writeFile } from 'node:fs/promises'

const contrasena = process.env.BEESOCC_INTEGRAL_PASSWORD
assert.ok(contrasena, 'Define BEESOCC_INTEGRAL_PASSWORD para el laboratorio de pruebas.')

const carpeta = process.env.BEESOCC_INTEGRAL_DIR
assert.match(carpeta || '', /beesocc_23c_test_\d{8}_\d{6}_[a-f0-9]{8}$/)
const origen = 'http://beesocc23c-arbitro.localhost:5175'
const resultados = []

async function sesion(correo, conservar = false) {
  const archivo = carpeta + '/sesion-arbitro.json'
  let cookies = {}
  if (conservar) {
    try {
      cookies = JSON.parse(await readFile(archivo, 'utf8'))
    } catch {
      /* Primera ejecución de la sesión ficticia. */
    }
  }
  async function pedir(metodo, ruta, datos, sinCsrf = false) {
    const headers = {
      Accept: 'application/json',
      Origin: origen,
      Referer: origen + '/',
      Cookie: Object.entries(cookies)
        .map(([k, v]) => k + '=' + v)
        .join('; '),
    }
    if (datos) headers['Content-Type'] = 'application/json'
    if (!sinCsrf && cookies['XSRF-TOKEN'])
      headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies['XSRF-TOKEN'])
    const respuesta = await fetch('http://127.0.0.1:8013' + ruta, {
      method: metodo,
      headers,
      body: datos && JSON.stringify(datos),
    })
    for (const valor of respuesta.headers.getSetCookie()) {
      const par = valor.split(';')[0]
      const igual = par.indexOf('=')
      cookies[par.slice(0, igual)] = par.slice(igual + 1)
    }
    if (conservar) await writeFile(archivo, JSON.stringify(cookies))
    return { estado: respuesta.status, datos: await respuesta.json().catch(() => null) }
  }
  if (!Object.keys(cookies).some((nombre) => nombre.startsWith('beesocc23c_'))) {
    assert.equal((await pedir('GET', '/sanctum/csrf-cookie')).estado, 204)
    assert.equal(
      (
        await pedir('POST', '/api/login', {
          email: correo + '@beesocc23c.test',
          password: contrasena,
        })
      ).estado,
      200,
    )
  }
  return pedir
}
function comprobar(nombre, respuesta, esperado) {
  assert.equal(respuesta.estado, esperado, nombre)
  resultados.push({ prueba: nombre, estado: respuesta.estado })
}
const fase = process.argv[2]
const arbitro = await sesion('arbitro-a', true)
const datos = { tipo: 'gol', equipo_partido_id: 1, plantilla_id: 1, minuto: 20 }
if (fase === 'activo') {
  const admin = await sesion('admin')
  const ajeno = await sesion('ajeno')
  const segundo = await sesion('arbitro-b')
  comprobar('Sesión arbitral con cookie real', await arbitro('GET', '/api/usuario'), 200)
  comprobar('Lectura autorizada', await arbitro('GET', '/api/partidos/1'), 200)
  comprobar(
    'CSRF sin token incluso autenticado',
    await arbitro('POST', '/api/partidos/1/eventos', datos, true),
    419,
  )
  comprobar(
    'Administrador sin permiso de acta',
    await admin('POST', '/api/partidos/1/eventos', datos),
    403,
  )
  comprobar('Usuario ajeno', await ajeno('GET', '/api/partidos/1'), 403)
  comprobar(
    'Segundo árbitro sin asignación',
    await segundo('POST', '/api/partidos/1/eventos', datos),
    403,
  )
  comprobar(
    'ID de participante manipulado',
    await arbitro('POST', '/api/partidos/1/eventos', { ...datos, equipo_partido_id: 99999 }),
    422,
  )
  comprobar(
    'Tipo de evento inválido',
    await arbitro('POST', '/api/partidos/1/eventos', { ...datos, tipo: 'inventado' }),
    422,
  )
  comprobar('Partido inexistente', await arbitro('GET', '/api/partidos/99999'), 404)
  comprobar(
    'Transición repetida',
    await arbitro('PATCH', '/api/partidos/1', { estado: 'en_curso' }),
    409,
  )
  comprobar(
    'Sin permiso organizativo',
    await arbitro('PATCH', '/api/partidos/1', { lugar: 'Intrusión' }),
    403,
  )
  comprobar(
    'Invitado sin sesión',
    {
      estado: (
        await fetch('http://127.0.0.1:8013/api/partidos/1', {
          headers: { Accept: 'application/json', Origin: origen },
        })
      ).status,
    },
    401,
  )
} else if (fase === 'revocado') {
  // Reutiliza la cookie creada mientras la asignación aún estaba vigente.
  comprobar('La sesión sigue autenticada tras revocar', await arbitro('GET', '/api/usuario'), 200)
  comprobar('Revocado no consulta', await arbitro('GET', '/api/partidos/1'), 403)
  comprobar('Revocado no registra', await arbitro('POST', '/api/partidos/1/eventos', datos), 403)
  comprobar(
    'Revocado no cambia estado',
    await arbitro('PATCH', '/api/partidos/1', { estado: 'finalizado' }),
    403,
  )
  comprobar('Revocado no corrige', await arbitro('PATCH', '/api/eventos/1', { minuto: 30 }), 403)
  comprobar('Revocado no elimina', await arbitro('DELETE', '/api/eventos/1'), 403)
} else if (fase === 'finalizado') {
  const segundo = await sesion('arbitro-b')
  comprobar('Finalizado no registra', await segundo('POST', '/api/partidos/1/eventos', datos), 409)
  comprobar(
    'Finalizado no se reabre',
    await segundo('PATCH', '/api/partidos/1', { estado: 'en_curso' }),
    409,
  )
  comprobar('Finalizado no corrige', await segundo('PATCH', '/api/eventos/1', { minuto: 30 }), 409)
  const admin = await sesion('admin')
  const goles = await admin('GET', '/api/campeonatos/1/estadisticas/goleadores')
  comprobar('Estadística real de goleadores', goles, 200)
  assert.equal(goles.datos.resultados.find((j) => j.jugador_id === 1).total, 1)
  for (const tipo of ['asistencias', 'amarillas', 'rojas', 'faltas']) {
    const respuesta = await admin('GET', '/api/campeonatos/1/estadisticas/' + tipo)
    comprobar('Estadística real de ' + tipo, respuesta, 200)
    assert.equal(respuesta.datos.resultados.find((j) => j.jugador_id === 2).total, 1)
  }
  const equipos = await admin('GET', '/api/campeonatos/1/estadisticas/equipos')
  comprobar('Estadística real de equipos', equipos, 200)
  assert.equal(equipos.datos.resultados.find((e) => e.equipo_id === 1).goles, 2)
  const tabla = await admin('GET', '/api/campeonatos/1/tabla-posiciones')
  comprobar('Tabla real tras finalizar', tabla, 200)
  assert.equal(tabla.datos.tabla.find((e) => e.equipo_id === 1).PTS, 3)
} else if (fase === 'preparar-fecha') {
  const admin = await sesion('admin')
  comprobar(
    'Preparar fecha ficticia con segundos para reproducir regresión',
    await admin('PATCH', '/api/partidos/1', { fecha_hora: '2026-11-14T22:17:42Z' }),
    200,
  )
} else if (fase === 'verificar-fecha') {
  const admin = await sesion('admin')
  const detalle = await admin('GET', '/api/partidos/1')
  comprobar('Guardar solo lugar conserva fecha exacta', detalle, 200)
  assert.equal(new Date(detalle.datos.fecha_hora).toISOString(), '2026-11-14T22:17:42.000Z')
} else throw new Error('Fase no reconocida')
await writeFile(carpeta + '/http-' + fase + '.json', JSON.stringify(resultados, null, 2))
console.log(JSON.stringify(resultados, null, 2))
