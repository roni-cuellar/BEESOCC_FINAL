import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Boton, Campo } from '../components/ui'
import useAuthStore from '../stores/useAuthStore'

function RegistroPage() {
  const navegar = useNavigate()
  const { autenticar, cargando, error: errorServidor, limpiarError } = useAuthStore()
  useEffect(() => {
    limpiarError()
  }, [limpiarError])
  const [datos, setDatos] = useState({ nombre: '', correo: '', contrasena: '', confirmacion: '' })
  const [error, setError] = useState('')
  async function continuar(evento) {
    evento.preventDefault()
    if (datos.contrasena !== datos.confirmacion) {
      setError('Las contraseñas no coinciden.')
      return
    }
    if (
      await autenticar('registrar', {
        name: datos.nombre,
        email: datos.correo,
        password: datos.contrasena,
        password_confirmation: datos.confirmacion,
      })
    ) {
      setDatos({ nombre: '', correo: '', contrasena: '', confirmacion: '' })
      navegar('/seleccion-roles', { replace: true })
    }
  }
  function cambiar(campo, valor) {
    setDatos({ ...datos, [campo]: valor })
    setError('')
    limpiarError()
  }
  return (
    <>
      <p className="mb-3 text-xs font-semibold tracking-widest text-marca-oscuro">
        DALE FORMA A TU LIGA
      </p>
      <h1 className="font-titulo text-3xl font-bold text-oscuro">Crear cuenta</h1>
      <p className="mt-3 mb-7 text-sm leading-6 text-gris">
        El primer paso para organizar tu próximo campeonato.
      </p>
      <form onSubmit={continuar} aria-busy={cargando} className="grid gap-4">
        <Campo
          etiqueta="Nombre"
          id="registro-nombre"
          required
          maxLength={255}
          autoComplete="name"
          placeholder="Tu nombre"
          value={datos.nombre}
          onChange={(e) => cambiar('nombre', e.target.value)}
        />
        <Campo
          etiqueta="Correo electrónico"
          id="registro-correo"
          type="email"
          required
          maxLength={255}
          autoComplete="email"
          placeholder="nombre@correo.com"
          value={datos.correo}
          onChange={(e) => cambiar('correo', e.target.value)}
        />
        <Campo
          etiqueta="Contraseña"
          id="registro-contrasena"
          type="password"
          required
          minLength={8}
          maxLength={72}
          autoComplete="new-password"
          placeholder="Escribe tu contraseña"
          ayuda="Usa entre 8 y 72 caracteres."
          value={datos.contrasena}
          onChange={(e) => cambiar('contrasena', e.target.value)}
        />
        <Campo
          etiqueta="Confirmar contraseña"
          id="registro-confirmacion"
          type="password"
          required
          minLength={8}
          maxLength={72}
          autoComplete="new-password"
          placeholder="Repite tu contraseña"
          value={datos.confirmacion}
          error={error}
          onChange={(e) => cambiar('confirmacion', e.target.value)}
        />
        {errorServidor && (
          <p role="alert" className="text-sm text-red-700">
            {errorServidor}
          </p>
        )}
        <Boton type="submit" disabled={cargando} aria-busy={cargando} className="w-full">
          {cargando ? 'Creando cuenta…' : 'Continuar'}
        </Boton>
      </form>
      <p className="mt-5 text-center text-sm text-gris">
        ¿Ya tienes cuenta?{' '}
        <Link
          className="inline-flex min-h-11 items-center font-semibold text-marca-oscuro underline-offset-4 hover:underline"
          to="/login"
        >
          Inicia sesión
        </Link>
      </p>
      <p className="mt-3 border-t border-borde pt-4 text-xs leading-5 text-gris">
        Crea tu cuenta para organizar tus campeonatos.
      </p>
    </>
  )
}
export default RegistroPage
