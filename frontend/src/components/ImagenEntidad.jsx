import { useState } from 'react'
import { urlImagen } from '../services/api'
function ImagenEntidad({ ruta, nombre, className = '', decorativa = false }) {
  const [fallo, setFallo] = useState('')
  const src = urlImagen(ruta)
  return src && fallo !== src ? (
    <img
      className={`imagen-entidad ${className}`}
      src={src}
      alt={decorativa ? '' : nombre}
      loading="lazy"
      onError={() => setFallo(src)}
    />
  ) : (
    <span
      className={`imagen-entidad placeholder-entidad ${className}`}
      aria-hidden={decorativa || undefined}
      aria-label={decorativa ? undefined : 'Sin imagen de ' + nombre}
    >
      {nombre?.trim().slice(0, 2).toUpperCase() || 'BE'}
    </span>
  )
}
export default ImagenEntidad
