
import { useState } from 'react'
import Aviso from './Aviso'
import { mensajeError } from '../services/api'

function EquipoForm({ equipo, onGuardar, onCancelar }) {
  const [datos, setDatos] = useState({
    nombre: equipo?.nombre || '',
    ciudad: equipo?.ciudad || '',
    escudo: null,
  })

  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState('')

  // Cambiar nombre y ciudad
  function cambiar(e) {
    setDatos({
      ...datos,
      [e.target.name]: e.target.value,
    })
  }

  // Seleccionar una imagen
  function cambiarEscudo(e) {
    const archivo = e.target.files[0] || null

    setDatos({
      ...datos,
      escudo: archivo,
    })
  }

  // Guardar equipo
  async function guardar(e) {
    e.preventDefault()
    setError('')
    setGuardando(true)

    try {
      await onGuardar({
        nombre: datos.nombre.trim(),
        ciudad: datos.ciudad.trim(),
        escudo: datos.escudo,
      })
    } catch (e) {
      setError(mensajeError(e))
      setGuardando(false)
    }
  }

  return (
    <form onSubmit={guardar}>
      <Aviso error={error} />

      <label>
        Nombre del equipo
        <input
          required
          name="nombre"
          maxLength={150}
          value={datos.nombre}
          onChange={cambiar}
        />
      </label>

      <label>
        Ciudad
        <input
          required
          name="ciudad"
          maxLength={100}
          value={datos.ciudad}
          onChange={cambiar}
        />
      </label>

      <label>
        Escudo del equipo (opcional)
        <input
          type="file"
          name="escudo"
          accept="image/jpeg,image/png,image/webp"
          onChange={cambiarEscudo}
        />
      </label>

      {/* Mostrar imagen actual cuando se edita */}
      {equipo?.escudo && (
        <div>
          <p>Escudo actual:</p>
          <img
            src={equipo.escudo}
            alt={`Escudo de ${equipo.nombre}`}
            width="100"
            style={{ objectFit: 'contain' }}
          />
        </div>
      )}

      <div className="acciones">
        <button
          type="button"
          className="boton boton-borde"
          disabled={guardando}
          onClick={onCancelar}
        >
          Cancelar
        </button>

        <button
          className="boton boton-verde"
          disabled={guardando}
        >
          {guardando ? 'Guardando…' : 'Guardar equipo'}
        </button>
      </div>
    </form>
  )
}

export default EquipoForm
