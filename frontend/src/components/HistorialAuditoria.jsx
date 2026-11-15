import { fechaExpediente } from '../utils/correcciones'

const titulos = {
  solicitar_correccion: 'Solicitud de corrección', resolver_correccion: 'Resolución de solicitud',
  aplicar_correccion: 'Corrección aplicada', oficializar: 'Resultado oficializado',
  registrar_evento: 'Evento registrado', corregir_evento: 'Evento corregido', eliminar_evento: 'Evento eliminado',
  cambiar_estado: 'Cambio de estado', editar_programacion: 'Programación actualizada',
}
const campos = {
  id: 'Identificador', campeonato_id: 'Campeonato', jornada: 'Jornada', fecha_hora: 'Fecha del partido',
  lugar: 'Lugar', estado: 'Estado', tipo_resultado: 'Tipo de resultado', oficializado_at: 'Oficialización',
  partido_id: 'Partido', solicitado_por_id: 'Usuario solicitante', autorizado_por_id: 'Usuario responsable de la decisión',
  motivo: 'Motivo', alcance: 'Alcance', fecha_resolucion: 'Fecha de resolución', fecha_consumo: 'Fecha de aplicación',
  equipo_partido_id: 'Equipo participante', plantilla_id: 'Jugador de plantilla', tipo: 'Tipo', minuto: 'Minuto', gol_relacionado_id: 'Gol relacionado',
}

function ValoresAuditoria({ datos }) {
  if (!datos) return <p className="nota">Sin valores registrados.</p>
  return <>
    <dl className="datos-expediente">{Object.entries(datos).filter(([campo]) => campos[campo]).map(([campo, valor]) => <div key={campo}>
      <dt>{campos[campo]}</dt><dd>{valor == null ? 'Sin registrar' : campo.startsWith('fecha_') || campo === 'oficializado_at' ? fechaExpediente(valor) : String(valor).replaceAll('_', ' ')}</dd>
    </div>)}</dl>
    {datos.correccion && <><h5>Expediente</h5><ValoresAuditoria datos={datos.correccion} /></>}
    {datos.participantes && <><h5>Participantes</h5><ul>{datos.participantes.map(p => <li key={p.id}>{p.rol} · equipo inscrito #{p.campeonato_equipo_id} · participante #{p.id}</li>)}</ul></>}
    {datos.eventos && <><h5>Eventos ({datos.eventos.length})</h5><ul className="eventos-auditoria">{datos.eventos.map(evento => <li key={evento.id}>#{evento.id} · {evento.tipo} · {evento.minuto}′ · jugador de plantilla #{evento.plantilla_id} · participante #{evento.equipo_partido_id}{evento.gol_relacionado_id ? ' · gol relacionado #' + evento.gol_relacionado_id : ''}</li>)}</ul></>}
  </>
}

export default function HistorialAuditoria({ historial }) {
  return <section className="panel historial-auditoria"><h2>Historial de auditoría</h2>
    <p className="nota">Registro privado de responsables, motivos y valores anteriores y posteriores. Solo consulta.</p>
    {!historial.length && <p className="nota">No hay cambios auditados para este partido.</p>}
    {historial.map(registro => <details key={registro.id} className="registro-auditoria">
      <summary>{titulos[registro.accion] || registro.accion.replaceAll('_', ' ')} · {fechaExpediente(registro.fecha)} · Usuario #{registro.usuario_id}</summary>
      <p className="motivo-expediente">{registro.motivo}</p>
      <div className="dos-columnas valores-auditoria"><section><h4>Antes del cambio</h4><ValoresAuditoria datos={registro.anterior_json} /></section><section><h4>Después del cambio</h4><ValoresAuditoria datos={registro.posterior_json} /></section></div>
    </details>)}
  </section>
}
