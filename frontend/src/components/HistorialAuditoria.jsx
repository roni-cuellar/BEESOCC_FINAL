import { fechaExpediente } from '../utils/correcciones'

const titulos = {
  configurar_requisitos_inicio: 'Requisitos de inicio configurados', iniciar_campeonato: 'Campeonato iniciado',
  finalizar_campeonato: 'Campeonato finalizado', crear_plantilla: 'Jugador inscrito',
  actualizar_plantilla: 'Inscripción de jugador actualizada', eliminar_plantilla: 'Inscripción corregida', estado_inscripcion: 'Estado del equipo actualizado',
  crear_fase_grupos: 'Fase de grupos creada', confirmar_grupos: 'Distribución de grupos confirmada',
  generar_calendario_grupos: 'Calendario de grupos generado', clasificar_grupos: 'Clasificación oficial confirmada',
  invalidar_clasificacion_grupos: 'Clasificación invalidada por corrección', actualizar_clasificacion_grupos: 'Clasificación actualizada',
  generar_desempate_grupo: 'Desempate adicional confirmado', generar_eliminacion_grupos: 'Sorteo y cruces confirmados',
  solicitar_correccion: 'Solicitud de corrección', resolver_correccion: 'Resolución de solicitud',
  aplicar_correccion: 'Corrección aplicada', oficializar: 'Resultado oficializado',
  registrar_evento: 'Evento registrado', corregir_evento: 'Evento corregido', eliminar_evento: 'Evento eliminado',
  cambiar_estado: 'Cambio de estado', editar_programacion: 'Programación actualizada',
  crear_fase: 'Fase creada', generar_llaves: 'Cuadro generado', resolver_llave: 'Llave resuelta',
  registrar_penales: 'Tanda certificada', actualizar_plaza: 'Plaza actualizada', actualizar_clasificado: 'Clasificado actualizado',
}
const campos = {
  minimo_jugadores: 'Mínimo de jugadores elegibles', jugadores_elegibles: 'Jugadores elegibles al inicio',
  iniciado_at: 'Inicio del campeonato', finalizado_at: 'Finalización del campeonato', campeon_campeonato_equipo_id: 'Inscripción del campeón',
  dorsal: 'Dorsal', jugador_id: 'Jugador', fecha_ingreso: 'Fecha de ingreso', fecha_salida: 'Fecha de salida',
  origen_grupo_id: 'Grupo de procedencia', origen_puesto: 'Puesto de procedencia', clasificados_por_grupo: 'Cupo por grupo', posicion_inicio: 'Puesto inicial', grupo_id: 'Grupo',
  id: 'Identificador', campeonato_id: 'Campeonato', jornada: 'Jornada', fecha_hora: 'Fecha del partido',
  lugar: 'Lugar', estado: 'Estado', tipo_resultado: 'Tipo de resultado', oficializado_at: 'Oficialización',
  partido_id: 'Partido', solicitado_por_id: 'Usuario solicitante', autorizado_por_id: 'Usuario responsable de la decisión',
  motivo: 'Motivo', alcance: 'Alcance', fecha_resolucion: 'Fecha de resolución', fecha_consumo: 'Fecha de aplicación',
  equipo_partido_id: 'Equipo participante', plantilla_id: 'Jugador de plantilla', tipo: 'Tipo', minuto: 'Minuto', gol_relacionado_id: 'Gol relacionado',
  nombre: 'Nombre', orden: 'Orden', fase_id: 'Fase', ronda_id: 'Ronda', numero: 'Número', posicion: 'Posición',
  fase_equipo_id: 'Equipo de fase', ganador_fase_equipo_id: 'Clasificado', metodo_resolucion: 'Método de resolución',
  penales_plaza_1: 'Penales de plaza 1', penales_plaza_2: 'Penales de plaza 2', origen_tipo: 'Procedencia',
  origen_llave_id: 'Llave de origen', campeonato_equipo_id: 'Inscripción', modalidad: 'Modalidad', desempate: 'Desempate',
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
    {datos.siembra && <p>Siembra confirmada (inscripciones): {datos.siembra.join(' → ')}</p>}
    {Array.isArray(datos.sorteo) && <p>Orden del sorteo: {datos.sorteo.join(' → ')}</p>}
    {Array.isArray(datos.cruces) && <ul>{datos.cruces.map((par, i) => <li key={i}>Llave {i + 1}: {Array.isArray(par) ? par.map(p => p ?? 'Descanso').join(' · ') : 'Cruce confirmado'}</li>)}</ul>}
    {Array.isArray(datos.clasificados) && <details><summary>Clasificados ({datos.clasificados.length})</summary>{datos.clasificados.map((c, i) => <ValoresAuditoria key={i} datos={c} />)}</details>}
    {['plazas', 'partidos', 'rondas', 'llaves'].filter(c => Array.isArray(datos[c])).map(campo => <details key={campo}><summary>{campo} ({datos[campo].length})</summary>{datos[campo].map((valor, i) => <ValoresAuditoria key={valor.id ?? i} datos={valor} />)}</details>)}
    {datos.configuracion && <><h5>Configuración confirmada</h5><ValoresAuditoria datos={datos.configuracion} /></>}
  </>
}

export default function HistorialAuditoria({ historial, contexto = 'partido' }) {
  return <section className="panel historial-auditoria"><h2>Historial de auditoría</h2>
    <p className="nota">Registro privado de responsables, motivos y valores anteriores y posteriores. Solo consulta.</p>
    {!historial.length && <p className="nota">No hay cambios auditados para este {contexto}.</p>}
    {historial.map(registro => <details key={registro.id} className="registro-auditoria">
      <summary>{titulos[registro.accion] || registro.accion.replaceAll('_', ' ')} · {fechaExpediente(registro.fecha)} · Usuario #{registro.usuario_id}</summary>
      <p className="motivo-expediente">{registro.motivo}</p>
      {registro.entidad_tipo && <p className="nota">{registro.entidad_tipo} #{registro.entidad_id}</p>}
      <div className="dos-columnas valores-auditoria"><section><h4>Antes del cambio</h4><ValoresAuditoria datos={registro.anterior_json} /></section><section><h4>Después del cambio</h4><ValoresAuditoria datos={registro.posterior_json} /></section></div>
    </details>)}
  </section>
}
