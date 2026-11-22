import { useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  campeonEnCuadro,
  conexionesCuadro,
  equipoFase,
  llaveFinalCuadro,
  nombreEquipoFase,
  nombrePlaza,
  nombreRondaCuadro,
  partidosOficiales,
} from '../utils/eliminacion'
import ImagenEntidad from './ImagenEntidad'
import ConexionesCuadro from './eliminacion/ConexionesCuadro'
import { Boton, Campo } from './ui'

function PartidoLlave({ partido, ronda, resultado, publica, destino }) {
  const etiqueta =
    ronda.modalidad === 'ida_vuelta'
      ? partido.numero_serie === 1
        ? 'Ida'
        : 'Vuelta'
      : publica
        ? 'Partido'
        : 'Acta'
  const texto = `${etiqueta} · ${partido.estado.replaceAll('_', ' ')}`
  return (
    <li>
      {publica ? <span>{texto}</span> : <Link to={destino + partido.id}>{texto}</Link>}
      <p className="eliminacion-marcador">
        {resultado?.datos
          ? `${resultado.datos.local.nombre} ${resultado.datos.local.goles} – ${resultado.datos.visitante.goles} ${resultado.datos.visitante.nombre}`
          : resultado?.error || 'Marcador no disponible'}
      </p>
      <small>
        {partido.oficializado_at
          ? 'Resultado oficial · ' + new Date(partido.oficializado_at).toLocaleString('es-CO')
          : 'Resultado no oficial'}
      </small>
      {publica && partido.tipo_resultado === 'administrativo' && partido.marcador_deportivo && (
        <p className="nota">
          Resultado administrativo · marcador deportivo: {partido.marcador_deportivo.local} –{' '}
          {partido.marcador_deportivo.visitante}
        </p>
      )}
    </li>
  )
}

function LlaveVisual({
  fase,
  grupos,
  ronda,
  llave,
  final,
  destino,
  arbitral,
  publica,
  marcadores,
  bloqueado,
  onAvanzar,
  onPenales,
}) {
  const resuelta = llave.estado === 'resuelta'
  const clasificado = resuelta && llave.ganador_fase_equipo_id
  const global = llave.marcador_global
  return (
    <article
      className={`eliminacion-llave-card${final ? ' eliminacion-llave-final' : ''}`}
      data-llave={llave.id}
      aria-label={ronda.nombre + ', llave ' + llave.numero}
    >
      <header className="eliminacion-llave-cabecera">
        <h4>{final ? 'Llave final' : `Llave ${llave.numero}`}</h4>
        <span className={`estado estado-${resuelta ? 'finalizado' : 'programado'}`}>
          {resuelta ? 'Resuelta' : 'Pendiente'}
        </span>
      </header>
      <ul className="eliminacion-plazas">
        {llave.plazas.map((plaza) => {
          const nombre = nombrePlaza(fase, plaza)
          const equipo = equipoFase(fase, plaza.fase_equipo_id)
          const ganador =
            clasificado &&
            plaza.fase_equipo_id != null &&
            String(plaza.fase_equipo_id) === String(clasificado)
          const origen =
            plaza.origen_tipo === 'grupo_puesto' &&
            grupos.find((g) => String(g.id) === String(plaza.origen_grupo_id))
          return (
            <li
              key={plaza.id}
              data-plaza={plaza.id}
              className={ganador ? 'eliminacion-clasificado' : ''}
            >
              {equipo && (
                <ImagenEntidad
                  ruta={equipo.escudo}
                  nombre={nombre}
                  decorativa
                  className="eliminacion-escudo"
                />
              )}
              <div className="eliminacion-plaza-nombre">
                <span>{nombre}</span>
                {plaza.origen_tipo === 'grupo_puesto' && (
                  <small>
                    {origen?.codigo || 'Grupo #' + plaza.origen_grupo_id} · puesto{' '}
                    {plaza.origen_puesto}
                  </small>
                )}
                {ganador && (
                  <small className="eliminacion-indicador-clasificado">
                    {final ? 'Ganador de la final' : 'Clasificado'}
                  </small>
                )}
              </div>
            </li>
          )
        })}
      </ul>
      {llave.metodo_resolucion === 'bye' && <p className="nota">Pase por descanso · sin partido</p>}
      {clasificado ? (
        <p className="eliminacion-ganador">
          {final ? 'Ganador de la final' : 'Clasificado'}: {nombreEquipoFase(fase, clasificado)}
        </p>
      ) : null}
      {global && (
        <p className="eliminacion-global">
          Global oficial (plazas 1–2){' '}
          <strong>
            {global.plaza_1} – {global.plaza_2}
          </strong>
        </p>
      )}
      {llave.partidos.length > 0 && (
        <ul className="eliminacion-partidos">
          {llave.partidos.map((partido) => (
            <PartidoLlave
              key={partido.id}
              {...{ partido, ronda, publica, destino }}
              resultado={marcadores[partido.id]}
            />
          ))}
        </ul>
      )}
      {llave.penales_plaza_1 != null && (
        <p className="eliminacion-penales">
          Tanda certificada (plazas 1–2):{' '}
          <strong>
            {llave.penales_plaza_1} – {llave.penales_plaza_2}
          </strong>
        </p>
      )}
      {!publica && llave.estado === 'pendiente' && partidosOficiales(llave, ronda) && (
        <div className="acciones acciones-compactas">
          {!arbitral && (
            <Boton disabled={bloqueado} onClick={() => onAvanzar?.(llave)}>
              Solicitar avance
            </Boton>
          )}
          {arbitral && llave.penales_plaza_1 == null && llave.penales_plaza_2 == null && (
            <Boton
              variante="secundario"
              disabled={bloqueado}
              onClick={() => onPenales?.(llave, ronda)}
            >
              Certificar penales
            </Boton>
          )}
        </div>
      )}
    </article>
  )
}

export default function CuadroEliminacion({
  fase,
  grupos = [],
  ligaId,
  arbitral = false,
  publica = false,
  marcadores = {},
  bloqueado,
  onAvanzar,
  onPenales,
  campeon = fase.campeon,
  mostrarFinal = true,
  idCampeon,
}) {
  const lienzo = useRef(null)
  const visor = useRef(null)
  const [activa, setActiva] = useState('')
  const rondas = fase.rondas
  const nombres = rondas.map((ronda, indice) =>
    nombreRondaCuadro(ronda, rondas.length - 1 - indice, mostrarFinal),
  )
  const indice = Math.max(
    0,
    rondas.findIndex((ronda) => String(ronda.id) === activa),
  )
  const conexiones = useMemo(() => conexionesCuadro(rondas), [rondas])
  const destino = `${arbitral ? '/mis-arbitrajes/' : '/ligas/'}${ligaId}/partidos/`
  const final = mostrarFinal ? llaveFinalCuadro(fase) : null
  const ganadorFinal = final?.estado === 'resuelta' && final.ganador_fase_equipo_id
  const confirmado = final && campeonEnCuadro(fase, campeon)

  function irA(posicion) {
    const ronda = rondas[posicion]
    const elemento = lienzo.current?.querySelector(`[data-ronda="${ronda?.id}"]`)
    if (!ronda || !elemento) return
    setActiva(String(ronda.id))
    const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    visor.current.scrollTo({
      left: elemento.getBoundingClientRect().left - lienzo.current.getBoundingClientRect().left,
      behavior: movimientoReducido ? 'instant' : 'smooth',
    })
  }

  function navegarTeclado(e) {
    if (e.target !== e.currentTarget) return
    const teclas = {
      ArrowLeft: indice - 1,
      ArrowRight: indice + 1,
      Home: 0,
      End: rondas.length - 1,
    }
    if (!(e.key in teclas)) return
    e.preventDefault()
    irA(teclas[e.key])
  }

  return (
    <section aria-label="Cuadro eliminatorio" className="eliminacion-cuadro">
      <h2 className="font-titulo text-xl font-bold">
        {mostrarFinal ? 'Camino al título' : 'Llaves de desempate'}
      </h2>
      <p className="nota">Desliza o usa el selector para recorrer las rondas.</p>
      {rondas.length > 0 && (
        <nav className="eliminacion-navegacion" aria-label="Navegación de rondas">
          <Campo
            as="select"
            etiqueta="Ver ronda"
            value={String(rondas[indice].id)}
            onChange={(e) => irA(rondas.findIndex((r) => String(r.id) === e.target.value))}
          >
            {rondas.map((ronda, i) => (
              <option key={ronda.id} value={ronda.id}>
                {nombres[i]}
              </option>
            ))}
          </Campo>
          <div className="flex flex-wrap gap-2">
            <Boton variante="secundario" disabled={indice === 0} onClick={() => irA(indice - 1)}>
              Anterior
            </Boton>
            <Boton
              variante="secundario"
              disabled={indice === rondas.length - 1}
              onClick={() => irA(indice + 1)}
            >
              Siguiente
            </Boton>
          </div>
          <span role="status" className="sr-only">
            {nombres[indice]}
          </span>
        </nav>
      )}
      {conexiones.length > 0 && (
        <p className="eliminacion-leyenda">
          <span>Avance confirmado</span>
          <span>Plaza pendiente</span>
        </p>
      )}
      <div
        className="eliminacion-desplazamiento"
        ref={visor}
        tabIndex={0}
        role="region"
        aria-label="Rondas del cuadro eliminatorio"
        onKeyDown={navegarTeclado}
      >
        <div className="eliminacion-rondas" ref={lienzo}>
          <ConexionesCuadro lienzo={lienzo} conexiones={conexiones} />
          {rondas.map((ronda, i) => (
            <section className="eliminacion-ronda" data-ronda={ronda.id} key={ronda.id}>
              <header aria-current={i === indice ? 'step' : undefined}>
                <h3>{nombres[i]}</h3>
                <p>
                  {ronda.modalidad === 'unico' ? 'Partido único' : 'Ida y vuelta'} ·{' '}
                  {ronda.desempate === 'penales' ? 'Penales' : 'Prórroga y penales'}
                </p>
                <span>{ronda.estado.replaceAll('_', ' ')}</span>
              </header>
              <ol className="eliminacion-llaves">
                {ronda.llaves.map((llave) => (
                  <li key={llave.id}>
                    <LlaveVisual
                      {...{
                        fase,
                        grupos,
                        ronda,
                        llave,
                        destino,
                        arbitral,
                        publica,
                        marcadores,
                        bloqueado,
                        onAvanzar,
                        onPenales,
                      }}
                      final={llave === final}
                    />
                    {llave === final && confirmado && (
                      <section
                        className="eliminacion-campeon"
                        aria-label="Campeón oficial"
                        id={idCampeon}
                      >
                        <ImagenEntidad ruta={campeon.escudo} nombre={campeon.nombre} decorativa />
                        <div>
                          <p>Campeón confirmado · Campeonato finalizado</p>
                          <h4>{campeon.nombre}</h4>
                        </div>
                      </section>
                    )}
                    {llave === final && ganadorFinal && !confirmado && (
                      <p className="nota mt-2">
                        La confirmación del campeón requiere el cierre oficial del campeonato.
                      </p>
                    )}
                  </li>
                ))}
              </ol>
            </section>
          ))}
        </div>
      </div>
    </section>
  )
}
