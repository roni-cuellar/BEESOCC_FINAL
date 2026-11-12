import { Link } from 'react-router-dom'
import ilustracion from '../assets/branding/ayuda_2.png'
import { Boton, Tarjeta } from './ui'
import Icono from './Icono'

function LigaCard({ liga, onEliminar }) {
  const estado = liga.estado === 'planificacion' ? 'Planificación' : liga.estado.replaceAll('_', ' ')
  return <Tarjeta as="article" sinRelleno className="flex h-full flex-col overflow-hidden transition-shadow hover:shadow-md">
    <div className="relative flex h-44 items-center justify-between overflow-hidden bg-[#edf5ef] px-6 sm:h-48"><div className="z-1"><p className="text-[10px] font-semibold tracking-[.15em] text-marca-oscuro">BEESOCC</p><p className="mt-2 font-titulo text-2xl font-bold text-marca-oscuro">Temporada<br />{liga.temporada}</p></div><img className="size-36 object-contain sm:size-44" src={ilustracion} alt="" loading="lazy" width="1254" height="1254" /></div>
    <div className="flex flex-1 flex-col p-5 sm:p-6"><span className="mb-4 w-fit rounded-full border border-green-100 bg-green-50 px-3 py-1 text-[11px] font-semibold text-marca-oscuro">{estado}</span><h2 className="font-titulo text-xl leading-snug font-bold break-words text-oscuro">{liga.nombre}</h2><p className="mt-3 line-clamp-2 text-sm leading-6 break-words text-gris">{liga.descripcion || 'Sin descripción'}</p><p className="mt-4 mb-6 flex items-center gap-2 text-xs text-gris"><Icono nombre="partidos" />{liga.formato === 'todos_contra_todos' ? 'Todos contra todos' : liga.formato}</p><Boton className="mt-auto w-full" to={'/ligas/' + liga.id + '/dashboard'}>Administrar</Boton><div className="mt-4 flex items-center justify-between gap-3 border-t border-borde pt-3"><Link className="inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-marca-oscuro hover:bg-green-50" to={'/ligas/' + liga.id + '/editar'} aria-label={'Editar ' + liga.nombre}>Editar liga</Link><button type="button" className="inline-flex min-h-11 items-center rounded-lg px-2 text-sm text-red-700 hover:bg-red-50" onClick={() => onEliminar(liga)} aria-label={'Eliminar ' + liga.nombre}>Eliminar</button></div></div>
  </Tarjeta>
}
export default LigaCard
