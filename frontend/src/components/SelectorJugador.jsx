import { useEffect, useId, useRef, useState } from 'react'
import ImagenEntidad from './ImagenEntidad'
import { buscarJugadores, nombreCompleto } from '../utils/inscripciones'

export default function SelectorJugador({ jugadores, valor, onSeleccion, deshabilitado }) {
  const id = useId()
  const listaId = id + '-resultados'
  const entrada = useRef(null)
  const [consulta, setConsulta] = useState('')
  const [abierto, setAbierto] = useState(false)
  const [activo, setActivo] = useState(-1)
  const seleccionado = jugadores.find((jugador) => String(jugador.id) === valor)
  const resultados = buscarJugadores(jugadores, consulta)
  const mostrar = abierto && !deshabilitado
  const opcionActiva =
    mostrar && resultados[activo] ? listaId + '-' + resultados[activo].id : undefined

  useEffect(() => {
    if (opcionActiva) document.getElementById(opcionActiva)?.scrollIntoView({ block: 'nearest' })
  }, [opcionActiva])

  function elegir(jugador) {
    onSeleccion(String(jugador.id))
    setConsulta('')
    setActivo(-1)
    setAbierto(false)
  }

  function verLista() {
    setConsulta('')
    setActivo(-1)
    entrada.current?.focus()
    setAbierto(true)
  }

  function teclado(e) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault()
      setAbierto(true)
      if (!resultados.length) return
      setActivo((actual) => {
        if (e.key === 'ArrowDown') return Math.min(actual + 1, resultados.length - 1)
        return actual < 0 ? resultados.length - 1 : Math.max(0, actual - 1)
      })
    } else if (e.key === 'Enter') {
      e.preventDefault()
      if (mostrar && resultados.length) elegir(resultados[activo < 0 ? 0 : activo])
    } else if (e.key === 'Escape' && mostrar) {
      e.preventDefault()
      e.stopPropagation()
      setAbierto(false)
      setActivo(-1)
    }
  }

  return (
    <div
      className="mb-4 grid min-w-0 gap-2"
      onBlur={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget)) {
          setAbierto(false)
          setActivo(-1)
        }
      }}
    >
      <label htmlFor={id} className="font-semibold text-oscuro">
        Jugador existente
      </label>
      <input
        ref={entrada}
        id={id}
        role="combobox"
        type="text"
        autoComplete="off"
        aria-autocomplete="list"
        aria-required="true"
        aria-expanded={mostrar}
        aria-controls={listaId}
        aria-activedescendant={opcionActiva}
        aria-describedby={id + '-ayuda'}
        value={!mostrar && seleccionado ? nombreCompleto(seleccionado) : consulta}
        placeholder="Escribe un nombre o apellido"
        disabled={deshabilitado || !jugadores.length}
        onFocus={() => setAbierto(true)}
        onKeyDown={teclado}
        onChange={(e) => {
          setConsulta(e.target.value)
          setActivo(-1)
          setAbierto(true)
          if (valor) onSeleccion('')
        }}
      />
      <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
        {jugadores.length > 0 && (
          <button
            className="boton-texto min-h-11 text-marca-oscuro"
            type="button"
            onClick={verLista}
            disabled={deshabilitado}
          >
            Ver lista completa
          </button>
        )}
        {seleccionado && (
          <button
            className="boton-texto min-h-11 text-marca-oscuro"
            type="button"
            disabled={deshabilitado}
            onClick={() => {
              onSeleccion('')
              verLista()
            }}
          >
            Limpiar selección
          </button>
        )}
      </div>
      <p id={id + '-ayuda'} className="m-0 text-sm text-gris">
        {seleccionado
          ? `Seleccionado: ${nombreCompleto(seleccionado)} · #${seleccionado.id}`
          : 'Usa las flechas y Enter para elegir una coincidencia.'}
      </p>
      {!jugadores.length && (
        <p role="status" className="m-0 text-sm text-gris">
          No hay jugadores disponibles para inscribir.
        </p>
      )}
      {mostrar && !resultados.length && jugadores.length > 0 && (
        <p
          role="status"
          className="m-0 rounded-lg border border-borde bg-white p-3 text-sm text-gris"
        >
          No hay coincidencias.
        </p>
      )}
      <ul
        id={listaId}
        role="listbox"
        aria-label="Jugadores disponibles"
        hidden={!mostrar || !resultados.length}
        className="m-0 max-h-[min(14rem,34dvh)] min-w-0 list-none overflow-auto overscroll-contain rounded-xl border border-borde bg-white p-1"
      >
        {mostrar &&
          resultados.map((jugador, i) => {
            const nombre = nombreCompleto(jugador)
            return (
              <li
                id={listaId + '-' + jugador.id}
                key={jugador.id}
                role="option"
                aria-selected={activo === i}
                className={`flex min-h-12 cursor-pointer items-center gap-3 rounded-lg p-2 text-sm hover:bg-green-50 ${activo === i ? 'bg-green-100 text-marca-oscuro ring-1 ring-inset ring-marca' : 'text-oscuro'}`}
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => elegir(jugador)}
              >
                <ImagenEntidad ruta={jugador.foto} nombre={nombre} decorativa className="size-8" />
                <span className="min-w-0 wrap-anywhere font-semibold">
                  {nombre} <small className="font-normal text-gris">· #{jugador.id}</small>
                </span>
              </li>
            )
          })}
      </ul>
    </div>
  )
}
