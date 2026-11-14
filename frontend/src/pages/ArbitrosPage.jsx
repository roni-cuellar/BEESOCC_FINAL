import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import arbitroService from '../services/arbitroService'
import { mensajeError } from '../services/api'
import { Boton, Campo, Tarjeta } from '../components/ui'
import EncabezadoPagina from '../components/layout/EncabezadoPagina'
import EstadoVista from '../components/EstadoVista'
import Aviso from '../components/Aviso'
import Confirmacion from '../components/Confirmacion'

export function HistorialArbitros({ asignaciones }) {
  return <Tarjeta><h2 className="mb-4 font-titulo text-xl font-bold">Historial de asignaciones</h2>
    {!asignaciones.length && <p className="text-sm text-gris">Todavía no hay asignaciones.</p>}
    <ul className="grid gap-4">{asignaciones.map(a => <li key={a.id} className="min-w-0 border-b border-borde pb-4 text-sm break-words">
      <p className="font-semibold">{a.usuario.name} · {a.fecha_fin ? 'Revocada' : 'Vigente'}</p>
      <p>Inicio: {new Date(a.fecha_inicio).toLocaleString('es-CO')}</p>
      {a.fecha_fin && <><p>Fin: {new Date(a.fecha_fin).toLocaleString('es-CO')}</p><p>Motivo: {a.motivo_revocacion}</p></>}
      <p>Autorizó: {a.autorizador.name}</p>
    </li>)}</ul>
  </Tarjeta>
}

export default function ArbitrosPage() {
  const { ligaId } = useParams()
  return <GestionArbitros key={ligaId} ligaId={ligaId} />
}

function GestionArbitros({ ligaId }) {
  const [datos, setDatos] = useState({ vigente: null, historial: [] })
  const [cargando, setCargando] = useState(true)
  const [errorCarga, setErrorCarga] = useState('')
  const [error, setError] = useState('')
  const [mensaje, setMensaje] = useState('')
  const [intento, setIntento] = useState(0)
  const [email, setEmail] = useState('')
  const [usuario, setUsuario] = useState(null)
  const [buscando, setBuscando] = useState(false)
  const [motivo, setMotivo] = useState('')
  const [accion, setAccion] = useState(null)
  const [guardando, setGuardando] = useState(false)
  useEffect(() => {
    let vigente = true

    Promise.all([arbitroService.vigente(ligaId), arbitroService.historial(ligaId)])
      .then(([actual, historial]) => { if (vigente) setDatos({ vigente: actual, historial }) })
      .catch(e => { if (vigente) setErrorCarga(mensajeError(e)) })
      .finally(() => { if (vigente) setCargando(false) })
    return () => { vigente = false }
  }, [ligaId, intento])
  function actualizar() {
    setCargando(true); setErrorCarga(''); setUsuario(null); setEmail(''); setMotivo(''); setAccion(null)
    setIntento(v => v + 1)
  }
  async function buscar(e) {
    e.preventDefault()
    if (buscando) return
    setBuscando(true); setError(''); setMensaje(''); setUsuario(null)
    try {
      const encontrado = await arbitroService.buscarUsuario(ligaId, email.trim())
      setUsuario(encontrado)
      if (!encontrado) setError('No se encontró otro usuario con ese correo exacto.')
    } catch (e) { setError(mensajeError(e)) }
    finally { setBuscando(false) }
  }
  async function guardar(tipo) {
    if (guardando) return
    setGuardando(true); setError(''); setMensaje('')
    try {
      const resultado = tipo === 'revocar' ? await arbitroService.revocar(ligaId, motivo.trim())
        : tipo === 'reemplazar' ? await arbitroService.reemplazar(ligaId, usuario.id, motivo.trim())
          : await arbitroService.asignar(ligaId, usuario.id)
      setMensaje(resultado.message)
      actualizar()
    } finally { setGuardando(false) }
  }
  const bloqueado = guardando || buscando
  return <>
    <EncabezadoPagina titulo="Árbitros" descripcion="Administra la autorización arbitral de este campeonato." />
    <Aviso exito={mensaje} error={error} />
    <EstadoVista cargando={cargando} error={errorCarga} reintentar={actualizar} />
    {!cargando && !errorCarga && <div className="grid gap-6 xl:grid-cols-2">
      <Tarjeta><h2 className="mb-4 font-titulo text-xl font-bold">Árbitro activo</h2>
        <p className="mb-6 text-sm">{datos.vigente ? datos.vigente.usuario.name : 'Sin árbitro asignado'}</p>
        <form onSubmit={buscar} className="grid gap-4">
          <Campo etiqueta="Correo exacto del usuario registrado" type="email" required maxLength={255} value={email} disabled={bloqueado} onChange={e => { setEmail(e.target.value); setUsuario(null) }} ayuda="Solicita el correo directamente a la persona. No se muestra un catálogo de cuentas." />
          <Boton type="submit" variante="secundario" disabled={bloqueado}>{buscando ? 'Buscando…' : 'Buscar usuario'}</Boton>
        </form>
        {usuario && <p className="my-4 text-sm break-words" role="status">Usuario seleccionado: <strong>{usuario.name}</strong> · ID {usuario.id}</p>}
        {datos.vigente && <div className="my-4"><Campo as="textarea" etiqueta="Motivo del reemplazo o revocación" maxLength={1000} value={motivo} disabled={bloqueado} onChange={e => setMotivo(e.target.value)} /></div>}
        <div className="mt-5 flex flex-wrap gap-3">
          <Boton disabled={bloqueado || !usuario || (datos.vigente && (!motivo.trim() || usuario.id === datos.vigente.usuario_id))} onClick={() => {
            if (datos.vigente) setAccion('reemplazar')
            else guardar('asignar').catch(e => setError(mensajeError(e)))
          }}>{datos.vigente ? 'Reemplazar árbitro' : 'Asignar árbitro'}</Boton>
          {datos.vigente && <Boton variante="peligro" disabled={bloqueado || !motivo.trim()} onClick={() => setAccion('revocar')}>Revocar autorización</Boton>}
        </div>
      </Tarjeta>
      <HistorialArbitros asignaciones={datos.historial} />
    </div>}
    {accion && <Confirmacion titulo={accion === 'revocar' ? 'Revocar autorización' : 'Reemplazar árbitro'} texto={accion === 'revocar' ? 'El árbitro perderá su autorización. Su historial se conservará.' : `Se revocará al árbitro actual y se asignará a ${usuario.name}. El historial se conservará.`} onCerrar={() => setAccion(null)} onConfirmar={() => guardar(accion)} />}
  </>
}
