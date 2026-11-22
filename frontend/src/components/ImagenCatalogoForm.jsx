import { useEffect, useState } from 'react'
import ImagenEntidad from './ImagenEntidad'
import { validarArchivoImagen } from '../services/imagenCatalogo'

export default function ImagenCatalogoForm({
  campo,
  etiqueta,
  actual,
  nombre,
  archivo,
  eliminar,
  onArchivo,
  onEliminar,
  renderizarPrevia,
}) {
  const [error, setError] = useState('')
  const [previa, setPrevia] = useState(null)
  useEffect(() => {
    if (!previa || archivo !== previa.archivo) return
    return () => URL.revokeObjectURL(previa.url)
  }, [archivo, previa])

  function seleccionar(e) {
    const seleccion = e.target.files[0] || null
    const mensaje = validarArchivoImagen(seleccion)
    e.target.setCustomValidity(mensaje)
    setError(mensaje)
    const nuevo = mensaje ? null : seleccion
    if (renderizarPrevia)
      setPrevia(nuevo ? { archivo: nuevo, url: URL.createObjectURL(nuevo) } : null)
    onArchivo(nuevo)
  }
  return (
    <div>
      <label>
        {etiqueta} (opcional)
        <input
          type="file"
          name={campo}
          accept="image/jpeg,image/png,image/webp"
          onChange={seleccionar}
          aria-invalid={!!error}
          aria-describedby={`${campo}-ayuda`}
        />
      </label>
      <p id={`${campo}-ayuda`} className="nota">
        JPG, PNG o WEBP · máximo 5 MB. Sin archivo nuevo se conserva la imagen actual.
      </p>
      {error && <p role="alert">{error}</p>}
      {renderizarPrevia &&
        renderizarPrevia({
          url: archivo && previa?.archivo === archivo ? previa.url : null,
          actual: eliminar ? null : actual,
        })}
      {actual && (
        <>
          {!renderizarPrevia && <ImagenEntidad ruta={actual} nombre={nombre} />}
          <label>
            <input
              type="checkbox"
              checked={eliminar}
              disabled={!!archivo}
              onChange={(e) => onEliminar(e.target.checked)}
            />{' '}
            Quitar {etiqueta.toLowerCase()} actual al guardar
          </label>
        </>
      )}
    </div>
  )
}
