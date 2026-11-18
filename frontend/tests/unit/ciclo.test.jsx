import { afterEach, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import api from '../../src/services/api.js'
import liga from '../../src/services/ligaService.js'
import Ciclo, { SelectorModalidad } from '../../src/components/CicloCampeonatoPanel.jsx'
import { seleccionModalidad } from '../../src/utils/modalidadCampeonato.js'
import useLigaStore from '../../src/stores/useLigaStore.js'

const adaptador = api.defaults.adapter

for (const modalidad of ['Fútbol 5', 'Fútbol 7', 'Fútbol 11', 'Fútbol sala del reglamento']) {
  it(`persiste y carga ${modalidad} sin deducir el mínimo`, async () => {
    let guardada
    api.defaults.adapter = async (config) => {
      if (config.method === 'patch') guardada = JSON.parse(config.data)
      return { data: { configuracion: guardada }, status: 200, headers: {}, config }
    }
    await liga.requisitosInicio(12, { modalidad, minimo_jugadores: 3 })
    const { configuracion } = await liga.configuracion(12)
    expect(configuracion).toEqual({ modalidad, minimo_jugadores: 3 })
    const html = renderToStaticMarkup(
      <SelectorModalidad
        seleccion={seleccionModalidad(configuracion.modalidad)}
        valor={configuracion.modalidad}
      />,
    )
    expect(html).toContain('selected=""')
    if (modalidad === 'Fútbol sala del reglamento') {
      expect(html).toContain('value="personalizada" selected=""')
      expect(html).toContain(`value="${modalidad}"`)
    } else {
      expect(html).toContain(`value="${modalidad}" selected=""`)
      expect(html).not.toContain('Nombre de la modalidad personalizada')
    }
  })
}

it('muestra opciones explícitas y comunica la selección del desplegable', () => {
  let seleccionada = ''
  const selector = SelectorModalidad({
    seleccion: '',
    valor: '',
    onSeleccionar: (valor) => {
      seleccionada = valor
    },
  })
  const select = selector.props.children[0].props.children[1]
  select.props.onChange({ target: { value: 'Fútbol 7' } })
  expect(seleccionada).toBe('Fútbol 7')
  const html = renderToStaticMarkup(selector)
  for (const nombre of [
    'Selecciona una modalidad',
    'Fútbol 5',
    'Fútbol 7',
    'Fútbol 11',
    'Personalizada',
  ])
    expect(html).toContain(nombre)
  expect(html).toContain('<select required=""')
  expect(seleccionModalidad('')).toBe('')
})

it('carga nombres históricos sin normalizarlos ni sustituirlos por un código', () => {
  for (const valor of ['Ficticia', 'futbol_5', 'Fútbol 7 municipal']) {
    expect(seleccionModalidad(valor)).toBe('personalizada')
    const html = renderToStaticMarkup(<SelectorModalidad seleccion="personalizada" valor={valor} />)
    expect(html).toContain(`value="${valor}"`)
    expect(html).toContain('maxLength="50"')
  }
})

it('comunica la carga de requisitos sin habilitar el inicio', () => {
  const html = renderToStaticMarkup(<Ciclo liga={{ id: 12, estado: 'planificacion' }} />)
  expect(html).toContain('Cargando requisitos de inicio…')
  expect(html).toContain('aria-busy="true"')
  expect(html).toContain('disabled=""')
})
afterEach(() => {
  api.defaults.adapter = adaptador
})

it('refresca un conflicto sin desmontar el panel ni aceptar una respuesta tardía', async () => {
  const anterior = { id: 12, estado: 'planificacion' }
  useLigaStore.setState({ liga: anterior, cargando: false, error: '', solicitada: '12' })
  let resolver
  api.defaults.adapter = (config) =>
    new Promise((resolve) => {
      resolver = (datos) => resolve({ data: datos, status: 200, headers: {}, config })
    })
  const pendiente = useLigaStore.getState().cargarLiga(12, { conservar: true })
  expect(useLigaStore.getState().liga).toBe(anterior)
  expect(useLigaStore.getState().cargando).toBe(false)
  resolver({ id: 12, estado: 'en_curso' })
  await pendiente
  expect(useLigaStore.getState().liga.estado).toBe('en_curso')
  const vieja = useLigaStore.getState().cargarLiga(12, { conservar: true })
  const resolverVieja = resolver
  const reciente = useLigaStore.getState().cargarLiga(14)
  resolver({ id: 14, estado: 'planificacion' })
  await reciente
  resolverVieja({ id: 12, estado: 'finalizado' })
  await vieja
  expect(useLigaStore.getState().liga.id).toBe(14)
})

it('envía configuración explícita y usa las transiciones privadas existentes', async () => {
  const envios = []
  api.defaults.adapter = async (config) => {
    envios.push([config.method, config.url, config.data ? JSON.parse(config.data) : null])
    return { data: {}, status: 200, headers: {}, config }
  }
  await liga.requisitosInicio(12, { modalidad: 'Ficticia', minimo_jugadores: 3 })
  await liga.iniciar(12)
  await liga.finalizar(12)
  await liga.auditoriaCiclo(12)
  expect(envios).toEqual([
    ['patch', '/campeonatos/12/requisitos-inicio', { modalidad: 'Ficticia', minimo_jugadores: 3 }],
    ['post', '/campeonatos/12/iniciar', null],
    ['post', '/campeonatos/12/finalizar', null],
    ['get', '/campeonatos/12/auditoria-ciclo', null],
  ])
})

for (const estado of ['planificacion', 'en_curso', 'finalizado']) {
  it(`muestra únicamente la acción correspondiente a ${estado}`, () => {
    const html = renderToStaticMarkup(<Ciclo liga={{ id: 12, estado }} />)
    expect(html.includes('Iniciar campeonato')).toBe(estado === 'planificacion')
    expect(html.includes('Finalizar campeonato')).toBe(estado === 'en_curso')
    expect(html).toContain('Consultar auditoría del ciclo')
    if (estado === 'finalizado')
      expect(html).toContain('no se permiten modificaciones deportivas ordinarias')
    if (estado === 'planificacion')
      expect(html).toContain('No se asigna un número por modalidad automáticamente')
  })
}
