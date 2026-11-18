import IlustracionModulo from '../IlustracionModulo'
import { Link } from 'react-router-dom'
import { Boton } from '../ui'
import { nombreInscripcion } from '../../utils/grupos'
import { nombreEquipoFase, partidosOficiales } from '../../utils/eliminacion'

export function PartidosGrupo({ partidos, ligaId, arbitral, marcadores, fase }) {
  return (
    <ul className="grupos-partidos">
      {partidos.map((p) => {
        const m = marcadores[p.id]
        return (
          <li key={p.id}>
            <div>
              <strong>
                {p.participantes
                  .map((x) => nombreInscripcion(fase, [], x.campeonato_equipo_id))
                  .join(' · ')}
              </strong>
              <p>
                {m?.datos
                  ? `${m.datos.local.goles} – ${m.datos.visitante.goles}`
                  : m?.error || 'Marcador no disponible'}{' '}
                <span className="texto-suave">· {p.estado.replaceAll('_', ' ')}</span>
              </p>
              <small>
                {p.oficializado_at ? 'Resultado oficial' : 'Sin oficializar'}
                {p.fecha_hora ? ` · ${new Date(p.fecha_hora).toLocaleString('es-CO')}` : ''}
              </small>
            </div>
            <Boton
              variante="secundario"
              to={`${arbitral ? '/mis-arbitrajes/' : '/ligas/'}${ligaId}/partidos/${p.id}`}
            >
              Abrir acta
            </Boton>
          </li>
        )
      })}
    </ul>
  )
}

export default function DesempatesGrupos({
  fase,
  clasificacion,
  expedientes,
  previa,
  ligaId,
  arbitral,
  marcadores,
  bloqueado,
  onPrevisualizar,
  onConfirmar,
  onAvanzar,
  onPenales,
}) {
  const nombre = (id) => nombreInscripcion(fase, [], id)
  return (
    <>
      <div className="fila-entre">
        <div className="flex min-w-0 items-center gap-4">
          <IlustracionModulo tipo="desempates" />
          <div className="min-w-0">
            <h2>Desempates con respaldo oficial</h2>
            <p className="nota">
              Partidos extra y liguillas adicionales, separados de la tabla regular. Cada nueva
              generación requiere confirmación del organizador.
            </p>
          </div>
        </div>
      </div>
      {clasificacion.tablas.map((t) => (
        <section className="panel" key={t.grupo_id}>
          <p className="etiqueta">{fase.grupos.find((g) => g.id === t.grupo_id)?.codigo}</p>
          {t.pendientes_resolucion.length === 0 && (
            <p className="nota">Sin empates pendientes de resolución.</p>
          )}
          {t.pendientes_resolucion.map((p) => (
            <div className="grupos-pendiente" key={p.clave}>
              <h3>{p.inscripciones.length === 2 ? 'Partido extra' : 'Liguilla adicional'}</h3>
              <p>{p.inscripciones.map(nombre).join(' · ')}</p>
              <p className="nota">
                Posiciones {p.rango_posiciones.join(' a ')} ·{' '}
                {p.generable
                  ? 'Pendiente de generación confirmada'
                  : 'Pendiente de resultados oficiales o avance'}
              </p>
              {!arbitral && p.generable && (
                <Boton disabled={bloqueado} onClick={() => onPrevisualizar(t.grupo_id, p.clave)}>
                  Previsualizar desempate
                </Boton>
              )}
            </div>
          ))}
          {(expedientes[t.grupo_id] || []).map((c) => {
            const vigente = t.desempates_usados.includes(c.id)
            const oficial = c.llave && partidosOficiales(c, { modalidad: 'unico' })
            return (
              <article className="grupos-expediente" key={c.id}>
                <h3>
                  Expediente #{c.id} · {c.llave_id ? 'Partido extra' : 'Liguilla adicional'}
                </h3>
                <p className="nota">
                  {c.inscripciones.map(nombre).join(' · ')} ·{' '}
                  {vigente ? 'Expediente vigente' : 'Expediente anterior; solo consulta'}
                </p>
                {c.llave?.ganador_fase_equipo_id && (
                  <p className="grupos-resolucion">
                    Resolución oficial: {nombreEquipoFase(fase, c.llave.ganador_fase_equipo_id)}
                  </p>
                )}
                {c.llave?.penales_plaza_1 != null && (
                  <p>
                    Tanda certificada: {c.llave.penales_plaza_1} – {c.llave.penales_plaza_2} (orden
                    de equipos del expediente).
                  </p>
                )}
                <PartidosGrupo
                  partidos={c.partidos}
                  ligaId={ligaId}
                  arbitral={arbitral}
                  marcadores={marcadores}
                  fase={fase}
                />
                {vigente && oficial && c.llave.estado === 'pendiente' && (
                  <div className="acciones">
                    {arbitral ? (
                      c.llave.penales_plaza_1 == null && (
                        <Boton disabled={bloqueado} onClick={() => onPenales(c)}>
                          Certificar penales del desempate
                        </Boton>
                      )
                    ) : (
                      <Boton disabled={bloqueado} onClick={() => onAvanzar(c.llave)}>
                        Solicitar resolución del partido extra
                      </Boton>
                    )}
                  </div>
                )}
                {!c.llave_id && (
                  <p className="nota">
                    Consulta los puestos resultantes en la tabla oficial. Si persiste la igualdad,
                    Laravel indicará el siguiente desempate pendiente.
                  </p>
                )}
              </article>
            )
          })}
        </section>
      ))}
      {previa && (
        <section className="panel grupos-previa" aria-label="Previsualización de desempate">
          <p className="etiqueta">ANTES DE GENERAR</p>
          <h2>
            {previa.tipo === 'partido_extra'
              ? 'Partido extra entre dos equipos'
              : 'Liguilla adicional de una vuelta'}
          </h2>
          <p>{previa.inscripciones.map(nombre).join(' · ')}</p>
          <p className="nota">
            Se resolverá a partir del puesto {previa.posicion_inicio}. Los resultados requieren acta
            oficial; las cifras de la tabla regular se conservan.
          </p>
          <Boton disabled={bloqueado} onClick={onConfirmar}>
            Confirmar generación del desempate
          </Boton>
        </section>
      )}
      <Link
        className="enlace-texto"
        to={arbitral ? `/mis-arbitrajes/${ligaId}/partidos` : `/ligas/${ligaId}/partidos`}
      >
        Consultar todas las actas y correcciones
      </Link>
    </>
  )
}
