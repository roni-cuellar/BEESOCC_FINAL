import { describe, expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { CampeonPublico, ResumenPublico } from '../../src/pages/DetallePublicoPage'
import { FichaEquipo, FichaJugador } from '../../src/pages/FichasPublicasPage'
import MenuEspectador from '../../src/components/visualizador/MenuEspectador'
import { SalonFamaContenido } from '../../src/components/visualizador/SalonFama'
import { ultimosResultados } from '../../src/utils/espectador'

vi.mock('../../src/stores/useAuthStore', () => ({
  default: () => ({ usuario: { id: 1 }, verificado: true }),
}))

const render = (elemento) => renderToStaticMarkup(<MemoryRouter>{elemento}</MemoryRouter>)
const equipo = {
  id: 7,
  nombre: 'Águilas',
  escudo: 'https://example.test/escudo.png',
  ciudad: 'Bogotá',
}
const jugador = {
  id: 9,
  nombre: 'Carlos',
  apellido: 'Pérez',
  foto: 'https://example.test/foto.png',
}

describe('espectador', () => {
  it('ofrece las tres secciones y el cambio de rol', () => {
    const html = render(<MenuEspectador ligaId="8" />)
    for (const ruta of [
      '/campeonatos/8',
      '/campeonatos/8/clasificacion',
      '/campeonatos/8/estadisticas',
      '/seleccion-roles',
    ]) {
      expect(html).toContain(`href="${ruta}"`)
    }
    expect(html).toContain('aria-controls="menu-espectador"')
  })

  it('enlaza las fichas públicas y muestra escudo, fotografía, dorsal y posición', () => {
    expect(
      render(<ResumenPublico equipos={[{ inscripcion_id: 1, equipo }]} partidos={[]} />),
    ).toContain('href="/equipos/7"')
    const lista = render(
      <FichaEquipo
        equipo={equipo}
        estado="activo"
        plantilla={[{ jugador, dorsal: 10, posicion: 'Delantero' }]}
      />,
    )
    for (const texto of [equipo.escudo, 'Bogotá', 'Dorsal 10', 'Delantero', '/jugadores/9'])
      expect(lista).toContain(texto)
    const ficha = render(
      <FichaJugador
        jugador={jugador}
        equipos={[{ equipo, dorsal: 10, posicion: 'Delantero', vigente: true }]}
        totales={{ goles: 3 }}
      />,
    )
    for (const texto of [jugador.foto, 'Dorsal 10', '/equipos/7', 'Tarjetas amarillas', '>3<'])
      expect(ficha).toContain(texto)
    expect(ficha).not.toContain('/ligas/')
  })

  it('solo destaca al campeón de un campeonato finalizado cuando el backend lo confirma', () => {
    expect(render(<CampeonPublico liga={{ estado: 'en_curso' }} campeon={equipo} />)).toBe('')
    expect(render(<CampeonPublico liga={{ estado: 'finalizado' }} />)).toContain(
      'No hay un campeón confirmado',
    )
    expect(render(<CampeonPublico liga={{ estado: 'finalizado' }} campeon={equipo} />)).toContain(
      equipo.escudo,
    )
  })

  it('el salón de la fama usa datos disponibles y no duplica a un jugador para completar puestos', () => {
    const lider = { jugador_id: 9, nombre: 'Carlos', apellido: 'Pérez', total: 4 }
    const html = render(
      <SalonFamaContenido
        consultas={{
          goles: { datos: { resultados: [lider, lider] } },
          asistencias: { datos: { resultados: [] } },
          tabla: {},
        }}
        podios={[
          { id: 'general', equipos: [{ equipo_id: 7, nombre: 'Águilas', posicion: 1, PTS: 3 }] },
        ]}
        consultaPartidos={{}}
        partidos={[]}
      />,
    )
    expect(html).toContain('Máximo goleador')
    expect(html).not.toContain('Segundo goleador')
    expect(html).toContain('Todavía no hay registros')
    expect(html).toContain('3 puntos')
    expect(html).toContain('Todavía no hay partidos finalizados')
  })

  it('ordena los tres últimos resultados y excluye encuentros sin finalizar', () => {
    const datos = [
      { id: 1, estado: 'finalizado', fecha_hora: '2026-11-19' },
      { id: 2, estado: 'finalizado', fecha_hora: '2026-11-21' },
      { id: 3, estado: 'en_curso', fecha_hora: '2026-11-22' },
      { id: 4, estado: 'finalizado', oficializado_at: '2026-11-23' },
      { id: 5, estado: 'finalizado', fecha_hora: '2026-11-18' },
    ]
    expect(ultimosResultados(datos).map((p) => p.id)).toEqual([4, 2, 1])
  })
})
