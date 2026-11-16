import { afterEach, describe, expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import api from '../../src/services/api.js'
import equipo from '../../src/services/equipoService.js'
import jugador from '../../src/services/jugadorService.js'
import { validarArchivoImagen } from '../../src/services/imagenCatalogo.js'
import EquipoForm from '../../src/components/EquipoForm.jsx'
import JugadorForm from '../../src/components/JugadorForm.jsx'

const adaptador = api.defaults.adapter
afterEach(() => { api.defaults.adapter = adaptador })

describe('contratos de fotos y escudos', () => {
  for (const [servicio, campo, entidad, ruta] of [[equipo, 'escudo', 'equipo', '/equipos'], [jugador, 'foto', 'jugador', '/jugadores']]) {
    it(`${campo}: crea y reemplaza archivo con multipart y método interpretado por PHP`, async () => {
      const envios = []
      api.defaults.adapter = async config => {
        envios.push(config)
        return { data: { [entidad]: { id: 9 } }, status: 200, headers: {}, config }
      }
      const archivo = new File(['ficticio'], 'imagen.png', { type: 'image/png' })
      await servicio.crear({ nombre: 'Ficticio', [campo]: archivo })
      await servicio.actualizar(9, { nombre: 'Nuevo', [campo]: archivo })
      expect(envios[0].url).toBe(ruta)
      expect(envios[0].method).toBe('post')
      expect(envios[0].data.get(campo).name).toBe('imagen.png')
      expect(envios[0].data.has('_method')).toBe(false)
      expect(envios[1].url).toBe(ruta + '/9')
      expect(envios[1].method).toBe('post')
      expect(envios[1].data.get('_method')).toBe('PATCH')
    })

    it(`${campo}: conserva por omisión y solo quita con una acción explícita`, async () => {
      const envios = []
      api.defaults.adapter = async config => {
        envios.push(JSON.parse(config.data))
        return { data: { [entidad]: {} }, status: 200, headers: {}, config }
      }
      await servicio.actualizar(9, { nombre: 'Ficticio', [campo]: null, eliminar_imagen: false })
      await servicio.actualizar(9, { nombre: 'Ficticio', eliminar_imagen: true })
      expect(envios).toEqual([{ nombre: 'Ficticio' }, { nombre: 'Ficticio', eliminar_imagen: true }])
    })
  }

  it('valida formatos, tamaño máximo y ausencia de archivo', () => {
    expect(validarArchivoImagen(null)).toBe('')
    for (const type of ['image/jpeg', 'image/png', 'image/webp']) expect(validarArchivoImagen({ type, size: 5 * 1024 * 1024 })).toBe('')
    expect(validarArchivoImagen({ type: 'image/png', size: 5 * 1024 * 1024 + 1 })).toContain('5 MB')
    expect(validarArchivoImagen({ type: 'image/svg+xml', size: 1 })).toContain('JPG')
    expect(validarArchivoImagen({ type: 'text/plain', size: 1 })).not.toBe('')
  })

  it('propaga el fallo de subida sin reintentar ni cambiar la referencia por otro envío', async () => {
    let envios = 0
    const error = { response: { status: 502 } }
    api.defaults.adapter = async () => { envios++; throw error }
    await expect(jugador.actualizar(9, { foto: new File(['ficticio'], 'imagen.png', { type: 'image/png' }) })).rejects.toBe(error)
    expect(envios).toBe(1)
  })

  it('ambos formularios muestran archivo, referencia actual y eliminación explícita', () => {
    for (const html of [
      renderToStaticMarkup(<EquipoForm equipo={{ nombre: 'Ficticio', escudo: 'https://example.test/a.png' }} />),
      renderToStaticMarkup(<JugadorForm jugador={{ nombre: 'Ficticio', foto: 'https://example.test/a.png' }} />),
    ]) {
      expect(html).toContain('type="file"')
      expect(html).toContain('image/jpeg,image/png,image/webp')
      expect(html).toContain('type="checkbox"')
      expect(html).toContain('actual al guardar')
      expect(html).toContain('se conserva la imagen actual')
      expect(html).not.toContain('URL de la foto')
    }
  })
})
