import { describe, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import Boton from '../../src/components/ui/Boton.jsx'
import Campo from '../../src/components/ui/Campo.jsx'
import { fechaLocalCampo, transicionesPartido } from '../../src/utils/partidos.js'

describe('contratos accesibles de los componentes base', () => {
  it('un botón en carga no envía formularios ni permite nuevas acciones', () => {
    const html = renderToStaticMarkup(<Boton cargando>Guardar</Boton>)
    expect(html).toContain('type="button"')
    expect(html).toContain('disabled=""')
    expect(html).toContain('aria-busy="true"')
  })

  it('asocia el mensaje de validación con el campo y su etiqueta', () => {
    const html = renderToStaticMarkup(<Campo id="nombre" etiqueta="Nombre" error="Es obligatorio" required />)
    expect(html).toContain('for="nombre"')
    expect(html).toContain('aria-invalid="true"')
    expect(html).toContain('aria-describedby="nombre-descripcion"')
    expect(html).toContain('id="nombre-descripcion"')
    expect(html).toContain('role="alert"')
  })

  it('conserva opciones y validación nativa del select', () => {
    const html = renderToStaticMarkup(<Campo as="select" id="formato" etiqueta="Formato" required><option value="liga">Liga</option></Campo>)
    expect(html).toContain('<select')
    expect(html).toContain('required=""')
    expect(html).toContain('<option value="liga">Liga</option>')
  })
})

describe('reglas actuales de presentación de partidos', () => {
  it('no ofrece transiciones desde estados definitivos', () => {
    expect(transicionesPartido.finalizado).toEqual([])
    expect(transicionesPartido.cancelado).toEqual([])
    expect(transicionesPartido.suspendido).toContain('en_curso')
  })

  it('no rellena el campo de programación con una fecha inválida', () => {
    expect(fechaLocalCampo(null)).toBe('')
    expect(fechaLocalCampo('fecha inválida')).toBe('')
  })
})
