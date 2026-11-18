import campeonatos from '../assets/branding/optimizadas/campeonatos.png'
import equipos from '../assets/branding/optimizadas/equipos.png'
import partidos from '../assets/branding/optimizadas/partidos.png'
import estadisticas from '../assets/branding/optimizadas/estadisticas.png'
import arbitraje from '../assets/branding/optimizadas/arbitraje-actas.png'
import grupos from '../assets/branding/optimizadas/fase-grupos.png'
import eliminacion from '../assets/branding/optimizadas/eliminacion-directa.png'
import desempates from '../assets/branding/optimizadas/penales-desempates.png'
import plantillas from '../assets/branding/optimizadas/plantillas.png'
import publico from '../assets/branding/optimizadas/consulta-publica.png'

const ilustraciones = {
  campeonatos,
  equipos,
  partidos,
  estadisticas,
  arbitraje,
  grupos,
  eliminacion,
  desempates,
  plantillas,
  publico,
}

export default function IlustracionModulo({ tipo }) {
  const imagen = ilustraciones[tipo]
  if (!imagen) return null
  const lado = ['equipos', 'partidos', 'estadisticas'].includes(tipo)
    ? 512
    : tipo === 'campeonatos'
      ? 384
      : 256

  // Acompaña un título visible: es decorativa y no repite su lectura accesible.
  return (
    <img
      src={imagen}
      alt=""
      width={lado}
      height={lado}
      loading="lazy"
      decoding="async"
      className="h-16 w-16 shrink-0 object-contain sm:h-24 sm:w-24"
    />
  )
}
