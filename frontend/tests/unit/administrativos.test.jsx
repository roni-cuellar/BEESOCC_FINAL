import { afterEach, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import api from '../../src/services/api'
import administrativo from '../../src/services/administrativoService'
import Resolucion, { InactivarEquipo } from '../../src/components/ResolucionAdministrativa'
import { permisosCorreccion } from '../../src/utils/correcciones'
import ResultadoOficial from '../../src/components/ResultadoOficial'

const adaptador = api.defaults.adapter
afterEach(() => {
  api.defaults.adapter = adaptador
})
const partido = {
  id: 9,
  estado: 'programado',
  tipo_resultado: 'deportivo',
  participantes: [
    { id: 1, campeonato_equipo_id: 12 },
    { id: 2, campeonato_equipo_id: 14 },
  ],
}

it('envía decisiones explícitas sin goles ni eventos y solicita autorización separada', async () => {
  const envios = []
  api.defaults.adapter = async (config) => {
    envios.push([config.method, config.url, JSON.parse(config.data)])
    return { data: { correccion: { id: 5 } }, status: 200, headers: {}, config }
  }
  await administrativo.resultado(9, {
    beneficiado_id: 12,
    sancionado_id: 14,
    motivo: 'Decisión ficticia',
  })
  await administrativo.inactivar(14, { tipo: 'descalificacion', motivo: 'Motivo ficticio' })
  await administrativo.solicitarRectificacion(9, 'Revisión ficticia')
  expect(envios).toEqual([
    [
      'post',
      '/partidos/9/resultado-administrativo',
      { beneficiado_id: 12, sancionado_id: 14, motivo: 'Decisión ficticia' },
    ],
    [
      'post',
      '/inscripciones-equipos/14/decision-administrativa',
      { tipo: 'descalificacion', motivo: 'Motivo ficticio' },
    ],
    ['post', '/partidos/9/correcciones', { motivo: 'Revisión ficticia', alcance: 'resultado' }],
  ])
})

it('no preselecciona beneficiario ni sancionado y exige motivo', () => {
  const html = renderToStaticMarkup(
    <Resolucion partido={partido} habilitado onOperacion={() => {}} />,
  )
  expect(html).toContain('Equipo beneficiado')
  expect(html).toContain('Equipo sancionado')
  expect(html).toContain('Motivo administrativo')
  expect(html.match(/Selecciona explícitamente un equipo/g)).toHaveLength(2)
  expect(html).toContain('No genera goles ni asistencias individuales')
  expect(html).toContain('disabled')
})

it('oculta la operación cuando el campeonato no está en curso', () => {
  expect(renderToStaticMarkup(<Resolucion partido={partido} habilitado={false} />)).toBe('')
})

it('distingue retiro voluntario, descalificación y resultado del partido', () => {
  const html = renderToStaticMarkup(
    <InactivarEquipo inscripcion={{ id: 14, equipo: { nombre: 'Ficticio' } }} />,
  )
  expect(html).toContain('Retiro voluntario')
  expect(html).toContain('Descalificación administrativa')
  expect(html).toContain('Los partidos pendientes requieren decisiones 3–0 separadas')
})

it('un administrativo oficial solo habilita resolver solicitudes de resultado para el administrador', () => {
  const oficial = {
    ...partido,
    estado: 'finalizado',
    oficializado_at: '2026-11-16T12:00:00Z',
    tipo_resultado: 'administrativo',
  }
  const c = { estado: 'solicitada', alcance: 'resultado' }
  expect(permisosCorreccion(oficial, c, false, 1).resolver).toBe(true)
  expect(permisosCorreccion(oficial, c, true, 1).resolver).toBe(false)
  expect(permisosCorreccion(oficial, { ...c, alcance: 'eventos' }, false, 1).resolver).toBe(false)
  expect(renderToStaticMarkup(<ResultadoOficial partido={oficial} />)).toContain('Administrativo')
})
