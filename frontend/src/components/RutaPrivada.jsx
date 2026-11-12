import { useEffect } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import useAuthStore from '../stores/useAuthStore'
import EstadoVista from './EstadoVista'

export default function RutaPrivada() {
  const { usuario, verificado, cargando, error, verificar } = useAuthStore()
  const ubicacion = useLocation()
  useEffect(() => { if (!verificado) verificar() }, [verificado, verificar])
  if (!verificado) return <EstadoVista cargando={cargando || !error} error={error} reintentar={verificar} />
  if (!usuario) return <Navigate to="/login" state={{ desde: ubicacion.pathname + ubicacion.search }} replace />
  return <Outlet />
}
