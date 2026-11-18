import { useEffect, useState } from 'react'
import publicoService from '../services/publicoService'
import { mensajeError } from '../services/api'
import LigaCard from '../components/LigaCard'
import EstadoVista from '../components/EstadoVista'
import EncabezadoPagina from '../components/layout/EncabezadoPagina'
import { Boton } from '../components/ui'

export default function CampeonatosPublicosPage() {
  const [ligas, setLigas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [revision, setRevision] = useState(0)
  useEffect(() => {
    const actualizar = () => setRevision((r) => r + 1)
    window.addEventListener('focus', actualizar)
    return () => window.removeEventListener('focus', actualizar)
  }, [])
  useEffect(() => {
    let vigente = true
    async function consultar() {
      setCargando(true)
      setError('')
      setLigas([])
      try {
        const datos = await publicoService.listar()
        if (vigente) setLigas(datos)
      } catch (e) {
        if (vigente) setError(mensajeError(e))
      } finally {
        if (vigente) setCargando(false)
      }
    }
    consultar()
    return () => {
      vigente = false
    }
  }, [revision])
  return (
    <section className="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10 lg:py-12">
      <EncabezadoPagina
        ilustracion="publico"
        titulo="Campeonatos publicados"
        descripcion="Sigue la competición sin crear una cuenta."
        acciones={
          <Boton
            variante="secundario"
            disabled={cargando}
            onClick={() => setRevision((r) => r + 1)}
          >
            Actualizar
          </Boton>
        }
      />
      <EstadoVista
        cargando={cargando}
        error={error}
        vacio={!ligas.length}
        titulo="Aún no hay campeonatos publicados"
        texto="Cuando un organizador publique su campeonato, podrás consultarlo aquí."
        reintentar={() => setRevision((r) => r + 1)}
      />
      {!cargando && !error && (
        <div className="grid items-stretch gap-6 md:grid-cols-2 xl:grid-cols-3">
          {ligas.map((liga) => (
            <LigaCard key={liga.id} liga={liga} publica />
          ))}
        </div>
      )}
    </section>
  )
}
