import EncabezadoPagina from '../components/layout/EncabezadoPagina'
import { useCallback, useState } from 'react'
import { useParams } from 'react-router-dom'
import ligaService from '../services/ligaService'
import publicoService from '../services/publicoService'
import useConsultaPublica from '../hooks/useConsultaPublica'
import EstadoVista from '../components/EstadoVista'
import TablaDeportiva, {
  IdentidadDeportiva,
  PuestoDeportivo,
} from '../components/deportivo/TablaDeportiva'
import { Boton } from '../components/ui'

const tipos = {
  goleadores: ['Goleadores', 'Goles'],
  asistencias: ['Asistencias', 'Asistencias'],
  amarillas: ['Amarillas', 'Tarjetas amarillas'],
  rojas: ['Rojas', 'Tarjetas rojas'],
  faltas: ['Faltas', 'Faltas'],
  equipos: ['Equipos'],
}

const columnasEquipo = [
  ['goles', 'Goles'],
  ['amarillas', 'Amarillas'],
  ['rojas', 'Rojas'],
  ['faltas', 'Faltas'],
]

export function RankingDeportivo({ tipo, resultados, identidades, base }) {
  const porEquipo = tipo === 'equipos'
  const jugadores = new Map(identidades?.jugadores?.map((fila) => [fila.jugador_id, fila]))
  const equipos = new Map(identidades?.equipos?.map((fila) => [fila.id, fila]))
  return (
    <TablaDeportiva titulo={tipos[tipo][0] + ' del campeonato'}>
      <thead>
        <tr>
          <th scope="col">Pos.</th>
          <th scope="col">{porEquipo ? 'Equipo' : 'Jugador'}</th>
          {!porEquipo && <th scope="col">Equipo</th>}
          {(porEquipo ? columnasEquipo : [['total', tipos[tipo][1]]]).map(([id, titulo]) => (
            <th key={id} scope="col">
              {titulo}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {resultados.map((fila, indice) => {
          const identidad = jugadores.get(fila.jugador_id)
          const equipo = porEquipo ? equipos.get(fila.equipo_id) : identidad?.equipo
          return (
            <tr key={porEquipo ? fila.equipo_id : fila.jugador_id}>
              <td>
                <PuestoDeportivo valor={indice + 1} />
              </td>
              <th scope="row">
                <IdentidadDeportiva
                  nombre={porEquipo ? fila.nombre : `${fila.nombre} ${fila.apellido || ''}`.trim()}
                  imagen={porEquipo ? equipo?.escudo : identidad?.foto}
                  to={
                    porEquipo
                      ? `${base}/equipos/${fila.equipo_id}`
                      : `${base}/jugadores/${fila.jugador_id}`
                  }
                  primaria
                />
              </th>
              {porEquipo ? (
                columnasEquipo.map(([campo]) => (
                  <td key={campo}>
                    {campo === 'goles' ? (
                      <strong className="deportivo-total">{fila[campo]}</strong>
                    ) : (
                      fila[campo]
                    )}
                  </td>
                ))
              ) : (
                <>
                  <td>
                    {equipo ? (
                      <IdentidadDeportiva
                        nombre={equipo.nombre}
                        imagen={equipo.escudo}
                        to={`${base}/equipos/${equipo.id}`}
                      />
                    ) : (
                      <span className="text-gris">—</span>
                    )}
                  </td>
                  <td>
                    <strong className="deportivo-total">{fila.total}</strong>
                  </td>
                </>
              )}
            </tr>
          )
        })}
      </tbody>
    </TablaDeportiva>
  )
}

export default function EstadisticasPage({ publica = false }) {
  const { ligaId } = useParams()
  const [tipo, setTipo] = useState('goleadores')
  const servicio = publica ? publicoService : ligaService
  const consultar = useCallback(() => servicio.estadisticas(ligaId, tipo), [servicio, ligaId, tipo])
  const consultarIdentidades = useCallback(() => servicio.identidades(ligaId), [servicio, ligaId])
  const consulta = useConsultaPublica(consultar)
  const identidades = useConsultaPublica(consultarIdentidades)
  const resultados = consulta.datos?.resultados || []
  return (
    <>
      <EncabezadoPagina
        tituloAs={publica ? 'h2' : 'h1'}
        ilustracion="estadisticas"
        titulo="Estadísticas"
        descripcion="Rendimiento del campeonato."
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
      <div className="mb-5 flex flex-wrap gap-2" role="group" aria-label="Tipo de estadística">
        {Object.entries(tipos).map(([clave, [titulo]]) => (
          <Boton
            key={clave}
            variante={tipo === clave ? 'principal' : 'secundario'}
            aria-pressed={tipo === clave}
            onClick={() => setTipo(clave)}
          >
            {titulo}
          </Boton>
        ))}
      </div>
      <EstadoVista
        {...consulta}
        vacio={!resultados.length}
        titulo="Aún no hay estadísticas"
        texto={
          publica
            ? 'Todavía no hay registros deportivos para esta clasificación.'
            : 'Registra eventos para consultar el rendimiento.'
        }
      />
      {resultados.length > 0 && (
        <section className="grid min-w-0 gap-3">
          <h2 className="font-titulo text-xl font-bold">{tipos[tipo][0]}</h2>
          {identidades.error && (
            <p className="nota" role="status">
              No se pudieron cargar todas las fotos y escudos.
            </p>
          )}
          <RankingDeportivo
            tipo={tipo}
            resultados={resultados}
            identidades={identidades.datos}
            base={`/${publica ? 'campeonatos' : 'ligas'}/${ligaId}`}
          />
        </section>
      )}
    </>
  )
}
