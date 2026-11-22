import { expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { RankingDeportivo } from '../../src/pages/EstadisticasPage'
import TablaPosicionesDeportiva from '../../src/components/deportivo/TablaPosicionesDeportiva'
import { FichaEquipo } from '../../src/pages/FichasPublicasPage'

const render = (elemento) => renderToStaticMarkup(<MemoryRouter>{elemento}</MemoryRouter>)
const equipo = { id: 7, nombre: 'Leones', escudo: 'https://example.test/leones.png' }
const fila = { equipo_id: 7, nombre: 'Leones', posicion: 1, PJ: 8, PTS: 19 }

it('usa la inscripción para identificar escudos y clasificados en grupos', () => {
  const html = render(
    <TablaPosicionesDeportiva
      filas={[{ ...fila, campeonato_equipo_id: 24 }]}
      equipos={{ 24: equipo }}
      clasificados={new Set([24])}
      titulo="Grupo A"
      destinoEquipo={(id) => '/ligas/8/equipos/' + id}
    />,
  )
  expect(html).toContain('Clasificado oficial')
  expect(html).toContain(equipo.escudo)
  expect(html).toContain('/ligas/8/equipos/7')
})

it('conserva las columnas y los datos de la tabla y enlaza el escudo real', () => {
  const html = render(
    <TablaPosicionesDeportiva
      filas={[fila]}
      equipos={{ 7: equipo }}
      titulo="Posiciones"
      destinoEquipo={(id) => '/ligas/8/equipos/' + id}
    />,
  )
  for (const columna of ['PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS'])
    expect(html).toContain(`>${columna}<`)
  expect(html).toContain('href="/ligas/8/equipos/7"')
  expect(html).toContain(equipo.escudo)
  expect(html).toContain('>19<')
  expect(html).toContain('tabindex="0"')
})

it('presenta los rankings en el orden del servidor con fotos, escudos y enlaces separados', () => {
  const html = render(
    <RankingDeportivo
      tipo="goleadores"
      base="/campeonatos/8"
      resultados={[
        { jugador_id: 3, nombre: 'Zoe', total: 7 },
        { jugador_id: 4, nombre: 'Ana', total: 4 },
      ]}
      identidades={{ jugadores: [{ jugador_id: 3, equipo, foto: 'https://example.test/zoe.png' }] }}
    />,
  )
  expect(html.indexOf('Zoe')).toBeLessThan(html.indexOf('Ana'))
  for (const texto of [
    '/jugadores/3',
    '/equipos/7',
    equipo.escudo,
    'zoe.png',
    'data-podio="2"',
    '>7<',
  ])
    expect(html).toContain(texto)
})

it('muestra posición y estadísticas de equipo sin rellenar métricas que no llegaron', () => {
  const html = render(
    <FichaEquipo
      estado="activo"
      equipo={equipo}
      plantilla={[]}
      consultaRendimiento={{
        datos: { posiciones: [{ grupo: 'Grupo A', fila }], totales: { goles: 7 } },
      }}
    />,
  )
  expect(html).toContain('Grupo A · Posición 1 · 19 puntos · 8 PJ')
  expect(html).toContain('Sin jugadores inscritos')
  expect(html).toContain('>7<')
  expect(html).not.toContain('Asistencias')
})
