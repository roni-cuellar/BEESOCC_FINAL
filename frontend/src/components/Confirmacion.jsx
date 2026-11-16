import { useEffect, useRef, useState } from 'react'
import Modal from './Modal'
import Aviso from './Aviso'
import { mensajeError } from '../services/api'
import Boton from './ui/Boton'
function Confirmacion({ titulo, texto, onConfirmar, onCerrar }) {
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState('')
  const confirmarRef = useRef(null)
  const enviando = useRef(false)
  useEffect(() => {
    if (error) confirmarRef.current?.focus()
  }, [error])
  async function confirmar() {
    if (enviando.current) return
    enviando.current = true
    setGuardando(true)
    setError('')
    try {
      await onConfirmar()
      onCerrar()
    } catch (error) {
      setError(mensajeError(error))
      setGuardando(false)
    } finally {
      enviando.current = false
    }
  }
  return (
    <Modal titulo={titulo} onCerrar={onCerrar} bloqueado={guardando}>
      <div className="texto-suave">{texto}</div>
      <Aviso error={error} />
      <div className="flex flex-wrap justify-end gap-3">
        <Boton variante="secundario" disabled={guardando} onClick={onCerrar}>
          Cancelar
        </Boton>
        <Boton
          ref={confirmarRef}
          disabled={guardando}
          aria-busy={guardando || undefined}
          onClick={confirmar}
        >
          {guardando ? 'Procesando…' : 'Confirmar'}
        </Boton>
      </div>
    </Modal>
  )
}
export default Confirmacion
