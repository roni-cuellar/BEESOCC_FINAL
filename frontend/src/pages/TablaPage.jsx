import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import ligaService from '../services/ligaService'
import { mensajeError } from '../services/api'
import EstadoVista from '../components/EstadoVista'
import publicoService from '../services/publicoService'
import EncabezadoPagina from '../components/layout/EncabezadoPagina'

function TablaPage({ publica = false }) {
  const { ligaId } = useParams()
  const [tabla, setTabla] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [revision, setRevision] = useState(0)
  useEffect(() => {
    let vigente = true
    async function consultar() {
      setCargando(true)
      setError('')
      setTabla([])
      try {
        const respuesta = await (publica ? publicoService : ligaService).tabla(ligaId)
        if (vigente) setTabla(respuesta.tabla)
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
  }, [ligaId, revision, publica])
  const columnas = ['PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS']
  const nombres = {
    PJ: 'Partidos jugados',
    PG: 'Ganados',
    PE: 'Empatados',
    PP: 'Perdidos',
    GF: 'Goles a favor',
    GC: 'Goles en contra',
    DG: 'Diferencia de goles',
    PTS: 'Puntos',
  }
  return (
    <>
      <EncabezadoPagina
        tituloAs={publica ? 'h2' : 'h1'}
        titulo="Tabla de posiciones"
        descripcion={
          publica
            ? 'Posiciones de partidos finalizados. Esta tabla no determina por sí sola el campeón.'
            : 'Solo partidos finalizados.'
        }
        acciones={
          <button
            className="boton boton-borde"
            onClick={() => setRevision((r) => r + 1)}
            disabled={cargando}
          >
            Actualizar
          </button>
        }
      />
      <EstadoVista
        cargando={cargando}
        error={error}
        vacio={!tabla.length}
        reintentar={() => setRevision((r) => r + 1)}
        titulo="No hay equipos en la tabla"
        texto={
          publica
            ? 'La clasificación estará disponible cuando haya equipos inscritos.'
            : 'Inscribe equipos para comenzar la competición.'
        }
      />
      {!cargando && !error && tabla.length > 0 && (
        <section className="panel" aria-label="Clasificación del campeonato">
          <p className="tabla-ayuda" id="ayuda-posiciones">
            PJ: jugados · PG: ganados · PE: empatados · PP: perdidos · GF: goles a favor · GC: goles
            en contra · DG: diferencia · PTS: puntos.
            <span>En pantallas pequeñas, desliza la tabla para ver todas las columnas.</span>
          </p>
          <div
            className="tabla-contenedor"
            role="region"
            aria-label="Posiciones por equipo"
            aria-describedby="ayuda-posiciones"
            tabIndex={0}
          >
            <table>
              <caption className="sr-only">Clasificación del campeonato</caption>
              <thead>
                <tr>
                  <th scope="col">Pos.</th>
                  <th scope="col">Equipo</th>
                  {columnas.map((c) => (
                    <th scope="col" key={c} title={nombres[c]} aria-label={nombres[c]}>
                      {c}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {tabla.map((equipo) => (
                  <tr key={equipo.equipo_id}>
                    <td>{equipo.posicion}</td>
                    <td>
                      <strong>{equipo.nombre}</strong>
                    </td>
                    {columnas.map((c) => (
                      <td className={c === 'PTS' ? 'puntos-tabla' : ''} key={c}>
                        {equipo[c]}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </>
  )
}
export default TablaPage
