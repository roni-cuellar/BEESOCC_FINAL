import { useEffect, useState } from 'react'

export default function ConexionesCuadro({ lienzo, conexiones }) {
  const [trazos, setTrazos] = useState([])

  useEffect(() => {
    const contenedor = lienzo.current
    if (!contenedor || !conexiones.length) return
    const llaves = new Map(
      [...contenedor.querySelectorAll('[data-llave]')].map((el) => [el.dataset.llave, el]),
    )
    const plazas = new Map(
      [...contenedor.querySelectorAll('[data-plaza]')].map((el) => [el.dataset.plaza, el]),
    )

    function medir() {
      const base = contenedor.getBoundingClientRect()
      setTrazos(
        conexiones.flatMap((conexion) => {
          const origen = llaves.get(String(conexion.origen))?.getBoundingClientRect()
          const destino = llaves.get(String(conexion.destino))?.getBoundingClientRect()
          const plaza = plazas.get(String(conexion.plaza))?.getBoundingClientRect()
          if (!origen || !destino || !plaza || destino.left <= origen.right) return []
          const x1 = origen.right - base.left
          const x2 = destino.left - base.left
          const medio = (x1 + x2) / 2
          const y1 = origen.top + origen.height / 2 - base.top
          const y2 = plaza.top + plaza.height / 2 - base.top
          return [{ ...conexion, d: `M ${x1} ${y1} H ${medio} V ${y2} H ${x2}` }]
        }),
      )
    }

    medir()
    if (typeof ResizeObserver === 'undefined') return
    const observador = new ResizeObserver(medir)
    observador.observe(contenedor)
    for (const elemento of [...llaves.values(), ...plazas.values()]) observador.observe(elemento)
    return () => observador.disconnect()
  }, [lienzo, conexiones])

  if (!conexiones.length) return null
  return (
    <svg aria-hidden="true" focusable="false" className="eliminacion-conexiones">
      {trazos.map((trazo) => (
        <path key={trazo.plaza} d={trazo.d} data-confirmada={trazo.confirmada ? 'si' : 'no'} />
      ))}
    </svg>
  )
}
