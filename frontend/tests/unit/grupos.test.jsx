import { afterEach, expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import api from '../../src/services/api'
import grupoService from '../../src/services/grupoService'
import faseService from '../../src/services/faseService'
import ligaService from '../../src/services/ligaService'
import equipoService from '../../src/services/equipoService'
import arbitroService from '../../src/services/arbitroService'
import partidoService from '../../src/services/partidoService'
import { crearGruposStore, envioCuadroGrupos } from '../../src/stores/useGruposStore'
import { datosDistribucion, nombreOrigen } from '../../src/utils/grupos'
import ConfiguracionGrupos, {
  PreviaDistribucion,
} from '../../src/components/grupos/ConfiguracionGrupos'
import TablasGrupos from '../../src/components/grupos/TablasGrupos'
import DesempatesGrupos, { PartidosGrupo } from '../../src/components/grupos/DesempatesGrupos'
import CrucesGrupos from '../../src/components/grupos/CrucesGrupos'
import PenalesForm from '../../src/components/PenalesForm'
import PartidoForm from '../../src/components/PartidoForm'
import CuadroEliminacion from '../../src/components/CuadroEliminacion'
import HistorialAuditoria from '../../src/components/HistorialAuditoria'
import Sidebar from '../../src/components/Sidebar'
import { ListaArbitrajes } from '../../src/pages/MisArbitrajesPage'
import AppRoutes from '../../src/routes/AppRoutes'
import useAuthStore from '../../src/stores/useAuthStore'
import { escenarioGrupos } from '../fixtures/grupos'

const adapter = api.defaults.adapter
afterEach(() => {
  api.defaults.adapter = adapter
  vi.restoreAllMocks()
  useAuthStore.setState({ usuario: null, verificado: false })
})
const render = (nodo) => renderToStaticMarkup(<MemoryRouter>{nodo}</MemoryRouter>)
const diferida = () => {
  let resolver
  const promesa = new Promise((r) => {
    resolver = r
  })
  return { promesa, resolver }
}
function preparar(etapa = 'clasificados') {
  const d = escenarioGrupos(etapa)
  vi.spyOn(faseService, 'listar').mockResolvedValue([d.fase])
  vi.spyOn(faseService, 'obtener').mockImplementation(async (id) =>
    id === 8 ? d.eliminacion : d.fase,
  )
  vi.spyOn(faseService, 'auditoria').mockResolvedValue(d.historial)
  vi.spyOn(ligaService, 'obtener').mockResolvedValue(d.liga)
  vi.spyOn(equipoService, 'inscripciones').mockResolvedValue(d.inscripciones)
  vi.spyOn(arbitroService, 'partidos').mockResolvedValue([])
  vi.spyOn(grupoService, 'clasificacion').mockResolvedValue(d.clasificacion)
  vi.spyOn(grupoService, 'desempates').mockImplementation(async (id) =>
    id === 11 && ['penales', 'clasificados', 'cuadro'].includes(etapa) ? [d.caso] : [],
  )
  vi.spyOn(grupoService, 'tabla').mockImplementation(async (id, vista) => ({
    ...d.clasificacion.tablas.find((t) => t.grupo_id === id),
    vista,
  }))
  vi.spyOn(partidoService, 'marcador').mockImplementation(async (id) => d.marcadores[id])
  return d
}

it.each([
  [
    'crearFase',
    [44, 'Grupos'],
    'post',
    '/campeonatos/44/fases',
    { nombre: 'Grupos', tipo: 'grupos' },
    { fase: {} },
  ],
  [
    'previsualizarDistribucion',
    [7, { metodo: 'automatico' }],
    'post',
    '/fases/7/previsualizar-grupos',
    { metodo: 'automatico' },
    {},
  ],
  [
    'confirmarDistribucion',
    [7, { confirmacion: 'firma' }],
    'post',
    '/fases/7/confirmar-grupos',
    { confirmacion: 'firma' },
    { fase: {} },
  ],
  ['calendario', [7], 'post', '/fases/7/generar-calendario-grupos', undefined, {}],
  ['tabla', [11, 'provisional'], 'get', '/grupos/11/tabla', undefined, {}],
  ['clasificacion', [7], 'get', '/fases/7/clasificacion-grupos', undefined, {}],
  ['clasificar', [7, 'firma'], 'post', '/fases/7/clasificar-grupos', { confirmacion: 'firma' }, {}],
  ['desempates', [11], 'get', '/grupos/11/desempates', undefined, []],
  [
    'previsualizarDesempate',
    [11, { clave: 'clave' }],
    'post',
    '/grupos/11/previsualizar-desempate',
    { clave: 'clave' },
    {},
  ],
  [
    'generarDesempate',
    [11, { clave: 'clave', confirmacion: 'firma' }],
    'post',
    '/grupos/11/generar-desempate',
    { clave: 'clave', confirmacion: 'firma' },
    { desempate: {} },
  ],
  [
    'previsualizarEliminacion',
    [7, { metodo: 'manual' }],
    'post',
    '/fases/7/previsualizar-eliminacion',
    { metodo: 'manual' },
    {},
  ],
  [
    'generarEliminacion',
    [7, { confirmacion: 'firma' }],
    'post',
    '/fases/7/generar-eliminacion',
    { confirmacion: 'firma' },
    { fase: {} },
  ],
])(
  'contrato %s conserva ruta, cuerpo, cookies y CSRF',
  async (metodo, args, verbo, ruta, cuerpo, respuesta) => {
    let peticion
    api.defaults.adapter = async (config) => {
      peticion = config
      return { config, headers: {}, status: 200, data: respuesta }
    }
    await grupoService[metodo](...args)
    expect(peticion.method).toBe(verbo)
    expect(peticion.url).toBe(ruta)
    expect(peticion.data ? JSON.parse(peticion.data) : undefined).toEqual(cuerpo)
    expect(peticion.withCredentials).toBe(true)
    expect(peticion.withXSRFToken).toBe(true)
    if (metodo === 'tabla') expect(peticion.params).toEqual({ vista: 'provisional' })
  },
)

it.each([401, 403, 404, 409, 422])(
  'todos los contratos propagan %s sin sustituir la respuesta de Laravel',
  async (status) => {
    const error = { response: { status } }
    api.defaults.adapter = async () => {
      throw error
    }
    for (const metodo of Object.keys(grupoService))
      await expect(grupoService[metodo](7, {})).rejects.toBe(error)
  },
)

it('la distribución manual exige todas las inscripciones y cupos; la automática no impone grupos', () => {
  const activos = escenarioGrupos().inscripciones
  expect(datosDistribucion('automatico', '3', '1', {}, activos)).toEqual({
    metodo: 'automatico',
    cantidad_grupos: 3,
    clasificados_por_grupo: 1,
  })
  expect(datosDistribucion('manual', 3, 1, {}, activos)).toBeNull()
  const asignaciones = { 1: 1, 2: 2, 3: 3, 4: 1, 5: 2, 6: 3 }
  expect(datosDistribucion('manual', 3, 1, asignaciones, activos).grupos).toEqual([
    [1, 4],
    [2, 5],
    [3, 6],
  ])
  for (const [cantidad, cupo] of [
    [0, 1],
    [7, 1],
    [2.5, 1],
    [3, 0],
    [1, 1],
  ])
    expect(datosDistribucion('automatico', cantidad, cupo, {}, activos)).toBeNull()
  expect(datosDistribucion('manual', 3, 3, asignaciones, activos)).toBeNull()
})

it('la carga oficial incluye cuadros corregidos, expedientes, actas y auditoría', async () => {
  const d = preparar('cuadro'),
    s = crearGruposStore()
  await s.getState().cargar(44)
  expect(s.getState().clasificacion).toEqual(d.clasificacion)
  expect(s.getState().eliminacion).toEqual(d.eliminacion)
  expect(s.getState().expedientes[11]).toEqual([d.caso])
  expect(s.getState().marcadores[40].datos.local.goles).toBe(1)
  expect(s.getState().historialCuadro).toEqual(d.historial)
})

it('la vista provisional usa su endpoint y nunca sustituye la clasificación oficial', async () => {
  preparar()
  const s = crearGruposStore()
  await s.getState().cargar(44, false, 'provisional')
  expect(grupoService.tabla).toHaveBeenCalledWith(11, 'provisional')
  expect(s.getState().tablas[0].vista).toBe('provisional')
  expect(s.getState().clasificacion.clasificacion_vigente).toBe(true)
})

it('el árbitro carga solo contratos permitidos y no previsualiza operaciones administrativas', async () => {
  preparar('penales')
  const s = crearGruposStore()
  const preview = vi.spyOn(grupoService, 'previsualizarDistribucion')
  await s.getState().cargar(44, true)
  await s.getState().previsualizar('distribucion', {})
  expect(arbitroService.partidos).toHaveBeenCalledWith(44)
  expect(ligaService.obtener).not.toHaveBeenCalled()
  expect(equipoService.inscripciones).not.toHaveBeenCalled()
  expect(preview).not.toHaveBeenCalled()
})

it.each(['distribucion', 'desempate', 'eliminacion'])(
  'confirma %s con los campos y firma exactos devueltos por Laravel',
  async (tipo) => {
    const d = preparar(),
      s = crearGruposStore()
    await s.getState().cargar(44)
    const v =
      tipo === 'distribucion'
        ? {
            fase_id: 7,
            metodo: 'automatico',
            cantidad_grupos: 3,
            clasificados_por_grupo: 1,
            grupos: [
              [1, 4],
              [2, 5],
              [3, 6],
            ],
            confirmacion: d.firma,
          }
        : tipo === 'desempate'
          ? {
              grupo_id: 11,
              clave: 'b'.repeat(64),
              inscripciones: [1, 4],
              tipo: 'partido_extra',
              confirmacion: d.firma,
            }
          : d.previa
    const metodo = {
      distribucion: 'previsualizarDistribucion',
      desempate: 'previsualizarDesempate',
      eliminacion: 'previsualizarEliminacion',
    }[tipo]
    const confirmador = {
      distribucion: 'confirmarDistribucion',
      desempate: 'generarDesempate',
      eliminacion: 'generarEliminacion',
    }[tipo]
    vi.spyOn(grupoService, metodo).mockResolvedValue(v)
    const confirmar = vi.spyOn(grupoService, confirmador).mockResolvedValue({})
    await s
      .getState()
      .previsualizar(tipo, { metodo: 'automatico' }, tipo === 'desempate' ? 11 : undefined)
    await s.getState().confirmarPrevia(44, false)
    const esperado =
      tipo === 'distribucion'
        ? Object.fromEntries(Object.entries(v).filter(([k]) => k !== 'fase_id'))
        : tipo === 'desempate'
          ? { clave: v.clave, confirmacion: v.confirmacion }
          : envioCuadroGrupos(v)
    expect(confirmar).toHaveBeenCalledExactlyOnceWith(tipo === 'desempate' ? 11 : 7, esperado)
    expect(s.getState().previa).toBeNull()
  },
)

it('los cruces manuales reenvían sorteo, firma y plazas sin reconstruirlos', async () => {
  const d = preparar(),
    s = crearGruposStore()
  await s.getState().cargar(44)
  const datos = {
    ...envioCuadroGrupos(d.previa),
    metodo: 'manual',
    cruces: [...d.previa.cruces].reverse(),
  }
  delete datos.confirmacion
  const preview = vi
    .spyOn(grupoService, 'previsualizarEliminacion')
    .mockResolvedValue({ ...d.previa, ...datos })
  await s.getState().previsualizar('eliminacion', datos)
  expect(preview).toHaveBeenCalledExactlyOnceWith(7, datos)
})

it('bloquea doble envío incluso antes del siguiente render y recarga tras guardar', async () => {
  preparar()
  const s = crearGruposStore(),
    espera = diferida()
  await s.getState().cargar(44)
  const accion = vi.fn(() => espera.promesa)
  const primero = s.getState().operar(accion, 44, false, 'Guardado')
  await s.getState().operar(accion, 44, false, 'Duplicado')
  expect(accion).toHaveBeenCalledTimes(1)
  espera.resolver()
  await primero
  expect(s.getState().operando).toBe(false)
  expect(s.getState().mensaje).toBe('Guardado')
})

it('descarta una previsualización tardía si se editó el formulario', async () => {
  const d = preparar(),
    s = crearGruposStore(),
    espera = diferida()
  await s.getState().cargar(44)
  vi.spyOn(grupoService, 'previsualizarEliminacion').mockReturnValue(espera.promesa)
  const p = s.getState().previsualizar('eliminacion', {})
  s.getState().invalidarPrevia()
  espera.resolver(d.previa)
  await p
  expect(s.getState().previa).toBeNull()
  await expect(s.getState().confirmarPrevia(44, false)).rejects.toMatchObject({
    response: { status: 409 },
  })
})

it('una carga vieja no recupera información después de limpiar o cambiar de campeonato', async () => {
  const d = preparar(),
    s = crearGruposStore(),
    espera = diferida()
  faseService.listar.mockReturnValueOnce(espera.promesa)
  const p = s.getState().cargar(44)
  s.getState().limpiar()
  espera.resolver([d.fase])
  await p
  expect(s.getState().fase).toBeNull()
  expect(s.getState().contexto).toBe('')
})

it('un error antiguo no borra una consulta nueva mientras recarga el contexto anterior', async () => {
  const d = preparar(),
    s = crearGruposStore(),
    espera = diferida()
  await s.getState().cargar(44)
  faseService.listar.mockReturnValueOnce(espera.promesa)
  vi.spyOn(grupoService, 'previsualizarEliminacion').mockRejectedValue({
    response: { status: 403 },
  })
  const p = s.getState().previsualizar('eliminacion', {})
  await vi.waitFor(() => expect(faseService.listar).toHaveBeenCalledTimes(2))
  await s.getState().cargar(44, false, 'provisional')
  espera.resolver([d.fase])
  await p
  expect(s.getState().fase.id).toBe(7)
  expect(s.getState().vista).toBe('provisional')
  expect(s.getState().error).toBe('')
})

it.each([401, 403, 404])(
  'un error %s borra datos privados y confirmaciones anteriores',
  async (status) => {
    preparar()
    const s = crearGruposStore()
    await s.getState().cargar(44)
    await expect(
      s.getState().operar(
        async () => {
          throw { response: { status } }
        },
        44,
        false,
        '',
      ),
    ).rejects.toBeTruthy()
    expect(s.getState().fase).toBeNull()
    expect(s.getState().clasificacion).toBeNull()
    expect(s.getState().previa).toBeNull()
    expect(s.getState().error).toBeTruthy()
  },
)

it('409 recarga clasificación y cuadro; 422 conserva datos pero invalida la firma', async () => {
  preparar()
  const s = crearGruposStore()
  await s.getState().cargar(44)
  const veces = grupoService.clasificacion.mock.calls.length
  await expect(
    s.getState().operar(
      async () => {
        throw { response: { status: 409 } }
      },
      44,
      false,
      '',
    ),
  ).rejects.toBeTruthy()
  expect(grupoService.clasificacion.mock.calls.length).toBe(veces + 1)
  await expect(
    s.getState().operar(
      async () => {
        throw {
          response: { status: 422, data: { errors: { grupos: ['Revisa la distribución.'] } } },
        }
      },
      44,
      false,
      '',
    ),
  ).rejects.toBeTruthy()
  expect(s.getState().fase.id).toBe(7)
  expect(s.getState().errorOperacion).toContain('Revisa la distribución')
  expect(s.getState().previa).toBeNull()
})

it('no acepta un cuadro o fase de otro campeonato', async () => {
  preparar()
  faseService.obtener.mockResolvedValue({ id: 7, tipo: 'grupos', campeonato_id: 99 })
  const s = crearGruposStore()
  await s.getState().cargar(44)
  expect(s.getState().fase).toBeNull()
  expect(s.getState().error).toBeTruthy()
})

it('la tabla representa cifras y posiciones recibidas, sin declarar candidatos como clasificados', () => {
  const d = escenarioGrupos('empate')
  const html = render(
    <TablasGrupos
      fase={d.fase}
      tablas={d.clasificacion.tablas}
      clasificacion={d.clasificacion}
      vista="oficial"
    />,
  )
  for (const campo of ['PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS'])
    expect(html).toContain(campo)
  expect(html).toContain('pendientes de resolución')
  expect(html).not.toContain('Clasificado oficial</small>')
  const oficial = escenarioGrupos()
  expect(
    render(
      <TablasGrupos
        fase={oficial.fase}
        tablas={oficial.clasificacion.tablas}
        clasificacion={oficial.clasificacion}
        vista="provisional"
      />,
    ),
  ).toContain('Clasificado oficial')
})

it('previsualiza distribución sin mostrar la firma técnica al usuario', () => {
  const d = escenarioGrupos()
  expect(render(<ConfiguracionGrupos inscripciones={d.inscripciones} />)).toContain(
    'Sorteo automático',
  )
  const html = render(
    <PreviaDistribucion
      vista={{
        metodo: 'manual',
        grupos: [
          [1, 4],
          [2, 5],
          [3, 6],
        ],
        clasificados_por_grupo: 1,
        confirmacion: d.firma,
      }}
      inscripciones={d.inscripciones}
    />,
  )
  expect(html).toContain('Águilas de Bogotá')
  expect(html).toContain('Confirmar distribución')
  expect(html).not.toContain(d.firma)
})

it('el árbitro consulta expedientes y actas sin poder generar ni avanzar', () => {
  const d = escenarioGrupos('penales')
  const html = render(
    <DesempatesGrupos
      fase={d.fase}
      clasificacion={d.clasificacion}
      expedientes={{ 11: [d.caso] }}
      ligaId={44}
      arbitral
      marcadores={{}}
    />,
  )
  expect(html).toContain('/mis-arbitrajes/44/partidos/40')
  expect(html).toContain('Certificar penales del desempate')
  expect(html).not.toContain('Previsualizar desempate')
  expect(html).not.toContain('Solicitar resolución')
  expect(
    render(
      <PenalesForm
        fase={d.fase}
        llave={d.caso.llave}
        nombresPlazas={['Águilas', 'Halcones']}
        ronda={{ nombre: 'Partido extra', desempate: 'penales' }}
      />,
    ),
  ).toContain('Penales · Halcones')
})

it('liguillas y partidos extra conservan el tipo y los equipos de la propuesta', () => {
  const d = escenarioGrupos('empate')
  for (const tipo of ['partido_extra', 'liguilla']) {
    const html = render(
      <DesempatesGrupos
        fase={d.fase}
        clasificacion={d.clasificacion}
        expedientes={{}}
        ligaId={44}
        marcadores={{}}
        previa={{
          tipo,
          inscripciones: tipo === 'liguilla' ? [1, 2, 3] : [1, 4],
          posicion_inicio: 1,
        }}
      />,
    )
    expect(html).toContain('Confirmar generación del desempate')
    expect(html).toContain(
      tipo === 'liguilla' ? 'Liguilla adicional de una vuelta' : 'Partido extra entre dos equipos',
    )
  }
})

it('los cruces muestran procedencias, descanso, rondas y sorteo solo desde la propuesta', () => {
  const d = escenarioGrupos()
  const html = render(
    <CrucesGrupos
      fase={d.fase}
      clasificacion={d.clasificacion}
      inscripciones={d.inscripciones}
      previa={d.previa}
    />,
  )
  expect(html).toContain('Descanso')
  expect(html).toContain('G1 · puesto 1')
  expect(html).toContain('Ver sorteo')
  expect(html).toContain('Confirmar sorteo')
  expect(nombreOrigen(null, d.fase, d.clasificacion, [])).toBe('Descanso')
  const cuadro = render(
    <CuadroEliminacion fase={d.eliminacion} grupos={d.fase.grupos} ligaId={44} marcadores={{}} />,
  )
  expect(cuadro).toContain('G2 · puesto 1')
  expect(cuadro).toContain('Camino al título')
})

it('el calendario enlaza actas existentes y la programación de grupos congela equipos y jornada', () => {
  const d = escenarioGrupos()
  const p = d.fase.grupos[0].partidos[0]
  expect(
    render(<PartidosGrupo partidos={[p]} fase={d.fase} ligaId={44} marcadores={{}} />),
  ).toContain('/ligas/44/partidos/30')
  const html = render(<PartidoForm partido={p} inscripciones={d.inscripciones} />)
  expect(html).not.toContain('Cambiar local')
  expect(html.match(/<input[^>]*name="jornada"[^>]*>/)?.[0]).toContain('disabled=""')
})

it('la auditoría de grupos presenta clasificación, sorteo y responsables solo en lectura', () => {
  const d = escenarioGrupos()
  const html = render(
    <HistorialAuditoria
      historial={[
        {
          ...d.historial[0],
          accion: 'generar_eliminacion_grupos',
          posterior_json: { sorteo: d.previa.sorteo, clasificados: d.clasificacion.candidatos },
        },
      ]}
    />,
  )
  expect(html).toContain('Sorteo y cruces confirmados')
  expect(html).toContain('Puesto de procedencia')
  expect(html).not.toContain('<button')
})

it('la navegación identifica grupos en administración y arbitraje', () => {
  const d = escenarioGrupos()
  expect(render(<Sidebar ligaId={44} grupos />)).toContain('/ligas/44/grupos')
  expect(
    render(
      <ListaArbitrajes
        asignaciones={[{ id: 1, fecha_inicio: '2026-11-15', campeonato: d.liga }]}
      />,
    ),
  ).toContain('/mis-arbitrajes/44/grupos')
})

it.each(['/ligas/44/grupos', '/mis-arbitrajes/44/grupos'])(
  'la ruta privada %s no expone formularios sin sesión',
  (ruta) => {
    const html = renderToStaticMarkup(
      <MemoryRouter initialEntries={[ruta]}>
        <AppRoutes />
      </MemoryRouter>,
    )
    expect(html).not.toContain('Distribución de grupos')
    expect(html).not.toContain('Águilas')
    expect(html).not.toContain('Historial de auditoría')
  },
)
