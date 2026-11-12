import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Boton, Campo } from '../components/ui'

function RegistroPage() {
  const navegar = useNavigate()
  const [datos, setDatos] = useState({ nombre: '', correo: '', contrasena: '', confirmacion: '' })
  const [error, setError] = useState('')
  function continuar(evento) {
    evento.preventDefault()
    if (datos.contrasena !== datos.confirmacion) { setError('Las contraseñas no coinciden.'); return }
    // Se conserva la navegación visual; no se crea una cuenta ni se envían datos.
    setDatos({ nombre: '', correo: '', contrasena: '', confirmacion: '' })
    navegar('/mis-ligas', { replace: true })
  }
  function cambiar(campo, valor) { setDatos({ ...datos, [campo]: valor }); setError('') }
  return <>
    <p className="mb-3 text-xs font-semibold tracking-widest text-marca-oscuro">DALE FORMA A TU LIGA</p>
    <h1 className="font-titulo text-3xl font-bold text-oscuro">Crear cuenta</h1>
    <p className="mt-3 mb-7 text-sm leading-6 text-gris">El primer paso para organizar tu próximo campeonato.</p>
    <form onSubmit={continuar} autoComplete="off" className="grid gap-4">
      <Campo etiqueta="Nombre" id="registro-nombre" autoComplete="off" placeholder="Tu nombre" value={datos.nombre} onChange={e => cambiar('nombre', e.target.value)} />
      <Campo etiqueta="Correo electrónico" id="registro-correo" type="email" autoComplete="off" placeholder="nombre@correo.com" value={datos.correo} onChange={e => cambiar('correo', e.target.value)} />
      <Campo etiqueta="Contraseña" id="registro-contrasena" type="password" autoComplete="off" placeholder="Escribe tu contraseña" value={datos.contrasena} onChange={e => cambiar('contrasena', e.target.value)} />
      <Campo etiqueta="Confirmar contraseña" id="registro-confirmacion" type="password" autoComplete="off" placeholder="Repite tu contraseña" value={datos.confirmacion} error={error} onChange={e => cambiar('confirmacion', e.target.value)} />
      <Boton type="submit" className="w-full">Continuar</Boton>
    </form>
    <p className="mt-5 text-center text-sm text-gris">¿Ya tienes cuenta? <Link className="inline-flex min-h-11 items-center font-semibold text-marca-oscuro underline-offset-4 hover:underline" to="/login">Inicia sesión</Link></p>
    <p className="mt-3 border-t border-borde pt-4 text-xs leading-5 text-gris">Formulario visual local. No se crea una cuenta ni se guardan tus datos.</p>
  </>
}
export default RegistroPage
