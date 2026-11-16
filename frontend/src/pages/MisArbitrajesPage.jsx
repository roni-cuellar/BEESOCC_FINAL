import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import arbitroService from '../services/arbitroService'
import { mensajeError } from '../services/api'
import { Boton, Tarjeta } from '../components/ui'
import EncabezadoPagina from '../components/layout/EncabezadoPagina'
import EstadoVista from '../components/EstadoVista'

export function ListaArbitrajes({ asignaciones }) {
  return (
    <div className="grid gap-5 md:grid-cols-2">
      {asignaciones.map((a) => (
        <Tarjeta key={a.id}>
          <h2 className="font-titulo text-xl font-bold break-words">{a.campeonato.nombre}</h2>
          <p className="mt-3 text-sm text-gris">
            Temporada {a.campeonato.temporada} · {a.campeonato.estado.replaceAll('_', ' ')}
          </p>
          <p className="mt-3 text-sm">
            Autorización vigente desde {new Date(a.fecha_inicio).toLocaleDateString('es-CO')}.
          </p>
          <Link
            className="enlace-texto mt-4 inline-flex min-h-11 items-center"
            to={'/mis-arbitrajes/' + a.campeonato.id + '/partidos'}
          >
            Controlar partidos
          </Link>
          {a.campeonato.formato === 'grupos_eliminacion' && (
            <Link
              className="enlace-texto mt-4 ml-4 inline-flex min-h-11 items-center"
              to={'/mis-arbitrajes/' + a.campeonato.id + '/grupos'}
            >
              Grupos, desempates y cuadro
            </Link>
          )}
          {a.campeonato.formato === 'eliminacion_directa' && (
            <Link
              className="enlace-texto mt-4 ml-4 inline-flex min-h-11 items-center"
              to={'/mis-arbitrajes/' + a.campeonato.id + '/cuadro'}
            >
              Cuadro y penales
            </Link>
          )}
        </Tarjeta>
      ))}
    </div>
  )
}
export default function MisArbitrajesPage() {
  const [asignaciones, setAsignaciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [intento, setIntento] = useState(0)
  useEffect(() => {
    let vigente = true

    arbitroService
      .misArbitrajes()
      .then((datos) => {
        if (vigente) setAsignaciones(datos)
      })
      .catch((e) => {
        if (vigente) setError(mensajeError(e))
      })
      .finally(() => {
        if (vigente) setCargando(false)
      })
    const refrescar = () => {
      setCargando(true)
      setError('')
      setIntento((v) => v + 1)
    }
    window.addEventListener('focus', refrescar)
    const intervalo = window.setInterval(refrescar, 30000)
    return () => {
      vigente = false
      window.removeEventListener('focus', refrescar)
      window.clearInterval(intervalo)
    }
  }, [intento])
  function actualizar() {
    setCargando(true)
    setError('')
    setIntento((v) => v + 1)
  }
  return (
    <section className="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:py-12">
      <EncabezadoPagina
        titulo="Mis arbitrajes"
        descripcion="Campeonatos donde tienes una autorización arbitral vigente."
        acciones={
          <Boton variante="secundario" disabled={cargando} onClick={actualizar}>
            Actualizar
          </Boton>
        }
      />
      <EstadoVista
        cargando={cargando}
        error={error}
        vacio={!asignaciones.length}
        titulo="Sin arbitrajes vigentes"
        texto="Cuando un propietario te asigne a su campeonato, aparecerá aquí."
        reintentar={actualizar}
      />
      {!cargando && !error && <ListaArbitrajes asignaciones={asignaciones} />}
    </section>
  )
}
