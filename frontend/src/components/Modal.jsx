import { useEffect, useRef } from 'react'
import Icono from './Icono'
function Modal({ titulo, onCerrar, bloqueado = false, children }) {
  const referencia = useRef(null)
  useEffect(() => {
    const anterior = document.activeElement
    const dialogo = referencia.current
    dialogo.showModal()
    return () => {
      dialogo.close()
      if (anterior?.isConnected) anterior.focus()
    }
  }, [])
  return (
    <dialog
      ref={referencia}
      className="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-2xl border border-borde bg-white p-5 text-oscuro shadow-2xl backdrop:bg-marca-oscuro/70 sm:p-7"
      aria-label={titulo}
      onCancel={(e) => {
        e.preventDefault()
        if (!bloqueado) onCerrar()
      }}
    >
      <div className="mb-6 flex items-start justify-between gap-4">
        <h2 className="font-titulo text-2xl font-bold">{titulo}</h2>
        <button
          type="button"
          className="-mt-2 -mr-2 grid size-11 shrink-0 place-items-center rounded-xl border border-borde hover:bg-gray-50 disabled:opacity-50"
          onClick={onCerrar}
          disabled={bloqueado}
          aria-label="Cerrar ventana"
        >
          <Icono nombre="cerrar" />
        </button>
      </div>
      {children}
    </dialog>
  )
}
export default Modal
