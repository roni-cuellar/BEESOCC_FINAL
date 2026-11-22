import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import useAuthStore from '../stores/useAuthStore'
import { Boton, Campo } from '../components/ui'

function LoginPage() {
  const navegar = useNavigate()
  const { state } = useLocation()
  const { autenticar, cargando, error, limpiarError } = useAuthStore()
  useEffect(() => {
    limpiarError()
  }, [limpiarError])
  const [datos, setDatos] = useState({ correo: '', contrasena: '' })
  async function entrar(evento) {
    evento.preventDefault()
    if (await autenticar('login', { email: datos.correo, password: datos.contrasena })) {
      setDatos({ correo: '', contrasena: '' })
      navegar('/seleccion-roles', { replace: true, state })
    }
  }
  return (
    <>
      <p className="mb-3 text-xs font-semibold tracking-widest text-marca-oscuro">
        VUELVE A LA CANCHA
      </p>
      <h1 className="font-titulo text-3xl font-bold text-oscuro">Iniciar sesión</h1>
      <p className="mt-3 mb-7 text-sm leading-6 text-gris">Accede a tu cuenta de BEESOCC.</p>
      <form onSubmit={entrar} aria-busy={cargando} className="grid gap-5">
        <Campo
          etiqueta="Correo electrónico"
          id="login-correo"
          type="email"
          required
          maxLength={255}
          autoComplete="email"
          placeholder="nombre@correo.com"
          value={datos.correo}
          onChange={(e) => setDatos({ ...datos, correo: e.target.value })}
        />
        <Campo
          etiqueta="Contraseña"
          id="login-contrasena"
          type="password"
          required
          autoComplete="current-password"
          placeholder="Escribe tu contraseña"
          value={datos.contrasena}
          onChange={(e) => setDatos({ ...datos, contrasena: e.target.value })}
        />
        {error && (
          <p role="alert" className="text-sm text-red-700">
            {error}
          </p>
        )}
        <Boton type="submit" disabled={cargando} aria-busy={cargando} className="w-full">
          {cargando ? 'Iniciando sesión…' : 'Iniciar sesión'}
        </Boton>
      </form>
      <p className="mt-6 text-center text-sm text-gris">
        ¿No tienes cuenta?{' '}
        <Link
          className="inline-flex min-h-11 items-center font-semibold text-marca-oscuro underline-offset-4 hover:underline"
          to="/registro"
        >
          Regístrate
        </Link>
      </p>
      <p className="mt-3 border-t border-borde pt-4 text-xs leading-5 text-gris">
        Accede con el correo y la contraseña de tu cuenta.
      </p>
    </>
  )
}
export default LoginPage
