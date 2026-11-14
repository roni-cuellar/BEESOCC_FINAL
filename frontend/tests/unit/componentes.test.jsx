import { describe, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import Boton from '../../src/components/ui/Boton.jsx'
import Campo from '../../src/components/ui/Campo.jsx'
import { fechaLocalCampo, fechaParaGuardar, transicionesPartido } from '../../src/utils/partidos.js'

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
  it('editar lugar o jornada conserva los segundos de la fecha sin cambios', () => {
    const original = '2026-11-14T22:17:42.000000Z'
    expect(fechaParaGuardar(fechaLocalCampo(original), original)).toBe(original)
  })

  it('permite cambiar explícitamente la programación sin conservar la fecha anterior', () => {
    const original = '2026-11-14T22:17:42.000000Z'
    const nueva = '2026-11-15T18:30'
    expect(fechaParaGuardar(nueva, original)).toBe(new Date(nueva).toISOString())
  })

  it('permite quitar la fecha o crear un partido sin programación', () => {
    expect(fechaParaGuardar('', '2026-11-14T22:17:42Z')).toBe(null)
    expect(fechaParaGuardar('', undefined)).toBe(null)
  })
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
