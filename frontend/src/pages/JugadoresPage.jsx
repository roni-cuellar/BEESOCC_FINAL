import TablaDeportiva from '../components/deportivo/TablaDeportiva'
import IlustracionModulo from '../components/IlustracionModulo'
import { useCallback, useState } from 'react'
import { useOutletContext, useParams } from 'react-router-dom'
import jugadorService from '../services/jugadorService'
import equipoService from '../services/equipoService'
import JugadorForm from '../components/JugadorForm'
import PlantillaForm from '../components/PlantillaForm'
import ImagenEntidad from '../components/ImagenEntidad'
import Modal from '../components/Modal'
import Confirmacion from '../components/Confirmacion'
import EstadoVista from '../components/EstadoVista'
import Aviso from '../components/Aviso'
import useConsultaPublica from '../hooks/useConsultaPublica'
import { jugadoresDisponibles } from '../utils/inscripciones'

function InscripcionJugador({ equipoId, inscripciones, jugadores, onGuardar, onCancelar }) {
  const consultar = useCallback(async () => {
    const listas = await Promise.all(inscripciones.map(({ id }) => jugadorService.plantillas(id)))
    return {
      disponibles: jugadoresDisponibles(jugadores, listas.flat()),
      plantillaEquipo: listas[inscripciones.findIndex(({ id }) => String(id) === equipoId)] || [],
    }
  }, [equipoId, inscripciones, jugadores])
  const consulta = useConsultaPublica(consultar)
  return consulta.datos ? (
    <PlantillaForm
      modo="inscribir"
      jugadores={consulta.datos.disponibles}
      plantillas={consulta.datos.plantillaEquipo}
      onGuardar={onGuardar}
      onCancelar={onCancelar}
    />
  ) : (
    <EstadoVista {...consulta} />
  )
}

function JugadoresPage() {
  const { ligaId } = useParams()
  const liga = useOutletContext()?.liga
  const [seleccionado, setEquipoId] = useState('')
  const [tab, setTab] = useState('plantilla')
  const [busqueda, setBusqueda] = useState('')
  const [revision, setRevision] = useState(0)
  const [formulario, setFormulario] = useState(null)
  const [inscripcionForm, setInscripcionForm] = useState(null)
  const [confirmacion, setConfirmacion] = useState(null)
  const [mensaje, setMensaje] = useState('')

  const consultarEquipos = useCallback(() => equipoService.inscripciones(ligaId), [ligaId])
  const consultaEquipos = useConsultaPublica(consultarEquipos)
  const consultaCatalogo = useConsultaPublica(jugadorService.listar, revision)
  const inscripciones = consultaEquipos.datos ?? []
  const jugadores = consultaCatalogo.datos ?? []
  const equipoId = inscripciones.some((i) => String(i.id) === seleccionado)
    ? seleccionado
    : String(inscripciones[0]?.id || '')
  const consultarPlantilla = useCallback(
    () => (equipoId ? jugadorService.plantillas(equipoId) : Promise.resolve([])),
    [equipoId],
  )
  const consultaPlantilla = useConsultaPublica(consultarPlantilla, revision)
  const { cargando: cargandoPlantilla, error: errorPlantilla } = consultaPlantilla
  const plantillas = consultaPlantilla.datos ?? []
  const consultaVista = tab === 'catalogo' ? consultaCatalogo : consultaEquipos
  const equipo = inscripciones.find((i) => String(i.id) === String(equipoId))
  const catalogo = jugadores.filter((j) =>
    (j.nombre + ' ' + j.apellido).toLowerCase().includes(busqueda.toLowerCase()),
  )
  async function guardarJugador(datos) {
    if (formulario.id) await jugadorService.actualizar(formulario.id, datos)
    else await jugadorService.crear(datos)
    setFormulario(null)
    setRevision((v) => v + 1)
    setMensaje('Jugador guardado correctamente.')
  }
  async function guardarPlantilla(datos) {
    if (inscripcionForm.modo === 'inscribir') await jugadorService.inscribir(equipoId, datos)
    else await jugadorService.actualizarPlantilla(inscripcionForm.plantilla.id, datos)
    setInscripcionForm(null)
    setRevision((v) => v + 1)
    setMensaje('Inscripción guardada correctamente.')
  }
  return (
    <>
      <div className="titulo-pagina">
        <div className="flex min-w-0 items-center gap-4">
          <IlustracionModulo tipo={tab === 'plantilla' ? 'plantillas' : 'equipos'} />
          <div className="min-w-0">
            <h1>Jugadores</h1>
            <p className="texto-suave">Gestiona jugadores y plantillas.</p>
          </div>
        </div>
        <button className="boton boton-verde" onClick={() => setFormulario({})}>
          + Registrar jugador
        </button>
      </div>
      <Aviso exito={mensaje} />
      <div className="pestanas">
        <button className={tab === 'plantilla' ? 'activa' : ''} onClick={() => setTab('plantilla')}>
          Plantillas
        </button>
        <button className={tab === 'catalogo' ? 'activa' : ''} onClick={() => setTab('catalogo')}>
          Todos los jugadores
        </button>
      </div>
      <EstadoVista {...consultaVista} />
      {!consultaVista.cargando &&
        !consultaVista.error &&
        (tab === 'catalogo' ? (
          <>
            <div className="filtros">
              <label>
                Buscar jugador
                <input
                  value={busqueda}
                  onChange={(e) => setBusqueda(e.target.value)}
                  placeholder="Nombre o apellido"
                />
              </label>
            </div>
            <EstadoVista
              vacio={!catalogo.length}
              titulo="No hay jugadores para mostrar"
              texto="Registra un jugador o cambia la búsqueda."
            />
            {catalogo.length > 0 && (
              <TablaDeportiva catalogo titulo="Catálogo de jugadores">
                <thead>
                  <tr>
                    <th>Jugador</th>
                    <th>Nacimiento</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {catalogo.map((j) => (
                    <tr key={j.id}>
                      <td>
                        <div className="celda-entidad">
                          <ImagenEntidad ruta={j.foto} nombre={j.nombre + ' ' + j.apellido} />
                          {j.nombre} {j.apellido}
                        </div>
                      </td>
                      <td>{j.fecha_nacimiento.slice(0, 10)}</td>
                      <td>
                        <div className="acciones acciones-compactas">
                          <button className="boton boton-borde" onClick={() => setFormulario(j)}>
                            Editar
                          </button>
                          <button
                            className="boton-texto peligro"
                            onClick={() =>
                              setConfirmacion({
                                titulo: 'Eliminar jugador',
                                texto:
                                  '¿Eliminar al jugador? Solo es posible si no tiene inscripciones.',
                                accion: async () => {
                                  await jugadorService.eliminar(j.id)
                                  setRevision((v) => v + 1)
                                },
                              })
                            }
                          >
                            Eliminar
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </TablaDeportiva>
            )}
          </>
        ) : (
          <>
            <div className="filtros">
              <label>
                Equipo inscrito
                <select value={equipoId} onChange={(e) => setEquipoId(e.target.value)}>
                  <option value="">Selecciona un equipo</option>
                  {inscripciones.map((i) => (
                    <option key={i.id} value={i.id}>
                      {i.equipo.nombre} · {i.estado}
                    </option>
                  ))}
                </select>
              </label>
              <button
                className="boton boton-verde"
                disabled={
                  !equipo ||
                  equipo.estado !== 'activo' ||
                  liga?.estado !== 'planificacion' ||
                  cargandoPlantilla ||
                  Boolean(errorPlantilla)
                }
                onClick={() => setInscripcionForm({ modo: 'inscribir' })}
              >
                Inscribir jugador
              </button>
            </div>
            {equipo?.estado === 'activo' && liga && liga.estado !== 'planificacion' && (
              <p className="nota">Las nuevas inscripciones se realizan durante la planificación.</p>
            )}
            {equipo?.estado === 'retirado' && (
              <div className="aviso aviso-info">
                Equipo retirado. Su plantilla se conserva para consulta.
              </div>
            )}
            <EstadoVista
              cargando={cargandoPlantilla}
              error={errorPlantilla}
              vacio={!equipo || !plantillas.length}
              titulo={!equipo ? 'Selecciona un equipo inscrito' : 'Plantilla vacía'}
              texto={
                !inscripciones.length
                  ? 'Inscribe un equipo para comenzar.'
                  : 'Selecciona un jugador para inscribirlo.'
              }
              reintentar={consultaPlantilla.reintentar}
            />
            {!cargandoPlantilla && !errorPlantilla && plantillas.length > 0 && (
              <TablaDeportiva catalogo titulo="Plantillas del campeonato">
                <thead>
                  <tr>
                    <th>Jugador</th>
                    <th>Dorsal</th>
                    <th>Posición</th>
                    <th>Ingreso</th>
                    <th>Salida</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {plantillas.map((p) => (
                    <tr key={p.id}>
                      <td>
                        <div className="celda-entidad">
                          <ImagenEntidad ruta={p.jugador.foto} nombre={p.jugador.nombre} />
                          {p.jugador.nombre} {p.jugador.apellido}
                        </div>
                      </td>
                      <td>{p.dorsal}</td>
                      <td>{p.posicion}</td>
                      <td>{p.fecha_ingreso.slice(0, 10)}</td>
                      <td>{p.fecha_salida?.slice(0, 10) || '—'}</td>
                      <td>
                        <span
                          className={'estado estado-' + (p.fecha_salida ? 'retirado' : 'activo')}
                        >
                          {p.fecha_salida ? 'Histórica' : 'Activa'}
                        </span>
                      </td>
                      <td>
                        {!p.fecha_salida ? (
                          <div className="acciones acciones-compactas">
                            <button
                              className="boton boton-borde"
                              onClick={() => setInscripcionForm({ modo: 'editar', plantilla: p })}
                            >
                              Editar
                            </button>
                            <button
                              className="boton boton-borde"
                              onClick={() => setInscripcionForm({ modo: 'salida', plantilla: p })}
                            >
                              Registrar salida
                            </button>
                            <button
                              className="boton-texto peligro"
                              onClick={() =>
                                setConfirmacion({
                                  titulo: 'Eliminar inscripción',
                                  texto:
                                    '¿Eliminar esta inscripción? Para retirar al jugador, registra su salida.',
                                  accion: async () => {
                                    await jugadorService.eliminarPlantilla(p.id)
                                    setRevision((v) => v + 1)
                                  },
                                })
                              }
                            >
                              Eliminar
                            </button>
                          </div>
                        ) : (
                          <span className="nota">Cerrada</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </TablaDeportiva>
            )}
          </>
        ))}
      {formulario && (
        <Modal
          titulo={formulario.id ? 'Editar jugador' : 'Registrar jugador'}
          onCerrar={() => setFormulario(null)}
        >
          <JugadorForm
            jugador={formulario}
            onGuardar={guardarJugador}
            onCancelar={() => setFormulario(null)}
          />
        </Modal>
      )}
      {inscripcionForm && (
        <Modal
          titulo={
            inscripcionForm.modo === 'salida'
              ? 'Registrar salida del jugador'
              : inscripcionForm.modo === 'editar'
                ? 'Editar inscripción del jugador'
                : 'Inscribir jugador en la plantilla'
          }
          onCerrar={() => setInscripcionForm(null)}
        >
          {inscripcionForm.modo === 'inscribir' && !consultaCatalogo.datos ? (
            <EstadoVista {...consultaCatalogo} />
          ) : inscripcionForm.modo === 'inscribir' ? (
            <InscripcionJugador
              equipoId={equipoId}
              inscripciones={inscripciones}
              jugadores={jugadores}
              onGuardar={guardarPlantilla}
              onCancelar={() => setInscripcionForm(null)}
            />
          ) : (
            <PlantillaForm
              {...inscripcionForm}
              plantillas={plantillas}
              onGuardar={guardarPlantilla}
              onCancelar={() => setInscripcionForm(null)}
            />
          )}
        </Modal>
      )}
      {confirmacion && (
        <Confirmacion
          titulo={confirmacion.titulo}
          texto={confirmacion.texto}
          onConfirmar={confirmacion.accion}
          onCerrar={() => setConfirmacion(null)}
        />
      )}
    </>
  )
}
export default JugadoresPage
