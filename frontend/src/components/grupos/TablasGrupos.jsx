import { Boton } from '../ui'
import { nombreInscripcion } from '../../utils/grupos'
import TablaPosicionesDeportiva from '../deportivo/TablaPosicionesDeportiva'

export default function TablasGrupos({
  fase,
  tablas,
  clasificacion,
  vista,
  bloqueado,
  onVista,
  onClasificar,
  publica = false,
  destinoEquipo,
}) {
  const vigente = clasificacion?.clasificacion_vigente
  const equipos = Object.fromEntries(
    (fase.equipos || []).map((fila) => [fila.campeonato_equipo_id, fila.inscripcion?.equipo]),
  )
  const clasificados = new Set(
    vigente ? clasificacion.candidatos.map((fila) => fila.campeonato_equipo_id) : [],
  )
  return (
    <>
      <div className="fila-entre grupos-vista-posiciones">
        <div>
          <h2>La competencia, grupo a grupo</h2>
          <p className="nota">
            Los partidos adicionales se resuelven aparte y no suman a esta tabla.
          </p>
        </div>
        <label>
          Vista de posiciones
          <select value={vista} disabled={bloqueado} onChange={(e) => onVista(e.target.value)}>
            <option value="oficial">Resultados oficiales</option>
            <option value="provisional">Posiciones provisionales</option>
          </select>
        </label>
      </div>
      <div className="grupos-rejilla grupos-tablas">
        {tablas.map((t) => {
          const grupo = fase.grupos.find((g) => g.id === t.grupo_id)
          return (
            <article className="panel" key={t.grupo_id}>
              <header className="fila-entre">
                <div>
                  <p className="etiqueta">{grupo?.codigo}</p>
                  <h3>{grupo?.nombre || `Grupo ${grupo?.codigo || ''}`}</h3>
                </div>
                <span className="estado">
                  {vista === 'provisional'
                    ? 'Provisional'
                    : t.definitiva
                      ? 'Posiciones definitivas'
                      : 'Oficial · pendiente'}
                </span>
              </header>
              <p className="nota">
                {t.partidos_oficiales} de {t.partidos_esperados} partidos regulares oficiales
              </p>
              <TablaPosicionesDeportiva
                filas={t.tabla}
                equipos={equipos}
                clasificados={clasificados}
                titulo={'Posiciones ' + (grupo?.codigo || '') + ' · ' + vista}
                destinoEquipo={destinoEquipo}
              />
              {t.pendientes_resolucion.length > 0 && (
                <p className="grupos-alerta">
                  Hay posiciones empatadas pendientes de resolución. Consulta Desempates.
                </p>
              )}
            </article>
          )
        })}
      </div>
      <section className="panel grupos-clasificacion">
        <div>
          <p className="etiqueta">RUMBO A LA ELIMINACIÓN</p>
          <h2>
            {vigente
              ? 'Clasificación oficial confirmada'
              : publica
                ? 'Clasificación pendiente'
                : 'Confirmar la clasificación'}
          </h2>
          <p className="nota">
            {vigente
              ? 'Estos son los clasificados oficiales de la fase.'
              : publica
                ? 'La organización debe resolver los resultados y desempates y confirmar los clasificados.'
                : clasificacion?.lista_para_clasificar
                  ? 'Los resultados y desempates están completos. Revisa los candidatos antes de confirmar.'
                  : 'Completa los resultados oficiales y todos los desempates para habilitar la clasificación.'}
          </p>
        </div>
        {clasificacion?.candidatos.length > 0 && (
          <ul className="grupos-lista-clasificados">
            {clasificacion.candidatos.map((c) => (
              <li key={`${c.origen_grupo_id}:${c.origen_puesto}`}>
                <strong>{nombreInscripcion(fase, [], c.campeonato_equipo_id)}</strong>
                <span>
                  {fase.grupos.find((g) => g.id === c.origen_grupo_id)?.codigo} · puesto{' '}
                  {c.origen_puesto} · {vigente ? 'Oficial' : 'Candidato sin confirmar'}
                </span>
              </li>
            ))}
          </ul>
        )}
        {onClasificar && !vigente && (
          <Boton
            disabled={bloqueado || !clasificacion?.lista_para_clasificar}
            onClick={onClasificar}
          >
            Confirmar clasificación oficial
          </Boton>
        )}
      </section>
    </>
  )
}
