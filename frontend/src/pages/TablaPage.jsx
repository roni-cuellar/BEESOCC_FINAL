import { useCallback } from 'react'
import { useParams } from 'react-router-dom'
import ligaService from '../services/ligaService'
import equipoService from '../services/equipoService'
import EstadoVista from '../components/EstadoVista'
import publicoService from '../services/publicoService'
import EncabezadoPagina from '../components/layout/EncabezadoPagina'
import TablaPosicionesDeportiva from '../components/deportivo/TablaPosicionesDeportiva'
import useConsultaPublica from '../hooks/useConsultaPublica'
import { Boton } from '../components/ui'

export default function TablaPage({ publica = false }) {
  const { ligaId } = useParams()
  const consultar = useCallback(
    () => (publica ? publicoService : ligaService).tabla(ligaId),
    [publica, ligaId],
  )
  const consultarEquipos = useCallback(
    () => (publica ? publicoService.equipos(ligaId) : equipoService.inscripciones(ligaId)),
    [publica, ligaId],
  )
  const consulta = useConsultaPublica(consultar)
  const identidades = useConsultaPublica(consultarEquipos)
  const tabla = consulta.datos?.tabla || []
  const equipos = Object.fromEntries(
    (identidades.datos || [])
      .filter(({ equipo }) => equipo)
      .map(({ equipo }) => [equipo.id, equipo]),
  )
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
          <Boton
            variante="secundario"
            disabled={consulta.cargando}
            onClick={() => {
              consulta.reintentar()
              identidades.reintentar()
            }}
          >
            Actualizar
          </Boton>
        }
      />
      <EstadoVista
        {...consulta}
        vacio={!tabla.length}
        titulo="No hay equipos en la tabla"
        texto={
          publica
            ? 'La clasificación estará disponible cuando haya equipos inscritos.'
            : 'Inscribe equipos para comenzar la competición.'
        }
      />
      {tabla.length > 0 && (
        <section aria-label="Clasificación del campeonato">
          <p className="tabla-ayuda" id="ayuda-posiciones">
            PJ: jugados · PG: ganados · PE: empatados · PP: perdidos · GF: goles a favor · GC: goles
            en contra · DG: diferencia · PTS: puntos.
            <span>En pantallas pequeñas, desliza la tabla para ver todas las columnas.</span>
          </p>
          <TablaPosicionesDeportiva
            filas={tabla}
            equipos={equipos}
            titulo="Posiciones por equipo"
            descripcionId="ayuda-posiciones"
            destinoEquipo={(id) => `/${publica ? 'campeonatos' : 'ligas'}/${ligaId}/equipos/${id}`}
          />
        </section>
      )}
    </>
  )
}
