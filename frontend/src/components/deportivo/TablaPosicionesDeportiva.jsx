import TablaDeportiva, { IdentidadDeportiva, PuestoDeportivo } from './TablaDeportiva'

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

export default function TablaPosicionesDeportiva({
  filas,
  titulo,
  descripcionId,
  columnas = Object.keys(nombres),
  equipos = {},
  destinoEquipo,
  clasificados,
}) {
  return (
    <TablaDeportiva titulo={titulo} descripcionId={descripcionId}>
      <thead>
        <tr>
          <th scope="col">Pos.</th>
          <th scope="col">Equipo</th>
          {columnas.map((columna) => (
            <th key={columna} scope="col">
              <abbr title={nombres[columna]} aria-label={nombres[columna]}>
                {columna}
              </abbr>
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {filas.map((fila) => {
          const id = fila.campeonato_equipo_id ?? fila.equipo_id
          const equipo = equipos[id]
          return (
            <tr key={id}>
              <td>
                <PuestoDeportivo valor={fila.posicion} />
              </td>
              <th scope="row">
                <IdentidadDeportiva
                  nombre={fila.nombre}
                  imagen={equipo?.escudo}
                  to={
                    destinoEquipo && (equipo?.id ?? fila.equipo_id)
                      ? destinoEquipo(equipo?.id ?? fila.equipo_id)
                      : undefined
                  }
                  primaria
                />
                {clasificados?.has(id) && (
                  <span className="deportivo-clasificado">Clasificado oficial</span>
                )}
              </th>
              {columnas.map((columna) => (
                <td key={columna}>
                  {columna === 'PTS' ? (
                    <strong className="deportivo-total">{fila[columna]}</strong>
                  ) : (
                    fila[columna]
                  )}
                </td>
              ))}
            </tr>
          )
        })}
      </tbody>
    </TablaDeportiva>
  )
}
