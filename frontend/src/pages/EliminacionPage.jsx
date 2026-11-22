import IlustracionModulo from '../components/IlustracionModulo'
import LogoCampeonato from '../components/LogoCampeonato'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { crearEliminacionStore } from '../stores/useEliminacionStore'
import faseService from '../services/faseService'
import EstadoVista from '../components/EstadoVista'
import Aviso from '../components/Aviso'
import Confirmacion from '../components/Confirmacion'
import HistorialAuditoria from '../components/HistorialAuditoria'
import ConfiguracionEliminacion, { PreviaEliminacion } from '../components/ConfiguracionEliminacion'
import CuadroEliminacion from '../components/CuadroEliminacion'
import PenalesForm from '../components/PenalesForm'
import { Boton, Campo } from '../components/ui'
import '../styles/eliminacion.css'

export default function EliminacionPage({ arbitral = false }) {
  const { ligaId } = useParams()
  const [useCuadro] = useState(crearEliminacionStore)
  const s = useCuadro()
  const [nombre, setNombre] = useState('')
  const [confirmacion, setConfirmacion] = useState(null)
  const [tanda, setTanda] = useState(null)
  const contextoActual = s.contexto === ligaId + ':' + arbitral
  const bloqueado = s.cargando || s.operando
  function recargar(faseId = s.fase?.id) {
    setConfirmacion(null)
    setTanda(null)
    s.cargar(ligaId, arbitral, faseId)
  }
  useEffect(() => {
    useCuadro.getState().cargar(ligaId, arbitral)
    const actualizar = () => {
      const actual = useCuadro.getState()
      if (!actual.operando) {
        setConfirmacion(null)
        setTanda(null)
        actual.cargar(ligaId, arbitral, actual.fase?.id)
      }
    }
    window.addEventListener('focus', actualizar)
    return () => {
      window.removeEventListener('focus', actualizar)
      useCuadro.getState().limpiar()
    }
  }, [ligaId, arbitral, useCuadro])
  const operar = (accion, texto) => s.operar(accion, ligaId, arbitral, texto, s.fase?.id)
  async function confirmarOperacion() {
    try {
      await confirmacion.accion()
    } catch (e) {
      if ([401, 403, 404, 409].includes(e.response?.status)) {
        setConfirmacion(null)
        setTanda(null)
      }
      throw e
    }
  }
  return (
    <section
      className={
        arbitral ? 'eliminacion-pagina mx-auto max-w-7xl px-5 py-8 sm:px-8' : 'eliminacion-pagina'
      }
    >
      {arbitral && (
        <Link className="enlace-texto" to={'/mis-arbitrajes/' + ligaId + '/partidos'}>
          Volver a partidos arbitrales
        </Link>
      )}
      <div className="titulo-pagina">
        <div className="flex min-w-0 items-center gap-4">
          <LogoCampeonato
            ruta={s.liga?.logo ?? s.fase?.campeonato?.logo}
            respaldo={<IlustracionModulo tipo="eliminacion" />}
          />
          <div className="min-w-0">
            <p className="etiqueta">ELIMINACIÓN DIRECTA · CUADRO PRIVADO</p>
            <h1>Fases y llaves</h1>
            <p className="texto-suave">
              {arbitral
                ? 'Consulta el cuadro y certifica las tandas de penales.'
                : 'Organiza la siembra y acompaña el camino al título.'}
            </p>
          </div>
        </div>
        <Boton variante="secundario" disabled={bloqueado} onClick={() => recargar()}>
          Actualizar cuadro
        </Boton>
      </div>
      <EstadoVista
        cargando={s.cargando || !contextoActual}
        error={contextoActual ? s.error : ''}
        reintentar={() => recargar()}
      />
      {contextoActual && !s.cargando && !s.error && (
        <>
          <Aviso error={s.errorOperacion} exito={s.mensaje} />
          {s.liga && s.liga.formato !== 'eliminacion_directa' ? (
            <p className="panel">Este campeonato no es de eliminación directa.</p>
          ) : (
            <>
              {s.fases.length > 0 && (
                <div className="panel">
                  <Campo
                    as="select"
                    etiqueta="Fase eliminatoria"
                    value={s.fase?.id || ''}
                    disabled={bloqueado}
                    onChange={(e) => recargar(e.target.value)}
                  >
                    {s.fases.map((f) => (
                      <option key={f.id} value={f.id}>
                        {f.nombre} · {f.estado.replaceAll('_', ' ')}
                      </option>
                    ))}
                  </Campo>
                </div>
              )}
              {!s.fases.length && (
                <section className="panel">
                  <h2>Sin fase eliminatoria</h2>
                  {!arbitral && s.liga?.estado === 'planificacion' ? (
                    <form
                      onSubmit={(e) => {
                        e.preventDefault()
                        operar(
                          async () => ({
                            fase_id: (await faseService.crear(ligaId, nombre.trim())).id,
                          }),
                          'Fase creada.',
                        ).catch(() => {})
                      }}
                    >
                      <fieldset className="contents" disabled={bloqueado}>
                        <Campo
                          etiqueta="Nombre de la fase"
                          required
                          maxLength={80}
                          value={nombre}
                          onChange={(e) => setNombre(e.target.value)}
                        />
                        <Boton type="submit" disabled={bloqueado || !nombre.trim()}>
                          Crear fase eliminatoria
                        </Boton>
                      </fieldset>
                    </form>
                  ) : (
                    <p className="nota">El organizador todavía no ha configurado una fase.</p>
                  )}
                </section>
              )}
              {s.fase && (
                <>
                  <div className="fila-entre">
                    <h2>{s.fase.nombre}</h2>
                    <span className="estado">{s.fase.estado.replaceAll('_', ' ')}</span>
                  </div>
                  {!s.fase.rondas.length && !arbitral && s.liga?.estado === 'planificacion' && (
                    <ConfiguracionEliminacion
                      key={s.fase.id}
                      inscripciones={s.inscripciones}
                      bloqueado={bloqueado}
                      onCambiar={s.invalidarPrevia}
                      onPrevisualizar={s.previsualizar}
                    />
                  )}
                  {s.previa && (
                    <PreviaEliminacion
                      previa={s.previa}
                      inscripciones={s.inscripciones}
                      bloqueado={bloqueado}
                      onConfirmar={() =>
                        setConfirmacion({
                          titulo: 'Generar cuadro eliminatorio',
                          texto:
                            'Se conservará la siembra mostrada y se crearán las rondas y los partidos correspondientes. El cuadro confirmado no se puede editar directamente. ¿Continuar?',
                          accion: () => s.generar(ligaId, arbitral),
                        })
                      }
                    />
                  )}
                  {s.fase.rondas.length > 0 && (
                    <CuadroEliminacion
                      fase={s.fase}
                      ligaId={ligaId}
                      arbitral={arbitral}
                      marcadores={s.marcadores}
                      bloqueado={bloqueado}
                      onAvanzar={(llave) =>
                        setConfirmacion({
                          titulo: 'Solicitar avance de llave ' + llave.numero,
                          texto:
                            'Laravel verificará los resultados oficiales, resolverá el clasificado y actualizará las plazas siguientes. No se elige un ganador manualmente. ¿Continuar?',
                          accion: () =>
                            operar(
                              () => faseService.avanzar(llave.id),
                              'Clasificado actualizado por Laravel.',
                            ),
                        })
                      }
                      onPenales={(llave, ronda) => setTanda({ llave, ronda })}
                    />
                  )}
                  {tanda && arbitral && (
                    <PenalesForm
                      key={tanda.llave.id}
                      fase={s.fase}
                      {...tanda}
                      bloqueado={bloqueado}
                      onConfirmar={(datos) =>
                        setConfirmacion({
                          titulo: 'Certificar tanda de penales',
                          texto:
                            'Plaza 1: ' +
                            datos.penales_plaza_1 +
                            ' · Plaza 2: ' +
                            datos.penales_plaza_2 +
                            '. Motivo: ' +
                            datos.motivo,
                          accion: async () => {
                            await operar(
                              () => faseService.penales(tanda.llave.id, datos),
                              'Tanda certificada. El administrador puede solicitar el avance.',
                            )
                            setTanda(null)
                          },
                        })
                      }
                    />
                  )}
                  <HistorialAuditoria historial={s.historial} contexto="cuadro" />
                </>
              )}
            </>
          )}
        </>
      )}
      {confirmacion && contextoActual && !s.error && (
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
