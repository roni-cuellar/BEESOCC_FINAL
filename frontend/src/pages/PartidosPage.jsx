import IlustracionModulo from '../components/IlustracionModulo'
import { useEffect, useState } from 'react'
import { Link, useOutletContext, useParams } from 'react-router-dom'
import usePartidoStore from '../stores/usePartidoStore'
import useEquipoStore from '../stores/useEquipoStore'
import partidoService from '../services/partidoService'
import PartidoCard from '../components/PartidoCard'
import PartidoForm from '../components/PartidoForm'
import Modal from '../components/Modal'
import EstadoVista from '../components/EstadoVista'
import Aviso from '../components/Aviso'
import { estadosPartido } from '../utils/partidos'
function PartidosPage() {
  const { ligaId } = useParams()
  const eliminacion = useOutletContext()?.liga?.formato === 'eliminacion_directa'
  const grupos = useOutletContext()?.liga?.formato === 'grupos_eliminacion'
  const { partidos, cargando, error, cargar } = usePartidoStore()
  const { inscripciones, error: errorEquipos, cargar: cargarEquipos } = useEquipoStore()
  const [jornada, setJornada] = useState('')
  const [estado, setEstado] = useState('')
  const [crear, setCrear] = useState(false)
  const [mensaje, setMensaje] = useState('')
  useEffect(() => {
    cargar(ligaId)
    cargarEquipos(ligaId)
  }, [ligaId, cargar, cargarEquipos])
  const visibles = partidos.filter(
    (p) => (!jornada || String(p.jornada) === jornada) && (!estado || p.estado === estado),
  )
  return (
    <>
      <div className="titulo-pagina">
        <div className="flex min-w-0 items-center gap-4">
          <IlustracionModulo tipo="partidos" />
          <div className="min-w-0">
            <h1>Partidos</h1>
            <p className="texto-suave">Programa encuentros y consulta resultados.</p>
          </div>
        </div>
        {grupos ? (
          <Link className="boton boton-verde" to={'/ligas/' + ligaId + '/grupos'}>
            Administrar grupos
          </Link>
        ) : eliminacion ? (
          <Link className="boton boton-verde" to={'/ligas/' + ligaId + '/cuadro'}>
            Administrar cuadro
          </Link>
        ) : (
          <button
            className="boton boton-verde"
            disabled={
              Boolean(errorEquipos) || inscripciones.filter((i) => i.estado === 'activo').length < 2
            }
            onClick={() => setCrear(true)}
          >
            + Crear partido
          </button>
        )}
      </div>
      <Aviso error={errorEquipos} exito={mensaje} />
      <div className="filtros">
        <label>
          Jornada
          <select value={jornada} onChange={(e) => setJornada(e.target.value)}>
            <option value="">Todas las jornadas</option>
            {[...new Set(partidos.map((p) => p.jornada))]
              .sort((a, b) => a - b)
              .map((j) => (
                <option key={j} value={j}>
                  Jornada {j}
                </option>
              ))}
          </select>
        </label>
        <label>
          Estado
          <select value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="">Todos los estados</option>
            {estadosPartido.map((e) => (
              <option key={e} value={e}>
                {e.replaceAll('_', ' ')}
              </option>
            ))}
          </select>
        </label>
      </div>
      <EstadoVista
        cargando={cargando}
        error={error}
        vacio={!visibles.length}
        titulo="No hay partidos registrados"
        texto="Crea un partido o cambia los filtros."
        reintentar={() => {
          cargar(ligaId)
          cargarEquipos(ligaId)
        }}
      />
      {!cargando && !error && (
        <div className="rejilla-tarjetas">
          {visibles.map((p) => (
            <PartidoCard key={p.id} partido={p} ligaId={ligaId} />
          ))}
        </div>
      )}
      {crear && (
        <Modal titulo="Crear partido" onCerrar={() => setCrear(false)}>
          <PartidoForm
            inscripciones={inscripciones}
            onCancelar={() => setCrear(false)}
            onGuardar={async (datos) => {
              await partidoService.crear(ligaId, datos)
              setCrear(false)
              setMensaje('Partido creado correctamente.')
              await cargar(ligaId)
            }}
          />
        </Modal>
      )}
    </>
  )
}
export default PartidosPage
