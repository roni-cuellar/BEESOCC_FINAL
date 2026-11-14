import { afterEach, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import api, { mensajeError } from '../../src/services/api'
import arbitroService from '../../src/services/arbitroService'
import partidoService from '../../src/services/partidoService'
import { accionesEstado } from '../../src/utils/partidos'
import { jugadoresDelEvento, golesParaAsistencia, tiposEvento } from '../../src/utils/eventos'
import AccionesEstadoPartido from '../../src/components/AccionesEstadoPartido'
import EventoForm from '../../src/components/EventoForm'
import PartidoCard from '../../src/components/PartidoCard'
import AppRoutes from '../../src/routes/AppRoutes'
import useAuthStore from '../../src/stores/useAuthStore'

const adaptador = api.defaults.adapter
afterEach(() => { api.defaults.adapter = adaptador; useAuthStore.setState({ usuario: null, verificado: false }) })
const partido = { id: 3, campeonato_id: 4, jornada: 1, estado: 'en_curso', fecha_hora: '2026-11-14T18:00:00Z', participantes: [
  { id: 10, rol: 'local', campeonato_equipo_id: 1, campeonato_equipo: { equipo: { nombre: 'Águilas' } } },
  { id: 20, rol: 'visitante', campeonato_equipo_id: 2, campeonato_equipo: { equipo: { nombre: 'Leones' } } },
] }
const jugador = (id, campos = {}) => ({ id, jugador_id: id, fecha_ingreso: '2026-01-01', fecha_salida: null, dorsal: id, jugador: { nombre: 'Jugador', apellido: String(id) }, ...campos })
const plantillas = { 1: [jugador(1), jugador(2, { fecha_salida: '2026-11-13' }), jugador(3, { fecha_ingreso: '2026-11-15' }), jugador(4, { fecha_salida: '2026-11-16' })], 2: [jugador(5)] }

it.each([
  ['programado', ['en_curso']], ['en_curso', ['suspendido', 'finalizado']],
  ['suspendido', ['en_curso']], ['finalizado', []], ['cancelado', []], ['inventado', []],
])('acciones del árbitro coinciden con Laravel para %s', (estado, destinos) => {
  expect(accionesEstado(estado, true)).toEqual(destinos)
  const html = renderToStaticMarkup(<AccionesEstadoPartido estado={estado} arbitral onCambiar={() => {}} />)
  expect(html).not.toContain('Cancelar partido')
  expect((html.match(/<button/g) || []).length).toBe(destinos.length)
})
it.each(['programado', 'en_curso', 'suspendido'])('el administrador solo recibe cancelación desde %s', estado => {
  expect(accionesEstado(estado)).toEqual(['cancelado'])
  const html = renderToStaticMarkup(<AccionesEstadoPartido estado={estado} onCambiar={() => {}} />)
  expect(html).toContain('Cancelar partido'); expect(html).not.toContain('Iniciar'); expect(html).not.toContain('Suspender'); expect(html).not.toContain('Finalizar')
})
it('el formulario conserva seis eventos, validación nativa y bloqueo de todos los campos', () => {
  const html = renderToStaticMarkup(<EventoForm partido={partido} plantillas={plantillas} eventos={[]} bloqueado onGuardar={async () => {}} />)
  Object.values(tiposEvento).forEach(titulo => expect(html).toContain(titulo))
  expect(html).toContain('<fieldset class="contents" disabled=""')
  expect(html).toContain('min="0" max="150" step="1"')
})
it('selecciona jugadores habilitados del participante y del rival para autogoles', () => {
  expect(jugadoresDelEvento(partido, plantillas, 'gol', '10').map(p => p.id)).toEqual([1, 4])
  expect(jugadoresDelEvento(partido, plantillas, 'autogol', '10').map(p => p.id)).toEqual([5])
  expect(jugadoresDelEvento(partido, plantillas, 'gol', '999')).toEqual([])
  expect(jugadoresDelEvento(partido, plantillas, 'autogol', '')).toEqual([])
})
it('ofrece solo goles propios sin asistencia y de otro jugador', () => {
  const eventos = [
    { id: 1, tipo: 'gol', equipo_partido_id: 10, plantilla: { jugador_id: 1 } },
    { id: 2, tipo: 'autogol', equipo_partido_id: 10, plantilla: { jugador_id: 5 } },
    { id: 3, tipo: 'gol', equipo_partido_id: 20, plantilla: { jugador_id: 5 } },
    { id: 4, tipo: 'gol', equipo_partido_id: 10, plantilla: { jugador_id: 4 } },
    { id: 5, tipo: 'asistencia', gol_relacionado_id: '4' },
  ]
  expect(golesParaAsistencia(eventos, '10', 4).map(e => e.id)).toEqual([1])
  expect(golesParaAsistencia(eventos, '10', 1)).toEqual([])
})
it('las tarjetas arbitrales enlazan al panel privado y conservan equipos y fecha', () => {
  const html = renderToStaticMarkup(<MemoryRouter><PartidoCard partido={partido} ligaId={4} arbitral /></MemoryRouter>)
  expect(html).toContain('Águilas'); expect(html).toContain('Leones')
  expect(html).toContain('/mis-arbitrajes/4/partidos/3'); expect(html).not.toContain('href="/ligas/')
})
it('consulta lista y plantillas en endpoints privados sin consultas administrativas', async () => {
  const rutas = []
  api.defaults.adapter = async config => {
    rutas.push(config.url)
    return { data: config.url.endsWith('plantillas-arbitrales') ? plantillas : [partido], status: 200, headers: {}, config }
  }
  expect(await arbitroService.partidos(4)).toEqual([partido])
  expect(await arbitroService.plantillas(3)).toEqual(plantillas)
  expect(rutas).toEqual(['/mis-arbitrajes/4/partidos', '/partidos/3/plantillas-arbitrales'])
})
it.each(Object.keys(tiposEvento))('envía %s y permite recargar marcador y eventos con los servicios existentes', async tipo => {
  const rutas = []
  const datos = { tipo, equipo_partido_id: 10, plantilla_id: tipo === 'autogol' ? 5 : 1, minuto: 10, gol_relacionado_id: tipo === 'asistencia' ? 7 : null }
  api.defaults.adapter = async config => {
    rutas.push([config.method, config.url, config.data && JSON.parse(config.data)])
    return { data: { evento: { id: 8, ...datos }, local: { goles: 1 }, visitante: { goles: 0 } }, status: 200, headers: {}, config }
  }
  expect(await partidoService.crearEvento(3, datos)).toEqual({ id: 8, ...datos })
  expect((await partidoService.marcador(3)).local.goles).toBe(1)
  await partidoService.eventos(3)
  expect(rutas).toEqual([['post', '/partidos/3/eventos', datos], ['get', '/partidos/3/marcador', undefined], ['get', '/partidos/3/eventos', undefined]])
})
it.each([401, 403, 404, 409, 422])('propaga %s en lectura y escritura sin convertirlo en éxito', async status => {
  const error = { response: { status, data: { errors: { minuto: ['El minuto no es válido.'] } } } }
  api.defaults.adapter = async () => { throw error }
  await expect(arbitroService.partidos(4)).rejects.toBe(error)
  await expect(partidoService.actualizar(3, { estado: 'finalizado' })).rejects.toBe(error)
  expect(mensajeError(error)).toBeTruthy()
})
it.each(['/mis-arbitrajes/4/partidos', '/mis-arbitrajes/4/partidos/3'])('protege la sesión en %s', ruta => {
  useAuthStore.setState({ usuario: null, verificado: false })
  const html = renderToStaticMarkup(<MemoryRouter initialEntries={[ruta]}><AppRoutes /></MemoryRouter>)
  expect(html).toContain('Cargando información'); expect(html).not.toContain('Registrar evento')
})
