import Icono from './Icono'

function Aviso({ error, exito, onCerrar }) {
  if (!error && !exito) return null
  return <div className={'my-4 flex items-start gap-3 rounded-xl border px-4 py-3 font-sans text-sm leading-6 break-words ' + (error ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-marca-oscuro')} role={error ? 'alert' : 'status'}>
    <Icono className="mt-1 shrink-0" nombre={error ? 'alerta' : 'exito'} />
    <p className="min-w-0 flex-1">{error || exito}</p>
    {onCerrar && <button type="button" className="-my-2 -mr-2 grid size-11 shrink-0 place-items-center rounded-lg hover:bg-black/5" aria-label="Cerrar aviso" onClick={onCerrar}><Icono nombre="cerrar" /></button>}
  </div>
}
export default Aviso
