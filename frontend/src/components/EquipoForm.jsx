import { useRef, useState } from 'react'
import Aviso from './Aviso'
import ImagenCatalogoForm from './ImagenCatalogoForm'
import { mensajeError } from '../services/api'

function EquipoForm({ equipo, onGuardar, onCancelar }) {
  const [datos, setDatos] = useState({
    nombre: equipo?.nombre || '',
    ciudad: equipo?.ciudad || '',
    escudo: null,
  })

  const enviando = useRef(false)
  const [eliminar, setEliminar] = useState(false)
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState('')

  // Cambiar nombre y ciudad
  function cambiar(e) {
    setDatos({
      ...datos,
      [e.target.name]: e.target.value,
    })
  }

  // Guardar equipo
  async function guardar(e) {
    e.preventDefault()
    if (enviando.current) return
    enviando.current = true
    setError('')
    setGuardando(true)

    try {
      await onGuardar({
        nombre: datos.nombre.trim(),
        ciudad: datos.ciudad.trim(),
        escudo: datos.escudo,
        eliminar_imagen: eliminar,
      })
    } catch (e) {
      setError(mensajeError(e))
      setGuardando(false)
      enviando.current = false
    }
  }

  return (
    <form onSubmit={guardar}>
      <Aviso error={error} />
      <fieldset className="contents" disabled={guardando}>
        <label>
          Nombre del equipo
          <input required name="nombre" maxLength={150} value={datos.nombre} onChange={cambiar} />
        </label>

        <label>
          Ciudad
          <input required name="ciudad" maxLength={100} value={datos.ciudad} onChange={cambiar} />
        </label>

        <ImagenCatalogoForm
          campo="escudo"
          etiqueta="Escudo"
          actual={equipo?.escudo}
          nombre={equipo?.nombre || datos.nombre}
          archivo={datos.escudo}
          eliminar={eliminar}
          onArchivo={(escudo) => {
            setDatos({ ...datos, escudo })
            if (escudo) setEliminar(false)
          }}
          onEliminar={setEliminar}
        />

        <div className="acciones">
          <button
            type="button"
            className="boton boton-borde"
            disabled={guardando}
            onClick={onCancelar}
          >
            Cancelar
          </button>

          <button className="boton boton-verde" disabled={guardando}>
            {guardando ? 'Guardando…' : 'Guardar equipo'}
          </button>
        </div>
      </fieldset>
    </form>
  )
}

export default EquipoForm
