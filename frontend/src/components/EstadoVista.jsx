import ilustracion from '../assets/branding/ayuda_2.png'
import Boton from './ui/Boton'
import Aviso from './Aviso'
function EstadoVista({ cargando, error, vacio, titulo = 'Todavía no hay registros', texto = 'Comienza agregando la información de tu campeonato.', reintentar, accion, className = '' }) {
  if (cargando) return <div className="flex min-h-36 items-center justify-center gap-3 px-4 py-8 font-sans text-sm text-gris" role="status"><span aria-hidden="true" className="size-5 shrink-0 animate-spin rounded-full border-2 border-borde border-t-marca motion-reduce:animate-none" />Cargando información…</div>
  if (error) return <div className="grid justify-items-center gap-5 py-6"><Aviso error={error} />{reintentar && <Boton variante="secundario" onClick={reintentar}>Volver a intentar</Boton>}</div>
  if (vacio) return <div className={'grid min-h-72 justify-items-center gap-4 rounded-2xl border border-dashed border-borde bg-white px-5 py-10 text-center sm:py-14 ' + className}><img className="size-28 object-contain" src={ilustracion} alt="" loading="lazy" width="1254" height="1254" /><h2 className="max-w-lg font-titulo text-2xl font-bold text-oscuro">{titulo}</h2><p className="max-w-lg text-sm leading-7 text-gris">{texto}</p>{accion && <div className="mt-2">{accion}</div>}</div>
  return null
}
export default EstadoVista
