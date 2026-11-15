import { afterEach, expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import api, { mensajeError } from '../../src/services/api'
import faseService from '../../src/services/faseService'
import ligaService from '../../src/services/ligaService'
import equipoService from '../../src/services/equipoService'
import arbitroService from '../../src/services/arbitroService'
import partidoService from '../../src/services/partidoService'
import { crearEliminacionStore } from '../../src/stores/useEliminacionStore'
import { datosCuadro, nombrePlaza, partidosOficiales, validarSiembra } from '../../src/utils/eliminacion'
import ConfiguracionEliminacion, { PreviaEliminacion } from '../../src/components/ConfiguracionEliminacion'
import CuadroEliminacion from '../../src/components/CuadroEliminacion'
import PenalesForm from '../../src/components/PenalesForm'
import HistorialAuditoria from '../../src/components/HistorialAuditoria'
import PartidoForm from '../../src/components/PartidoForm'
import { ListaArbitrajes } from '../../src/pages/MisArbitrajesPage'
import AppRoutes from '../../src/routes/AppRoutes'
import useAuthStore from '../../src/stores/useAuthStore'
import LigaCard from '../../src/components/LigaCard'

const adapter = api.defaults.adapter
afterEach(() => { api.defaults.adapter = adapter; vi.restoreAllMocks(); useAuthStore.setState({ usuario: null, verificado: false }) })
const inscripciones = [1, 2, 3].map(id => ({ id, estado: 'activo', equipo: { nombre: ['Águilas', 'Leones', 'Tigres'][id - 1] } }))
const partido = { id: 30, estado: 'finalizado', oficializado_at: '2026-11-15T20:00:00Z', tipo_resultado: 'deportivo', numero_serie: 1, participantes: [] }
const llave = { id: 10, numero: 1, estado: 'pendiente', ganador_fase_equipo_id: null, penales_plaza_1: null, penales_plaza_2: null, plazas: [
  { id: 1, posicion: 1, fase_equipo_id: 101, origen_tipo: 'directo' }, { id: 2, posicion: 2, fase_equipo_id: 102, origen_tipo: 'directo' },
], partidos: [partido] }
const ronda = { id: 5, numero: 1, nombre: 'Semifinal', estado: 'planificacion', modalidad: 'unico', desempate: 'penales', llaves: [llave] }
const fase = { id: 7, campeonato_id: 4, nombre: 'Copa', tipo: 'eliminacion', estado: 'planificacion', equipos: inscripciones.map(i => ({ id: i.id + 100, inscripcion: i })), rondas: [ronda] }
const envio = { siembra: [3, 1, 2], rondas: [{ modalidad: 'ida_vuelta', desempate: 'prorroga_penales' }, { modalidad: 'unico', desempate: 'penales' }] }
const previa = { fase_id: 7, ...envio, cruces: [{ numero: 1, siembras: [1, 4], inscripciones: [3, null] }], confirmacion: 'a'.repeat(64) }
const render = nodo => renderToStaticMarkup(<MemoryRouter>{nodo}</MemoryRouter>)

it('la tarjeta identifica el formato eliminatorio sin exponer su nombre interno', () => {
  const html = render(<LigaCard liga={{ id: 4, nombre: 'Copa', temporada: '2026', estado: 'planificacion', formato: 'eliminacion_directa' }} />)
  expect(html).toContain('Eliminación directa'); expect(html).not.toContain('eliminacion_directa')
})

it.each([
  ['listar', [4], 'get', '/campeonatos/4/fases', undefined, [fase]],
  ['crear', [4, 'Finales'], 'post', '/campeonatos/4/fases', { nombre: 'Finales', tipo: 'eliminacion' }, { fase }],
  ['obtener', [7], 'get', '/fases/7', undefined, fase],
  ['auditoria', [7], 'get', '/fases/7/auditoria', undefined, []],
  ['previsualizar', [7, envio], 'post', '/fases/7/previsualizar-llaves', envio, previa],
  ['generar', [7, { ...envio, confirmacion: previa.confirmacion }], 'post', '/fases/7/generar-llaves', { ...envio, confirmacion: previa.confirmacion }, { fase }],
  ['avanzar', [10], 'post', '/llaves/10/avanzar', undefined, { llave }],
  ['penales', [10, { penales_plaza_1: 5, penales_plaza_2: 4, motivo: 'Certifico el método' }], 'post', '/llaves/10/penales', { penales_plaza_1: 5, penales_plaza_2: 4, motivo: 'Certifico el método' }, { llave }],
])('servicio %s conserva ruta privada, payload, cookies y CSRF', async (metodo, args, verbo, ruta, cuerpo, respuesta) => {
  let peticion
  api.defaults.adapter = async config => { peticion = config; return { data: respuesta, status: 200, config, headers: {} } }
  await faseService[metodo](...args)
  expect(peticion.method).toBe(verbo); expect(peticion.url).toBe(ruta)
  expect(peticion.data ? JSON.parse(peticion.data) : undefined).toEqual(cuerpo)
  expect(peticion.withCredentials).toBe(true); expect(peticion.withXSRFToken).toBe(true)
})

it.each([401, 403, 404, 409, 422])('propaga y presenta el error %s de los ocho contratos', async status => {
  const error = { response: { status, data: { message: 'El cuadro ya fue confirmado.', errors: { siembra: ['Selecciona todos los equipos.'] } } } }
  api.defaults.adapter = async () => { throw error }
  for (const accion of [() => faseService.listar(4), () => faseService.crear(4, 'F'), () => faseService.obtener(7), () => faseService.auditoria(7), () => faseService.previsualizar(7, envio), () => faseService.generar(7, envio), () => faseService.avanzar(10), () => faseService.penales(10, {})]) await expect(accion()).rejects.toBe(error)
  expect(mensajeError(error)).toBeTruthy()
})

it('la configuración empieza sin orden implícito y contiene todas las rondas y métodos', () => {
  const html = render(<ConfiguracionEliminacion inscripciones={inscripciones} />)
  expect((html.match(/Selecciona un equipo/g) || []).length).toBe(3)
  expect(html).toContain('Siembra 3'); expect(html).toContain('Modalidad de ronda 2'); expect(html).toContain('Ida y vuelta'); expect(html).toContain('Prórroga y penales')
  expect(html).toMatch(/disabled=""[^>]*>Previsualizar llaves/)
  expect(validarSiembra(['3', '1', '2'], inscripciones)).toBe(true)
  for (const ids of [[], [1, 2], [1, 1, 3], [1, 2, 99], ['', '2', '3']]) expect(validarSiembra(ids, inscripciones)).toBe(false)
  expect(datosCuadro(['3', '1', '2'], envio.rondas)).toEqual(envio)
})

it('la vista previa muestra exactamente los cruces, descansos y modalidades de Laravel', () => {
  const html = render(<PreviaEliminacion previa={previa} inscripciones={inscripciones} />)
  expect(html).toContain('Tigres'); expect(html).toContain('Descanso'); expect(html).toContain('Siembra 4'); expect(html).toContain('ida y vuelta'); expect(html).toContain('Confirmar generación')
  expect(html).not.toContain('Acta'); expect(html).not.toContain('Ganador')
})

it('el cuadro muestra descanso sin inventar partidos y solo el clasificado recibido', () => {
  const bye = { ...llave, metodo_resolucion: 'bye', estado: 'resuelta', ganador_fase_equipo_id: 101, plazas: [llave.plazas[0], { id: 2, posicion: 2, origen_tipo: 'bye' }], partidos: [] }
  const html = render(<CuadroEliminacion fase={{ ...fase, rondas: [{ ...ronda, llaves: [bye] }] }} ligaId={4} marcadores={{}} />)
  expect(html).toContain('Descanso'); expect(html).toContain('Pase por descanso · sin partido'); expect(html).toContain('Clasificado: Águilas'); expect(html).not.toContain('/partidos/')
  const pendiente = render(<CuadroEliminacion fase={fase} ligaId={4} marcadores={{}} />)
  expect(pendiente).not.toContain('Clasificado:')
})

it('muestra procedencias entre rondas y resultados recibidos sin sumar globales en React', () => {
  expect(nombrePlaza(fase, { origen_tipo: 'ganador_llave', origen_llave_id: 10 })).toBe('Ganador de Semifinal · llave 1')
  const marcadores = { 30: { datos: { local: { nombre: 'Águilas', goles: 2 }, visitante: { nombre: 'Leones', goles: 2 } } } }
  const html = render(<CuadroEliminacion fase={fase} ligaId={4} marcadores={marcadores} />)
  expect(html).toContain('Águilas 2 – 2 Leones'); expect(html).toContain('Resultado oficial'); expect(html).toContain('/ligas/4/partidos/30')
  expect(html).not.toContain('Global:')
})

it.each(['programado', 'en_curso', 'finalizado'])('distingue %s de la oficialización y bloquea el avance prematuro', estado => {
  const sinOficial = { ...llave, partidos: [{ ...partido, estado, oficializado_at: null }] }
  const html = render(<CuadroEliminacion fase={{ ...fase, rondas: [{ ...ronda, llaves: [sinOficial] }] }} ligaId={4} marcadores={{}} />)
  expect(html).toContain(estado.replaceAll('_', ' ')); expect(html).toContain('Resultado no oficial'); expect(html).not.toContain('Solicitar avance')
  expect(partidosOficiales(sinOficial, ronda)).toBe(false)
})

it('separa las acciones de administrador y árbitro y bloquea la edición de tandas certificadas', () => {
  const admin = render(<CuadroEliminacion fase={fase} ligaId={4} marcadores={{}} />)
  const arbitral = render(<CuadroEliminacion fase={fase} ligaId={4} arbitral marcadores={{}} />)
  expect(admin).toContain('Solicitar avance'); expect(admin).not.toContain('Certificar penales')
  expect(arbitral).toContain('Certificar penales'); expect(arbitral).not.toContain('Solicitar avance'); expect(arbitral).toContain('/mis-arbitrajes/4/partidos/30')
  const tanda = render(<CuadroEliminacion fase={{ ...fase, rondas: [{ ...ronda, llaves: [{ ...llave, penales_plaza_1: 5, penales_plaza_2: 4 }] }] }} ligaId={4} arbitral marcadores={{}} />)
  expect(tanda).toContain('5 – 4'); expect(tanda).not.toContain('Certificar penales')
})

it('ida/vuelta requiere los dos partidos oficiales y deportivos para ofrecer acciones', () => {
  expect(partidosOficiales(llave, { ...ronda, modalidad: 'ida_vuelta' })).toBe(false)
  expect(partidosOficiales({ ...llave, partidos: [partido, { ...partido, id: 31 }] }, { ...ronda, modalidad: 'ida_vuelta' })).toBe(true)
  expect(partidosOficiales({ ...llave, partidos: [{ ...partido, tipo_resultado: 'administrativo' }] }, ronda)).toBe(false)
})

it('penales exige motivo, totales enteros limitados y explica el método sin generar goles', () => {
  const html = render(<PenalesForm fase={fase} llave={llave} ronda={{ ...ronda, desempate: 'prorroga_penales' }} />)
  expect(html).toContain('prórroga y penales'); expect(html).toContain('no suma goles'); expect(html).toContain('min="0" max="255" step="1"')
  expect(html).toContain('Motivo y certificación'); expect(html).toContain('Águilas'); expect(html).toContain('Leones')
  expect(html).toMatch(/disabled=""[^>]*>Revisar certificación/)
})

it('auditoría representa responsables, siembra, plazas, clasificados y tandas sin editar', () => {
  const html = render(<HistorialAuditoria contexto="cuadro" historial={[{ id: 1, accion: 'registrar_penales', usuario_id: 9, entidad_tipo: 'llave', entidad_id: 10, motivo: 'Método cumplido', fecha: '2026-11-15T20:00:00Z', anterior_json: { siembra: [3, 1, 2] }, posterior_json: { ganador_fase_equipo_id: 101, penales_plaza_1: 5, penales_plaza_2: 4, plazas: llave.plazas } }]} />)
  for (const texto of ['Tanda certificada', 'Usuario #9', 'llave #10', '3 → 1 → 2', 'Penales de plaza 1', 'Clasificado', 'Procedencia']) expect(html).toContain(texto)
  expect(html).not.toContain('<button')
})

it('partidos del cuadro no ofrecen cambiar equipos ni jornada y el árbitro encuentra el cuadro', () => {
  const html = render(<PartidoForm partido={{ ...partido, llave_id: 10 }} inscripciones={inscripciones} permitirEquipos={false} />)
  expect(html).not.toContain('Cambiar local'); expect(html.match(/<input[^>]*name="jornada"[^>]*>/)?.[0]).toContain('disabled=""')
  const lista = render(<ListaArbitrajes asignaciones={[{ id: 2, fecha_inicio: '2026-11-15', campeonato: { id: 4, nombre: 'Copa', estado: 'en_curso', temporada: '2026', formato: 'eliminacion_directa' } }]} />)
  expect(lista).toContain('/mis-arbitrajes/4/cuadro')
})

it.each(['/ligas/4/cuadro', '/mis-arbitrajes/4/cuadro'])('la ruta %s no muestra datos ni formularios sin sesión verificada', ruta => {
  const html = renderToStaticMarkup(<MemoryRouter initialEntries={[ruta]}><AppRoutes /></MemoryRouter>)
  expect(html).not.toContain('Crear fase'); expect(html).not.toContain('Camino al título'); expect(html).toContain('Cargando')
})

function prepararStore(faseInicial = fase) {
  vi.spyOn(faseService, 'listar').mockResolvedValue([faseInicial])
  vi.spyOn(faseService, 'obtener').mockResolvedValue(faseInicial)
  vi.spyOn(faseService, 'auditoria').mockResolvedValue([])
  vi.spyOn(ligaService, 'obtener').mockResolvedValue({ id: 4, formato: 'eliminacion_directa', estado: 'planificacion' })
  vi.spyOn(equipoService, 'inscripciones').mockResolvedValue(inscripciones)
  vi.spyOn(arbitroService, 'partidos').mockResolvedValue([partido])
  vi.spyOn(partidoService, 'marcador').mockResolvedValue({ partido_id: 30, local: { nombre: 'Águilas', goles: 2 }, visitante: { nombre: 'Leones', goles: 2 } })
  return crearEliminacionStore()
}

it('el administrador crea fase con el contrato y vuelve a consultar detalle y auditoría', async () => {
  const store = prepararStore()
  await store.getState().cargar(4, false)
  vi.spyOn(faseService, 'crear').mockResolvedValue({ id: 7 })
  await store.getState().operar(async () => ({ fase_id: (await faseService.crear(4, 'Finales')).id }), 4, false, 'Fase creada.')
  expect(faseService.crear).toHaveBeenCalledWith(4, 'Finales')
  expect(faseService.obtener).toHaveBeenCalledTimes(2); expect(faseService.auditoria).toHaveBeenCalledTimes(2)
  expect(store.getState().mensaje).toBe('Fase creada.')
})

it('congela el envío previsualizado y genera usando exactamente el token del servidor', async () => {
  const store = prepararStore({ ...fase, rondas: [] })
  await store.getState().cargar(4, false)
  vi.spyOn(faseService, 'previsualizar').mockResolvedValue(previa)
  vi.spyOn(faseService, 'generar').mockResolvedValue(fase)
  const borrador = structuredClone(envio)
  await store.getState().previsualizar(borrador)
  borrador.siembra.reverse(); borrador.rondas[0].modalidad = 'unico'
  await store.getState().generar(4, false)
  expect(faseService.generar).toHaveBeenCalledWith(7, { ...envio, confirmacion: previa.confirmacion })
  expect(store.getState().previa).toBeNull()
})

it('cambiar configuración invalida la confirmación y no permite generar sin otra vista previa', async () => {
  const store = prepararStore()
  await store.getState().cargar(4, false)
  vi.spyOn(faseService, 'previsualizar').mockResolvedValue(previa)
  const generar = vi.spyOn(faseService, 'generar')
  await store.getState().previsualizar(envio)
  store.getState().invalidarPrevia()
  await expect(store.getState().generar(4, false)).rejects.toMatchObject({ response: { status: 409 } })
  expect(generar).not.toHaveBeenCalled()
})

it('el árbitro consulta sin llamar endpoints administrativos de liga o inscripciones', async () => {
  const store = prepararStore()
  await store.getState().cargar(4, true)
  expect(arbitroService.partidos).toHaveBeenCalledWith(4)
  expect(ligaService.obtener).not.toHaveBeenCalled(); expect(equipoService.inscripciones).not.toHaveBeenCalled()
  expect(store.getState().fase.id).toBe(7)
})

it.each([401, 403, 404])('la pérdida de permiso %s elimina cuadro, auditoría y confirmación', async status => {
  const store = prepararStore()
  await store.getState().cargar(4, true)
  const error = { response: { status } }
  vi.spyOn(faseService, 'penales').mockRejectedValue(error)
  await expect(store.getState().operar(() => faseService.penales(10, {}), 4, true, 'Éxito', 7)).rejects.toBe(error)
  expect(store.getState()).toMatchObject({ fase: null, historial: [], previa: null, envio: null, marcadores: {}, cargando: false, contexto: '4:true' })
  expect(store.getState().error).toBe(mensajeError(error))
})

it('un 409 recarga el cuadro corregido y presenta el bloqueo real sin inventar clasificado', async () => {
  const store = prepararStore()
  await store.getState().cargar(4, false)
  const corregida = { ...fase, nombre: 'Cuadro después de corrección' }
  faseService.obtener.mockResolvedValue(corregida)
  const error = { response: { status: 409, data: { message: 'Empate oficial: falta una tanda válida certificada por el árbitro.' } } }
  await expect(store.getState().operar(() => Promise.reject(error), 4, false, 'Éxito', 7)).rejects.toBe(error)
  expect(store.getState().fase.nombre).toBe(corregida.nombre)
  expect(store.getState().errorOperacion).toContain('Empate oficial')
  expect(store.getState().mensaje).toBe('')
})

it('un 422 conserva el cuadro, presenta validación y descarta el token anterior', async () => {
  const store = prepararStore()
  await store.getState().cargar(4, false)
  const error = { response: { status: 422, data: { errors: { motivo: ['Indica el motivo de la tanda.'] } } } }
  await expect(store.getState().operar(() => Promise.reject(error), 4, false, 'Éxito', 7)).rejects.toBe(error)
  expect(store.getState().fase).toEqual(fase); expect(store.getState().errorOperacion).toBe('Indica el motivo de la tanda.')
  expect(store.getState().envio).toBeNull()
})

it('un 409 al previsualizar recarga una generación concurrente y no deja confirmación utilizable', async () => {
  const store = prepararStore({ ...fase, rondas: [] })
  await store.getState().cargar(4, false)
  faseService.obtener.mockResolvedValue(fase)
  vi.spyOn(faseService, 'previsualizar').mockRejectedValue({ response: { status: 409, data: { message: 'La estructura ya fue confirmada.' } } })
  await store.getState().previsualizar(envio)
  expect(store.getState().fase.rondas).toEqual(fase.rondas)
  expect(store.getState().previa).toBeNull(); expect(store.getState().envio).toBeNull()
  expect(store.getState().errorOperacion).toBe('La estructura ya fue confirmada.')
})

it('rechaza un detalle de otro campeonato antes de cargar marcadores privados', async () => {
  const store = prepararStore({ ...fase, campeonato_id: 99 })
  await store.getState().cargar(4, false)
  expect(store.getState().fase).toBeNull(); expect(store.getState().error).toContain('No encontramos')
  expect(partidoService.marcador).not.toHaveBeenCalled()
})

it('un marcador fallido se muestra indisponible y no se convierte en cero', async () => {
  const store = prepararStore()
  partidoService.marcador.mockRejectedValue({ response: { status: 500 } })
  await store.getState().cargar(4, false)
  expect(store.getState().marcadores[30]).toHaveProperty('error')
  expect(store.getState().marcadores[30]).not.toHaveProperty('datos')
})

it('una lectura denegada de auditoría borra el cuadro completo', async () => {
  const store = prepararStore()
  faseService.auditoria.mockRejectedValue({ response: { status: 403 } })
  await store.getState().cargar(4, true)
  expect(store.getState().fase).toBeNull(); expect(store.getState().historial).toEqual([])
})

it('una lectura antigua no restaura datos tras salir o cambiar de campeonato', async () => {
  const store = prepararStore()
  let terminar
  faseService.obtener.mockReturnValue(new Promise(resolve => { terminar = resolve }))
  const lectura = store.getState().cargar(4, false)
  await vi.waitFor(() => expect(terminar).toBeTypeOf('function'))
  store.getState().limpiar()
  terminar(fase); await lectura
  expect(store.getState().fase).toBeNull(); expect(store.getState().historial).toEqual([])
})

it('doble confirmación no duplica la petición de generación', async () => {
  const store = prepararStore()
  await store.getState().cargar(4, false)
  vi.spyOn(faseService, 'previsualizar').mockResolvedValue(previa)
  await store.getState().previsualizar(envio)
  let terminar
  const generar = vi.spyOn(faseService, 'generar').mockImplementation(() => new Promise(resolve => { terminar = resolve }))
  const primera = store.getState().generar(4, false)
  await store.getState().generar(4, false)
  expect(generar).toHaveBeenCalledTimes(1)
  terminar(fase); await primera
})

it('avance y tanda exitosos recargan cuadro, resultados y auditoría de Laravel', async () => {
  const store = prepararStore()
  await store.getState().cargar(4, true)
  vi.spyOn(faseService, 'penales').mockResolvedValue({ ...llave, penales_plaza_1: 5, penales_plaza_2: 4 })
  const datos = { penales_plaza_1: 5, penales_plaza_2: 4, motivo: 'Certifico' }
  await store.getState().operar(() => faseService.penales(10, datos), 4, true, 'Tanda certificada.', 7)
  vi.spyOn(faseService, 'avanzar').mockResolvedValue({ ...llave, ganador_fase_equipo_id: 102 })
  await store.getState().operar(() => faseService.avanzar(10), 4, false, 'Avance confirmado.', 7)
  expect(faseService.penales).toHaveBeenCalledWith(10, datos); expect(faseService.avanzar).toHaveBeenCalledWith(10)
  expect(faseService.obtener).toHaveBeenCalledTimes(3); expect(faseService.auditoria).toHaveBeenCalledTimes(3)
  expect(partidoService.marcador).toHaveBeenCalledTimes(3)
})
