import { useState } from 'react'
import ImagenEntidad from './ImagenEntidad'
import { validarArchivoImagen } from '../services/imagenCatalogo'

export default function ImagenCatalogoForm({ campo, etiqueta, actual, nombre, archivo, eliminar, onArchivo, onEliminar }) {
  const [error, setError] = useState('')
  function seleccionar(e) {
    const seleccion = e.target.files[0] || null
    const mensaje = validarArchivoImagen(seleccion)
    e.target.setCustomValidity(mensaje)
    setError(mensaje)
    onArchivo(mensaje ? null : seleccion)
  }
  return <div>
    <label>{etiqueta} (opcional)<input type="file" name={campo} accept="image/jpeg,image/png,image/webp" onChange={seleccionar} aria-invalid={!!error} aria-describedby={`${campo}-ayuda`} /></label>
    <p id={`${campo}-ayuda`} className="nota">JPG, PNG o WEBP · máximo 5 MB. Sin archivo nuevo se conserva la imagen actual.</p>
    {error && <p role="alert">{error}</p>}
    {actual && <><ImagenEntidad ruta={actual} nombre={nombre} /><label><input type="checkbox" checked={eliminar} disabled={!!archivo} onChange={e => onEliminar(e.target.checked)} /> Quitar {etiqueta.toLowerCase()} actual al guardar</label></>}
  </div>
}
