import IlustracionModulo from '../components/IlustracionModulo'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { crearGruposStore } from '../stores/useGruposStore'
import grupoService from '../services/grupoService'
import faseService from '../services/faseService'
import { nombreInscripcion } from '../utils/grupos'
import { Boton, Campo } from '../components/ui'
import EstadoVista from '../components/EstadoVista'
import Aviso from '../components/Aviso'
import Confirmacion from '../components/Confirmacion'
import HistorialAuditoria from '../components/HistorialAuditoria'
import CuadroEliminacion from '../components/CuadroEliminacion'
import PenalesForm from '../components/PenalesForm'
import ConfiguracionGrupos, { PreviaDistribucion } from '../components/grupos/ConfiguracionGrupos'
import TablasGrupos from '../components/grupos/TablasGrupos'
import DesempatesGrupos, { PartidosGrupo } from '../components/grupos/DesempatesGrupos'
import CrucesGrupos from '../components/grupos/CrucesGrupos'
import '../styles/eliminacion.css'
import '../styles/grupos.css'

const secciones = [
  ['grupos', 'Grupos y posiciones'],
  ['calendario', 'Calendario'],
  ['desempates', 'Desempates'],
  ['eliminacion', 'Eliminación'],
  ['auditoria', 'Auditoría'],
]

export default function GruposPage({ arbitral = false }) {
  const { ligaId } = useParams()
  return <ContenidoGrupos key={`${ligaId}:${arbitral}`} ligaId={ligaId} arbitral={arbitral} />
}

function ContenidoGrupos({ ligaId, arbitral }) {
  const [useGrupos] = useState(crearGruposStore)
  const s = useGrupos()
  const [seccion, setSeccion] = useState('grupos')
  const [nombre, setNombre] = useState('Fase de grupos')
  const [confirmacion, setConfirmacion] = useState(null)
  const [tanda, setTanda] = useState(null)
  const contextoActual = s.contexto === `${ligaId}:${arbitral}`
  const bloqueado = s.cargando || s.operando
  const operar = (accion, mensaje) => s.operar(accion, ligaId, arbitral, mensaje)
  const recargar = (vista = s.vista) => {
    setConfirmacion(null)
    setTanda(null)
    s.cargar(ligaId, arbitral, vista)
  }
  useEffect(() => {
    useGrupos.getState().cargar(ligaId, arbitral)
    const actualizar = () => {
      const a = useGrupos.getState()
      if (!a.operando) {
        setConfirmacion(null)
        setTanda(null)
        a.cargar(ligaId, arbitral)
      }
    }
    window.addEventListener('focus', actualizar)
    return () => {
      window.removeEventListener('focus', actualizar)
      useGrupos.getState().limpiar()
    }
  }, [ligaId, arbitral, useGrupos])
  const pedir = (titulo, texto, accion) => setConfirmacion({ titulo, texto, accion })
  const confirmarPrevia = () =>
    pedir(
      'Confirmar propuesta revisada',
      'Se conservará exactamente la propuesta mostrada. Se revalidarán la clasificación y la configuración antes de crear los registros.',
      () => s.confirmarPrevia(ligaId, arbitral),
    )
  const avanzar = (llave) =>
    pedir(
      'Solicitar avance oficial',
      'Se verificará el acta oficial y, cuando corresponda, la tanda certificada. La resolución se obtiene del resultado oficial.',
      () => operar(() => faseService.avanzar(llave.id), 'Resolución oficial actualizada.'),
    )
  async function confirmarOperacion() {
    try {
      await confirmacion.accion()
    } catch (e) {
      setConfirmacion(null)
      setTanda(null)
      throw e
    }
  }
  const vigente = s.clasificacion?.clasificacion_vigente
  const generador = !arbitral && s.liga?.estado === 'planificacion'
  return (
    <section
      className={`grupos-pagina eliminacion-pagina${arbitral ? ' mx-auto max-w-7xl px-5 py-8 sm:px-8' : ''}`}
    >
      {arbitral && (
        <Link className="enlace-texto" to={`/mis-arbitrajes/${ligaId}/partidos`}>
          Volver a partidos arbitrales
        </Link>
      )}
      <header className="grupos-cabecera">
        <div className="flex min-w-0 items-center gap-4">
          <IlustracionModulo tipo="grupos" />
          <div className="min-w-0">
            <p className="etiqueta">BEESOCC · GRUPOS + ELIMINACIÓN</p>
            <h1>Cada grupo, un camino al título.</h1>
            <p>
              {arbitral
                ? 'Consulta posiciones y actas; certifica las tandas de los partidos que arbitras.'
                : 'Organiza los grupos, resuelve las posiciones y acompaña a los clasificados.'}
            </p>
          </div>
        </div>
        <Boton variante="claro" disabled={bloqueado} onClick={() => recargar()}>
          Actualizar competición
        </Boton>
      </header>
      <EstadoVista
        cargando={s.cargando || !contextoActual}
        error={contextoActual ? s.error : ''}
        reintentar={() => recargar()}
      />
      {contextoActual && !s.cargando && !s.error && (
        <>
          <Aviso error={s.errorOperacion} exito={s.mensaje} />
          <ol className="grupos-etapas" aria-label="Etapas del campeonato">
            <li>
              <span>01</span>
              <strong>Grupos</strong>
              <small>{s.fase?.grupos.length ? 'Distribución confirmada' : 'Por configurar'}</small>
            </li>
            <li>
              <span>02</span>
              <strong>Clasificación</strong>
              <small>{vigente ? 'Confirmada oficialmente' : 'Pendiente de confirmación'}</small>
            </li>
            <li>
              <span>03</span>
              <strong>Eliminación</strong>
              <small>{s.eliminacion ? 'Cuadro generado' : 'Por generar'}</small>
            </li>
          </ol>
          {!s.fase ? (
            <section className="panel">
              <h2>Empieza por la fase de grupos</h2>
              {generador ? (
                <form
                  className="grupos-crear"
                  onSubmit={(e) => {
                    e.preventDefault()
                    operar(
                      () => grupoService.crearFase(ligaId, nombre.trim()),
                      'Fase de grupos creada.',
                    ).catch(() => {})
                  }}
                >
                  <Campo
                    etiqueta="Nombre de la fase"
                    required
                    maxLength={80}
                    value={nombre}
                    disabled={bloqueado}
                    onChange={(e) => setNombre(e.target.value)}
                  />
                  <Boton type="submit" disabled={bloqueado || !nombre.trim()}>
                    Crear fase de grupos
                  </Boton>
                </form>
              ) : (
                <p className="nota">El organizador todavía no ha creado una fase de grupos.</p>
              )}
            </section>
          ) : (
            <>
              <div className="fila-entre">
                <h2>{s.fase.nombre}</h2>
                <span className="estado">{s.fase.estado.replaceAll('_', ' ')}</span>
              </div>
              <nav className="grupos-navegacion" aria-label="Gestión de grupos">
                {secciones.map(([id, titulo]) => (
                  <button
                    key={id}
                    type="button"
                    aria-current={seccion === id ? 'page' : undefined}
                    disabled={bloqueado}
                    onClick={() => {
                      setSeccion(id)
                      s.invalidarPrevia()
                      setConfirmacion(null)
                      setTanda(null)
                    }}
                  >
                    {titulo}
                  </button>
                ))}
              </nav>
              {seccion === 'grupos' && (
                <>
                  {!s.fase.grupos.length &&
                    (generador ? (
                      <ConfiguracionGrupos
                        inscripciones={s.inscripciones}
                        bloqueado={bloqueado}
                        onCambiar={s.invalidarPrevia}
                        onPrevisualizar={(datos) => s.previsualizar('distribucion', datos)}
                      />
                    ) : (
                      <p className="panel">La distribución aún no está confirmada.</p>
                    ))}
                  {s.previa?.tipo === 'distribucion' && (
                    <PreviaDistribucion
                      vista={s.previa.vista}
                      inscripciones={s.inscripciones}
                      bloqueado={bloqueado}
                      onConfirmar={confirmarPrevia}
                    />
                  )}
                  {s.fase.grupos.length > 0 && (
                    <TablasGrupos
                      fase={s.fase}
                      tablas={s.tablas}
                      clasificacion={s.clasificacion}
                      vista={s.vista}
                      bloqueado={bloqueado}
                      onVista={recargar}
                      onClasificar={
                        !arbitral
                          ? () =>
                              pedir(
                                'Confirmar clasificación oficial',
                                'Confirma los candidatos y sus puestos mostrados. Se verificará que todos los resultados y desempates estén resueltos.',
                                () =>
                                  operar(
                                    () =>
                                      grupoService.clasificar(
                                        s.fase.id,
                                        s.clasificacion.confirmacion,
                                      ),
                                    'Clasificación oficial confirmada.',
                                  ),
                              )
                          : null
                      }
                    />
                  )}
                </>
              )}
              {seccion === 'calendario' && (
                <>
                  <section className="panel">
                    <h2>Calendario regular de grupos</h2>
                    <p className="nota">
                      Una vuelta por grupo. La programación, eventos, actas y correcciones se
                      gestionan desde cada partido.
                    </p>
                    {generador &&
                      s.fase.grupos.length > 0 &&
                      !s.clasificacion.tablas.some((t) => t.calendario_generado) && (
                        <Boton
                          disabled={bloqueado}
                          onClick={() =>
                            pedir(
                              'Generar calendario de grupos',
                              'Se crearán los encuentros regulares de una vuelta de la distribución confirmada.',
                              () =>
                                operar(
                                  () => grupoService.calendario(s.fase.id),
                                  'Calendario de grupos generado.',
                                ),
                            )
                          }
                        >
                          Generar calendario de grupos
                        </Boton>
                      )}
                  </section>
                  {s.fase.grupos.map((g) => (
                    <section className="panel" key={g.id}>
                      <h3>{g.codigo}</h3>
                      {g.partidos?.some((p) => p.tipo_partido === 'regular') ? (
                        <PartidosGrupo
                          partidos={g.partidos.filter((p) => p.tipo_partido === 'regular')}
                          ligaId={ligaId}
                          arbitral={arbitral}
                          marcadores={s.marcadores}
                          fase={s.fase}
                        />
                      ) : (
                        <p className="nota">Sin partidos regulares registrados.</p>
                      )}
                    </section>
                  ))}
                </>
              )}
              {seccion === 'desempates' && (
                <DesempatesGrupos
                  fase={s.fase}
                  clasificacion={s.clasificacion}
                  expedientes={s.expedientes}
                  previa={s.previa?.tipo === 'desempate' ? s.previa.vista : null}
                  ligaId={ligaId}
                  arbitral={arbitral}
                  marcadores={s.marcadores}
                  bloqueado={bloqueado}
                  onPrevisualizar={(id, clave) => s.previsualizar('desempate', { clave }, id)}
                  onConfirmar={confirmarPrevia}
                  onAvanzar={avanzar}
                  onPenales={(caso) =>
                    setTanda({
                      llave: caso.llave,
                      ronda: { nombre: 'Partido extra', desempate: 'penales' },
                      nombresPlazas: caso.inscripciones.map((id) =>
                        nombreInscripcion(s.fase, [], id),
                      ),
                      fase: s.fase,
                    })
                  }
                />
              )}
              {seccion === 'eliminacion' && (
                <>
                  {s.eliminacion ? (
                    <>
                      <CuadroEliminacion
                        key={s.eliminacion.id}
                        fase={s.eliminacion}
                        grupos={s.fase.grupos}
                        ligaId={ligaId}
                        arbitral={arbitral}
                        marcadores={s.marcadores}
                        bloqueado={bloqueado}
                        onAvanzar={avanzar}
                        onPenales={(llave, ronda) =>
                          setTanda({ llave, ronda, fase: s.eliminacion })
                        }
                      />
                      <HistorialAuditoria historial={s.historialCuadro} contexto="cuadro" />
                    </>
                  ) : vigente && !arbitral ? (
                    <CrucesGrupos
                      key={s.clasificacion.huella}
                      fase={s.fase}
                      clasificacion={s.clasificacion}
                      inscripciones={s.inscripciones}
                      previa={s.previa?.tipo === 'eliminacion' ? s.previa.vista : null}
                      bloqueado={bloqueado}
                      onCambiar={s.invalidarPrevia}
                      onPrevisualizar={(datos) => s.previsualizar('eliminacion', datos)}
                      onConfirmar={confirmarPrevia}
                    />
                  ) : (
                    <section className="panel">
                      <h2>El siguiente paso se gana en la cancha</h2>
                      <p className="nota">
                        {vigente
                          ? 'El organizador todavía no ha generado el cuadro eliminatorio.'
                          : 'Confirma la clasificación oficial de grupos antes de preparar la eliminación.'}
                      </p>
                    </section>
                  )}
                </>
              )}
              {seccion === 'auditoria' && (
                <HistorialAuditoria historial={s.historial} contexto="fase de grupos" />
              )}
              {tanda && arbitral && (
                <PenalesForm
                  key={tanda.llave.id}
                  {...tanda}
                  bloqueado={bloqueado}
                  onConfirmar={(datos) =>
                    pedir(
                      'Certificar tanda de penales',
                      `Plaza 1: ${datos.penales_plaza_1} · Plaza 2: ${datos.penales_plaza_2}. ${datos.motivo}`,
                      async () => {
                        await operar(
                          () => faseService.penales(tanda.llave.id, datos),
                          'Tanda certificada por el árbitro.',
                        )
                        setTanda(null)
                      },
                    )
                  }
                />
              )}
            </>
          )}
        </>
      )}
      {confirmacion && contextoActual && !s.cargando && !s.error && (
        <Confirmacion
          titulo={confirmacion.titulo}
          texto={confirmacion.texto}
          onConfirmar={confirmarOperacion}
          onCerrar={() => setConfirmacion(null)}
        />
      )}
    </section>
  )
}
