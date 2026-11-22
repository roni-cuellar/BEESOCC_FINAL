import { useState } from 'react'
import IlustracionModulo from './IlustracionModulo'
import { urlImagen } from '../services/api'

export default function LogoCampeonato({
  ruta,
  respaldo = <IlustracionModulo tipo="campeonatos" />,
  className = 'size-16 sm:size-24',
  alt = '',
}) {
  const [fallo, setFallo] = useState('')
  const src = urlImagen(ruta)

  if (!src || src === fallo) return respaldo

  return (
    <img
      src={src}
      alt={alt}
      className={`shrink-0 rounded-xl border border-green-100 bg-white object-contain p-1 ${className}`}
      onError={() => setFallo(src)}
    />
  )
}
