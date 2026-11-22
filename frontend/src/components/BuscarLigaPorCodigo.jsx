import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import publicoService from '../services/publicoService'
import { mensajeError } from '../services/api'
import { Boton, Campo } from './ui'

const formatoCodigo = /^[2-9A-HJ-NP-Z]{10}$/i
const formatoInvalido = 'Ingresa 10 letras o números del 2 al 9. Los códigos no usan I ni O.'

export default function BuscarLigaPorCodigo() {
  const navegar = useNavigate()
  const solicitud = useRef(null)
  const [codigo, setCodigo] = useState('')
  const [error, setError] = useState('')
  const [buscando, setBuscando] = useState(false)

  useEffect(() => () => solicitud.current?.abort(), [])

  async function buscar(evento) {
    evento.preventDefault()
    if (solicitud.current) return
    const valor = codigo.trim()
    if (!formatoCodigo.test(valor)) {
      setError(formatoInvalido)
      return
    }

    const control = new AbortController()
    solicitud.current = control
    setBuscando(true)
    setError('')
    try {
      const liga = await publicoService.porCodigo(valor.toUpperCase(), control.signal)
      if (!control.signal.aborted) navegar('/campeonatos/' + liga.id)
    } catch (fallo) {
      if (control.signal.aborted) return
      const estado = fallo.response?.status
      if (estado === 404) setError('No hay una liga publicada disponible con ese código.')
      else if (estado === 422) setError(formatoInvalido)
      else setError(mensajeError(fallo))
    } finally {
      solicitud.current = null
      if (!control.signal.aborted) setBuscando(false)
    }
  }

  return (
    <form
      onSubmit={buscar}
      aria-label="Buscar liga por código"
      aria-busy={buscando}
      className="mb-8 rounded-2xl border border-borde bg-white p-5 sm:p-6"
    >
      <div className="flex flex-wrap items-end gap-3">
        <div className="min-w-0 flex-[1_1_14rem] sm:max-w-xs">
          <Campo
            etiqueta="Código de liga"
            value={codigo}
            placeholder="Ej. F7RK28WBQ5"
            autoComplete="off"
            autoCapitalize="characters"
            spellCheck={false}
            disabled={buscando}
            className="uppercase"
            onChange={(evento) => {
              setCodigo(evento.target.value)
              setError('')
            }}
            error={error}
          />
        </div>
        <Boton type="submit" disabled={buscando} className="w-full sm:w-auto">
          {buscando ? 'Buscando…' : 'Buscar liga'}
        </Boton>
      </div>
    </form>
  )
}
