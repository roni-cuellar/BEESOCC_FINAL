import { Link } from 'react-router-dom'
import ImagenEntidad from '../ImagenEntidad'
import '../../styles/tabla-deportiva.css'

export function IdentidadDeportiva({ nombre, imagen, to, primaria = false }) {
  const contenido = (
    <>
      <ImagenEntidad ruta={imagen} nombre={nombre} decorativa className="deportivo-avatar" />
      <span>{nombre}</span>
    </>
  )
  return to ? (
    <Link
      className={`identidad-deportiva${primaria ? ' identidad-deportiva-principal' : ''}`}
      to={to}
    >
      {contenido}
    </Link>
  ) : (
    <span className="identidad-deportiva">{contenido}</span>
  )
}

export function PuestoDeportivo({ valor }) {
  return (
    <span className="deportivo-puesto" data-podio={valor >= 1 && valor <= 3 ? valor : undefined}>
      {valor ?? '—'}
    </span>
  )
}

export default function TablaDeportiva({ titulo, descripcionId, children, catalogo = false }) {
  return (
    <div
      className={catalogo ? 'tabla-deportiva tabla-deportiva-catalogo' : 'tabla-deportiva'}
      role="region"
      tabIndex={0}
      aria-label={titulo}
      aria-describedby={descripcionId}
    >
      <table>
        <caption className="sr-only">{titulo}</caption>
        {children}
      </table>
    </div>
  )
}
