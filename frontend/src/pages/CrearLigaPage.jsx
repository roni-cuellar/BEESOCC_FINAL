import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import ligaService from '../services/ligaService'
import { mensajeError } from '../services/api'
import Aviso from '../components/Aviso'
import EstadoVista from '../components/EstadoVista'
import { Boton, Campo, Tarjeta } from '../components/ui'
import EncabezadoPagina from '../components/layout/EncabezadoPagina'

function CrearLigaPage() {
  const { ligaId } = useParams()
  const navegar = useNavigate()
  const [datos, setDatos] = useState({
    nombre: '',
    descripcion: '',
    temporada: '',
    formato: 'todos_contra_todos',
  })
  const [cargando, setCargando] = useState(Boolean(ligaId))
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState('')
  const [errores, setErrores] = useState({})
  const formularioRef = useRef(null)
  useEffect(() => {
    if (error) formularioRef.current?.querySelector('[aria-invalid="true"]')?.focus()
  }, [error])
  const [errorConsulta, setErrorConsulta] = useState('')
  const [revision, setRevision] = useState(0)
  useEffect(() => {
    let vigente = true
    async function consultar() {
      setCargando(Boolean(ligaId))
      setErrorConsulta('')
      setError('')
      setErrores({})
      setDatos({ nombre: '', descripcion: '', temporada: '', formato: 'todos_contra_todos' })
      if (!ligaId) return
      try {
        const liga = await ligaService.obtener(ligaId)
        if (vigente)
          setDatos({
            nombre: liga.nombre,
            descripcion: liga.descripcion || '',
            temporada: liga.temporada,
            formato: liga.formato,
          })
      } catch (e) {
        if (vigente) setErrorConsulta(mensajeError(e))
      } finally {
        if (vigente) setCargando(false)
      }
    }
    consultar()
    return () => {
      vigente = false
    }
  }, [ligaId, revision])
  function cambiar(e) {
    setDatos({ ...datos, [e.target.name]: e.target.value })
    setErrores((actual) => ({ ...actual, [e.target.name]: undefined }))
  }
  async function guardar(e) {
    e.preventDefault()
    setGuardando(true)
    setError('')
    setErrores({})
    try {
      const envio = {
        ...datos,
        nombre: datos.nombre.trim(),
        descripcion: datos.descripcion.trim() || null,
        temporada: datos.temporada.trim(),
      }
      if (ligaId) await ligaService.actualizar(ligaId, envio)
      else await ligaService.crear(envio)
      navegar('/mis-ligas', {
        state: {
          mensaje: ligaId ? 'Liga actualizada correctamente.' : 'Liga creada correctamente.',
        },
      })
    } catch (e) {
      setError(mensajeError(e))
      if (e.response?.status === 422) setErrores(e.response.data?.errors || {})
      setGuardando(false)
    }
  }
  return (
    <section className="mx-auto w-full max-w-4xl px-5 py-8 sm:px-8 lg:py-12">
      <Link
        className="mb-6 inline-flex min-h-11 items-center text-sm font-semibold text-marca-oscuro hover:underline"
        to="/mis-ligas"
      >
        Mis ligas
      </Link>
      <EncabezadoPagina
        titulo={ligaId ? 'Editar liga' : 'Crear liga'}
        descripcion="Define la identidad del campeonato. Equipos, vueltas y calendario se organizan desde su dashboard."
      />
      <EstadoVista
        cargando={cargando}
        error={errorConsulta}
        reintentar={() => setRevision((r) => r + 1)}
      />
      {!cargando && !errorConsulta && (
        <Tarjeta as="form" ref={formularioRef} className="grid gap-6" onSubmit={guardar}>
          <Aviso error={error} />
          <Campo
            etiqueta="Nombre"
            name="nombre"
            required
            maxLength={150}
            value={datos.nombre}
            onChange={cambiar}
            disabled={guardando}
            error={errores.nombre?.[0]}
          />
          <Campo
            as="textarea"
            etiqueta="Descripción (opcional)"
            name="descripcion"
            value={datos.descripcion}
            onChange={cambiar}
            disabled={guardando}
            error={errores.descripcion?.[0]}
          />
          <div className="grid items-start gap-4 sm:grid-cols-2">
            <Campo
              etiqueta="Temporada"
              name="temporada"
              required
              maxLength={50}
              placeholder="2026"
              value={datos.temporada}
              onChange={cambiar}
              disabled={guardando}
              error={errores.temporada?.[0]}
            />
            <Campo
              as="select"
              etiqueta="Formato"
              name="formato"
              value={datos.formato}
              onChange={cambiar}
              disabled={guardando}
              error={errores.formato?.[0]}
              ayuda="Define cómo se organiza la competición. La modalidad (Fútbol 5, 7, 11 o personalizada) se configura en el dashboard."
            >
              <option value="todos_contra_todos">Todos contra todos</option>
              <option value="eliminacion_directa">Eliminación directa</option>
              <option value="grupos_eliminacion">Grupos + eliminación</option>
            </Campo>
          </div>

          <div className="flex flex-wrap justify-end gap-3 border-t border-borde pt-5">
            <Boton to="/mis-ligas" variante="secundario" disabled={guardando}>
              Cancelar
            </Boton>
            <Boton type="submit" cargando={guardando} disabled={Boolean(ligaId) && !datos.nombre}>
              Guardar liga
            </Boton>
          </div>
        </Tarjeta>
      )}
    </section>
  )
}
export default CrearLigaPage
