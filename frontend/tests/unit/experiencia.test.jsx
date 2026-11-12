import { describe, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { Boton, Confirmacion, EstadoVista } from '../../src/components/ui/index.js'
import LigaCard from '../../src/components/LigaCard.jsx'
import AccesoLayout from '../../src/components/AccesoLayout.jsx'
import RegistroPage from '../../src/pages/RegistroPage.jsx'

const render = elemento => renderToStaticMarkup(<MemoryRouter>{elemento}</MemoryRouter>)

describe('experiencia de las pantallas principales', () => {
  it('una acción de navegación sigue siendo un enlace accesible', () => {
    const html = render(<Boton to="/ligas/nueva">Crear liga</Boton>)
    expect(html).toContain('href="/ligas/nueva"')
    expect(html).not.toContain('type="button"')
  })

  it('un enlace bloqueado sale del recorrido del teclado', () => {
    const html = render(<Boton to="/mis-ligas" disabled>Cancelar</Boton>)
    expect(html).toContain('aria-disabled="true"')
    expect(html).toContain('tabindex="-1"')
  })

  it('la tarjeta usa la liga recibida y enlaza a su edición y dashboard', () => {
    const html = render(<LigaCard liga={{ id: 28, nombre: 'Liga de prueba', temporada: '2026', estado: 'planificacion', formato: 'todos_contra_todos' }} onEliminar={() => {}} />)
    expect(html).toContain('/ligas/28/dashboard')
    expect(html).toContain('/ligas/28/editar')
    expect(html).toContain('aria-label="Eliminar Liga de prueba"')
    expect(html).toContain('Planificación')
    expect(html).not.toContain('undefined')
  })

  it('un error tiene prioridad sobre una lista vacía y permite reintentar', () => {
    const html = render(<EstadoVista error="No se pudo conectar." vacio reintentar={() => {}} />)
    expect(html).toContain('role="alert"')
    expect(html).toContain('Volver a intentar')
    expect(html).not.toContain('Todavía no hay registros')
  })

  it('confirmar y cancelar son botones que no envían formularios', () => {
    const html = render(<Confirmacion titulo="Eliminar liga" texto="¿Continuar?" onConfirmar={async () => {}} onCerrar={() => {}} />)
    expect(html).toContain('<dialog')
    expect(html).toContain('aria-label="Eliminar liga"')
    expect(html.match(/type="button"/g)).toHaveLength(3)
  })

  it('el layout de acceso no incorpora navbar ni footer públicos', () => {
    const html = render(<AccesoLayout />)
    expect(html).toContain('Acceso a BEESOCC')
    expect(html).not.toContain('<nav')
    expect(html).not.toContain('<footer')
  })

  it('el registro informa su alcance visual y conserva etiquetas explícitas', () => {
    const html = render(<RegistroPage />)
    expect(html).toContain('No se crea una cuenta ni se guardan tus datos.')
    expect(html).toContain('for="registro-confirmacion"')
    expect(html).toContain('href="/login"')
  })
})
