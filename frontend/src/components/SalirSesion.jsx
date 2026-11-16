import { useNavigate } from 'react-router-dom'
import useAuthStore from '../stores/useAuthStore'

export default function SalirSesion({ className, onCerrar }) {
  const navegar = useNavigate()
  const { salir, cargando, error } = useAuthStore()
  async function cerrar() {
    if (await salir()) {
      onCerrar?.()
      navegar('/login', { replace: true })
    }
  }
  return (
    <>
      <button type="button" className={className} disabled={cargando} onClick={cerrar}>
        {cargando ? 'Saliendo…' : 'Salir'}
      </button>
      {error && (
        <p role="alert" className="text-sm">
          {error}
        </p>
      )}
    </>
  )
}
