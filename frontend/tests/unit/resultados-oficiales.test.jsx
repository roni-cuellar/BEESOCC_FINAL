import { afterEach, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import api, { mensajeError } from '../../src/services/api'
import correccionService from '../../src/services/correccionService'
import partidoService from '../../src/services/partidoService'
import { esResultadoOficial, permisosCorreccion } from '../../src/utils/correcciones'
import ResultadoOficial from '../../src/components/ResultadoOficial'
import { ExpedienteCorreccion } from '../../src/components/PanelCorrecciones'
import HistorialAuditoria from '../../src/components/HistorialAuditoria'
import PartidoCard from '../../src/components/PartidoCard'
import EventoForm from '../../src/components/EventoForm'

const adaptador = api.defaults.adapter
afterEach(() => {
  api.defaults.adapter = adaptador
})
const partido = {
  id: 3,
  campeonato_id: 4,
  jornada: 1,
  estado: 'finalizado',
  tipo_resultado: 'deportivo',
  oficializado_at: '2026-11-14T23:00:00Z',
  participantes: [],
}
const expediente = {
  id: 8,
  partido_id: 3,
  solicitado_por_id: 7,
  motivo: 'Corregir el minuto del gol',
  alcance: 'eventos',
  estado: 'solicitada',
}

it('consulta los expedientes y la auditoría privados sin inventar rutas', async () => {
  const peticiones = []
  api.defaults.adapter = async (config) => {
    peticiones.push([config.method, config.url])
    return { data: [expediente], status: 200, headers: {}, config }
  }
  expect(await correccionService.listar(3)).toEqual([expediente])
  expect(await correccionService.auditoria(3)).toEqual([expediente])
  expect(peticiones).toEqual([
    ['get', '/partidos/3/correcciones'],
    ['get', '/partidos/3/auditoria'],
  ])
})

it.each([
  [
    'solicitar',
    [3, expediente.motivo],
    'post',
    '/partidos/3/correcciones',
    { motivo: expediente.motivo, alcance: 'eventos' },
  ],
  ['resolver', [8, 'aprobada'], 'patch', '/correcciones/8/autorizar', { estado: 'aprobada' }],
  ['resolver', [8, 'rechazada'], 'patch', '/correcciones/8/autorizar', { estado: 'rechazada' }],
  [
    'aplicar',
    [8, [{ accion: 'corregir_minuto', evento_id: 10, minuto: 28 }]],
    'post',
    '/correcciones/8/aplicar',
    { operaciones: [{ accion: 'corregir_minuto', evento_id: 10, minuto: 28 }] },
  ],
])(
  '%s conserva el contrato Laravel y no suplanta responsables',
  async (metodo, argumentos, verbo, ruta, datos) => {
    let enviada
    api.defaults.adapter = async (config) => {
      enviada = [config.method, config.url, JSON.parse(config.data)]
      return { data: { correccion: expediente }, status: 200, headers: {}, config }
    }
    expect(await correccionService[metodo](...argumentos)).toEqual(expediente)
    expect(enviada).toEqual([verbo, ruta, datos])
  },
)

it('finaliza con POST específico y consulta el resultado oficial desde Laravel', async () => {
  const llamadas = []
  api.defaults.adapter = async (config) => {
    llamadas.push([config.method, config.url])
    return {
      data: config.method === 'post' ? { partido } : partido,
      status: 200,
      headers: {},
      config,
    }
  }
  expect(await partidoService.finalizar(3)).toEqual(partido)
  expect(esResultadoOficial(await partidoService.obtener(3))).toBe(true)
  expect(llamadas).toEqual([
    ['post', '/partidos/3/finalizar'],
    ['get', '/partidos/3'],
  ])
})

it('separa la solicitud arbitral y la decisión administrativa', () => {
  expect(permisosCorreccion(partido, expediente, true, 7)).toEqual({
    solicitar: true,
    resolver: false,
    aplicar: false,
  })
  expect(permisosCorreccion(partido, expediente, false, 1)).toEqual({
    solicitar: false,
    resolver: true,
    aplicar: false,
  })
})

it.each(['solicitada', 'rechazada', 'consumida'])(
  'bloquea aplicación de un expediente %s',
  (estado) => {
    expect(permisosCorreccion(partido, { ...expediente, estado }, true, 7).aplicar).toBe(false)
  },
)

it.each([
  [true, 7, 'eventos', true],
  [true, 9, 'eventos', false],
  [true, null, 'eventos', false],
  [false, 7, 'eventos', false],
  [true, 7, 'resultado', false],
])(
  'una aprobación solo la utiliza el solicitante arbitral con alcance implementado: %s/%s/%s',
  (arbitral, usuarioId, alcance, esperado) => {
    expect(
      permisosCorreccion(
        partido,
        { ...expediente, estado: 'aprobada', alcance },
        arbitral,
        usuarioId,
      ).aplicar,
    ).toBe(esperado)
  },
)

it.each([
  { ...partido, oficializado_at: null },
  { ...partido, estado: 'en_curso' },
  { ...partido, tipo_resultado: 'administrativo' },
])('no habilita correcciones fuera del resultado deportivo oficial', (datos) => {
  expect(permisosCorreccion(datos, { ...expediente, estado: 'aprobada' }, true, 7)).toEqual({
    solicitar: false,
    resolver: false,
    aplicar: false,
  })
  expect(permisosCorreccion(datos, expediente, false, 1).resolver).toBe(false)
})

it('distingue los históricos de los resultados oficiales y provisionales', () => {
  expect(renderToStaticMarkup(<ResultadoOficial partido={partido} />)).toContain(
    'Resultado oficial',
  )
  const historico = renderToStaticMarkup(
    <ResultadoOficial partido={{ ...partido, oficializado_at: null }} />,
  )
  expect(historico).toContain('Finalizado sin oficialización')
  expect(historico).toContain('No se oficializa automáticamente')
  expect(
    renderToStaticMarkup(
      <ResultadoOficial partido={{ ...partido, estado: 'en_curso', oficializado_at: null }} />,
    ),
  ).toContain('Resultado provisional')
})

it('presenta decisiones al administrador sin controles para modificar eventos', () => {
  const html = renderToStaticMarkup(
    <ExpedienteCorreccion
      correccion={expediente}
      partido={partido}
      arbitral={false}
      usuarioId={1}
    />,
  )
  expect(html).toContain(expediente.motivo)
  expect(html).toContain('Aprobar solicitud')
  expect(html).toContain('Rechazar solicitud')
  expect(html).not.toContain('Agregar al lote')
  expect(html).not.toContain('Revisar y aplicar')
})

it('presenta un lote vacío y bloqueado hasta preparar operaciones para el solicitante aprobado', () => {
  const html = renderToStaticMarkup(
    <ExpedienteCorreccion
      correccion={{ ...expediente, estado: 'aprobada' }}
      partido={partido}
      arbitral
      usuarioId={7}
      eventos={[]}
      plantillas={{}}
    />,
  )
  expect(html).toContain('Operaciones preparadas (0/100)')
  expect(html).toContain('disabled="">Revisar y aplicar corrección')
  expect(html).not.toContain('Aprobar solicitud')
})

it.each(['rechazada', 'consumida'])('muestra %s sin botones de aprobación o consumo', (estado) => {
  const html = renderToStaticMarkup(
    <ExpedienteCorreccion
      correccion={{ ...expediente, estado }}
      partido={partido}
      arbitral
      usuarioId={7}
    />,
  )
  expect(html).not.toContain('<button')
  expect(html).toContain(estado)
})

it('la auditoría muestra actor, motivo y antes/después sin controles de edición', () => {
  const html = renderToStaticMarkup(
    <HistorialAuditoria
      historial={[
        {
          id: 1,
          usuario_id: 7,
          accion: 'aplicar_correccion',
          motivo: '<script>privado</script>',
          fecha: '2026-11-14T23:00:00Z',
          anterior_json: {
            eventos: [{ id: 10, tipo: 'gol', minuto: 25, plantilla_id: 5, equipo_partido_id: 2 }],
          },
          posterior_json: { eventos: [], correccion: { id: 8, estado: 'consumida' } },
        },
      ]}
    />,
  )
  expect(html).toContain('Usuario #7')
  expect(html).toContain('Antes del cambio')
  expect(html).toContain('Después del cambio')
  expect(html).toContain('25′')
  expect(html).toContain('Eventos (0)')
  expect(html).toContain('consumida')
  expect(html).toContain('&lt;script&gt;')
  expect(html).not.toContain('<script>')
  expect(html).not.toContain('<button')
})

it('las tarjetas públicas muestran oficialización sin exponer expedientes privados', () => {
  const html = renderToStaticMarkup(
    <MemoryRouter>
      <PartidoCard partido={partido} ligaId={4} publica />
    </MemoryRouter>,
  )
  expect(html).toContain('Resultado oficial')
  expect(html).not.toContain('Expediente')
  expect(html).not.toContain('auditoría')
})

it('reutiliza el formulario de eventos para preparar el lote sin enviarlo ordinariamente', () => {
  const html = renderToStaticMarkup(
    <EventoForm
      partido={partido}
      plantillas={{}}
      eventos={[]}
      titulo="Preparar evento para la corrección"
      textoBoton="Agregar al lote"
      bloqueado
    />,
  )
  expect(html).toContain('Preparar evento para la corrección')
  expect(html).toContain('Agregar al lote')
  expect(html).toContain('fieldset class="contents" disabled')
})

it.each([401, 403, 404, 409, 422])(
  'conserva y presenta errores %s de cada endpoint de corrección',
  async (status) => {
    const error = {
      response: {
        status,
        data: {
          errors: { motivo: ['Indica el motivo.'] },
          message: 'La solicitud ya fue resuelta.',
        },
      },
    }
    api.defaults.adapter = async () => {
      throw error
    }
    for (const accion of [
      () => correccionService.listar(3),
      () => correccionService.auditoria(3),
      () => correccionService.solicitar(3, 'Motivo'),
      () => correccionService.resolver(8, 'aprobada'),
      () => correccionService.aplicar(8, []),
    ]) {
      await expect(accion()).rejects.toBe(error)
    }
    expect(mensajeError(error)).toBeTruthy()
  },
)
