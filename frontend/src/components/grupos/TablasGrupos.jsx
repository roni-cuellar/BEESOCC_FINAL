import { Boton } from '../ui'
import { nombreInscripcion } from '../../utils/grupos'

export default function TablasGrupos({ fase, tablas, clasificacion, vista, bloqueado, onVista, onClasificar }) {
  const vigente = clasificacion?.clasificacion_vigente
  return <>
    <div className="fila-entre"><div><h2>La competencia, grupo a grupo</h2><p className="nota">Los partidos adicionales se resuelven aparte y no suman a esta tabla.</p></div><label>Vista de posiciones<select value={vista} disabled={bloqueado} onChange={e => onVista(e.target.value)}><option value="oficial">Resultados oficiales</option><option value="provisional">Posiciones provisionales</option></select></label></div>
    <div className="grupos-rejilla grupos-tablas">{tablas.map(t => {
      const grupo = fase.grupos.find(g => g.id === t.grupo_id)
      return <article className="panel" key={t.grupo_id}><header className="fila-entre"><div><p className="etiqueta">{grupo?.codigo}</p><h3>{grupo?.nombre || `Grupo ${grupo?.codigo || ''}`}</h3></div><span className="estado">{vista === 'provisional' ? 'Provisional' : t.definitiva ? 'Posiciones definitivas' : 'Oficial · pendiente'}</span></header>
        <p className="nota">{t.partidos_oficiales} de {t.partidos_esperados} partidos regulares oficiales</p>
        <div className="grupos-tabla-desplazamiento" role="region" tabIndex={0} aria-label={`Posiciones ${grupo?.codigo}`}><table><caption className="sr-only">{grupo?.codigo} · {vista}</caption><thead><tr>{['Pos.', 'Equipo', 'PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS'].map(c => <th key={c} scope="col">{c}</th>)}</tr></thead><tbody>{t.tabla.map(f => {
          const clasificado = vigente && clasificacion.candidatos.some(c => c.campeonato_equipo_id === f.campeonato_equipo_id)
          return <tr key={f.campeonato_equipo_id} className={clasificado ? 'grupos-clasificado' : ''}><td>{f.posicion ?? '—'}</td><th scope="row">{f.nombre}{clasificado && <small>Clasificado oficial</small>}</th>{['PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS'].map(c => <td key={c}>{f[c]}</td>)}</tr>
        })}</tbody></table></div>
        {t.pendientes_resolucion.length > 0 && <p className="grupos-alerta">Hay posiciones empatadas pendientes de resolución. Consulta Desempates.</p>}
      </article>
    })}</div>
    <section className="panel grupos-clasificacion"><div><p className="etiqueta">RUMBO A LA ELIMINACIÓN</p><h2>{vigente ? 'Clasificación oficial confirmada' : 'Confirmar la clasificación'}</h2><p className="nota">{vigente ? 'Estos son los clasificados oficiales de la fase.' : clasificacion?.lista_para_clasificar ? 'Los resultados y desempates están completos. Revisa los candidatos antes de confirmar.' : 'Completa los resultados oficiales y todos los desempates para habilitar la clasificación.'}</p></div>
      {clasificacion?.candidatos.length > 0 && <ul className="grupos-lista-clasificados">{clasificacion.candidatos.map(c => <li key={`${c.origen_grupo_id}:${c.origen_puesto}`}><strong>{nombreInscripcion(fase, [], c.campeonato_equipo_id)}</strong><span>{fase.grupos.find(g => g.id === c.origen_grupo_id)?.codigo} · puesto {c.origen_puesto} · {vigente ? 'Oficial' : 'Candidato sin confirmar'}</span></li>)}</ul>}
      {onClasificar && !vigente && <Boton disabled={bloqueado || !clasificacion?.lista_para_clasificar} onClick={onClasificar}>Confirmar clasificación oficial</Boton>}
    </section>
  </>
}
