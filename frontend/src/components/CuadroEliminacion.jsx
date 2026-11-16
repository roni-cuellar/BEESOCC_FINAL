import { useState } from 'react'
import { Link } from 'react-router-dom'
import { nombreEquipoFase, nombrePlaza, partidosOficiales } from '../utils/eliminacion'

export default function CuadroEliminacion({ fase, grupos = [], ligaId, arbitral = false, marcadores, bloqueado, onAvanzar, onPenales }) {
  const [rondaVisible, setRondaVisible] = useState('')
  return <section aria-label="Cuadro eliminatorio" className="eliminacion-cuadro">
    <div className="fila-entre"><div><h2>Camino al título</h2><p className="nota">Clasificados y procedencias confirmados por Laravel. Abre un acta para consultar eventos o correcciones.</p></div>
      <label>Ver ronda<select value={rondaVisible} onChange={e => setRondaVisible(e.target.value)}><option value="">Todas las rondas</option>{fase.rondas.map(r => <option key={r.id} value={r.id}>{r.nombre}</option>)}</select></label>
    </div>
    <p className="nota">Desliza horizontalmente para recorrer el cuadro. «Oficial» indica un resultado certificado; finalizar por sí solo no lo certifica.</p>
    <div className="eliminacion-desplazamiento" tabIndex={0} role="region" aria-label="Rondas del cuadro eliminatorio">
      <div className="eliminacion-rondas">{fase.rondas.filter(r => !rondaVisible || String(r.id) === rondaVisible).map(ronda => <section className="eliminacion-ronda" key={ronda.id}>
        <header><p className="etiqueta">RONDA {ronda.numero}</p><h3>{ronda.nombre}</h3><p>{ronda.modalidad === 'unico' ? 'Partido único' : 'Ida y vuelta'} · {ronda.desempate === 'penales' ? 'Penales' : 'Prórroga y penales'}</p><span>{ronda.estado.replaceAll('_', ' ')}</span></header>
        <ol className="eliminacion-llaves">{ronda.llaves.map(llave => <li className="eliminacion-llave" key={llave.id}>
          <article aria-label={ronda.nombre + ', llave ' + llave.numero}><div className="fila-entre"><h4>Llave {llave.numero}</h4><span className={'estado estado-' + (llave.estado === 'resuelta' ? 'finalizado' : 'programado')}>{llave.estado === 'resuelta' ? 'Resuelta' : 'Pendiente'}</span></div>
            <ul className="eliminacion-plazas">{llave.plazas.map(plaza => <li key={plaza.id} className={plaza.fase_equipo_id && plaza.fase_equipo_id === llave.ganador_fase_equipo_id ? 'eliminacion-clasificado' : ''}><span>{nombrePlaza(fase, plaza)}{plaza.origen_tipo === 'grupo_puesto' && <small className="block">{grupos.find(g => g.id === plaza.origen_grupo_id)?.codigo || 'Grupo #' + plaza.origen_grupo_id} · puesto {plaza.origen_puesto}</small>}</span>{plaza.fase_equipo_id && plaza.fase_equipo_id === llave.ganador_fase_equipo_id && <small>Clasificado</small>}</li>)}</ul>
            {llave.metodo_resolucion === 'bye' && <p className="nota">Pase por descanso · sin partido</p>}
            {llave.ganador_fase_equipo_id && <p className="eliminacion-ganador">Clasificado: {nombreEquipoFase(fase, llave.ganador_fase_equipo_id)}</p>}
            <ul className="eliminacion-partidos">{llave.partidos.map(partido => {
              const resultado = marcadores[partido.id]
              return <li key={partido.id}><Link to={(arbitral ? '/mis-arbitrajes/' : '/ligas/') + ligaId + '/partidos/' + partido.id}>{ronda.modalidad === 'ida_vuelta' ? (partido.numero_serie === 1 ? 'Ida' : 'Vuelta') : 'Acta'} · {partido.estado.replaceAll('_', ' ')}</Link>
                <p>{resultado?.datos ? `${resultado.datos.local.nombre} ${resultado.datos.local.goles} – ${resultado.datos.visitante.goles} ${resultado.datos.visitante.nombre}` : resultado?.error || 'Marcador no disponible'}</p>
                <small>{partido.oficializado_at ? 'Resultado oficial · ' + new Date(partido.oficializado_at).toLocaleString('es-CO') : 'Resultado no oficial'}</small>
              </li>
            })}</ul>
            {llave.penales_plaza_1 != null && <p>Tanda certificada (plazas 1–2): <strong>{llave.penales_plaza_1} – {llave.penales_plaza_2}</strong></p>}
            {llave.estado === 'pendiente' && partidosOficiales(llave, ronda) && <div className="acciones acciones-compactas">
              {!arbitral && <button className="boton boton-verde" disabled={bloqueado} onClick={() => onAvanzar(llave)}>Solicitar avance</button>}
              {arbitral && llave.penales_plaza_1 == null && llave.penales_plaza_2 == null && <button className="boton boton-borde" disabled={bloqueado} onClick={() => onPenales(llave, ronda)}>Certificar penales</button>}
            </div>}
          </article>
        </li>)}</ol>
      </section>)}</div>
    </div>
  </section>
}
