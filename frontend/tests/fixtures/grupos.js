// Respuestas ficticias fijas para pruebas de presentación; no son reglas deportivas.
export function escenarioGrupos(etapa = 'clasificados') {
  const firma = 'a'.repeat(64)
  const liga = { id: 44, nombre: 'Copa BEESOCC · demostración', temporada: '2026', estado: 'planificacion', formato: 'grupos_eliminacion' }
  const inscripciones = ['Águilas de Bogotá', 'Leones de Medellín', 'Tigres de Cali', 'Halcones del Norte', 'Cóndores del Sur', 'Lobos de la Sabana'].map((nombre, i) => ({ id: i + 1, estado: 'activo', equipo: { id: i + 1, nombre } }))
  const equipos = inscripciones.map(i => ({ id: i.id + 100, campeonato_equipo_id: i.id, inscripcion: i }))
  const participante = (id, rol, indice) => ({ id: indice, campeonato_equipo_id: id, rol })
  const partido = (id, grupo, local, visita, extra = false) => ({ id, campeonato_id: 44, fase_id: 7, grupo_id: grupo, llave_id: extra ? 20 : null, tipo_partido: extra ? 'desempate' : 'regular', numero_serie: extra ? 1 : null, grupo_desempate_id: extra ? 1 : null, jornada: 1, estado: etapa === 'calendario' ? 'programado' : 'finalizado', tipo_resultado: 'deportivo', oficializado_at: etapa === 'calendario' ? null : '2026-11-15T20:00:00Z', participantes: [participante(local, 'local', id * 2), participante(visita, 'visitante', id * 2 + 1)] })
  const grupos = [1, 2, 3].map((n, i) => ({ id: 11 + i, codigo: `G${n}`, fase_id: 7, equipos: equipos.filter(e => [n, n + 3].includes(e.campeonato_equipo_id)), partidos: [partido(30 + i, 11 + i, n, n + 3)] }))
  const extra = partido(40, 11, 1, 4, true)
  const pendiente = { clave: 'b'.repeat(64), inscripciones: [1, 4], rango_posiciones: [1, 2], generable: etapa === 'empate', motivo: etapa === 'penales' ? 'avance_desempate_pendiente' : 'partido_extra_pendiente' }
  const confirmada = ['clasificados', 'cuadro'].includes(etapa)
  const completa = etapa !== 'calendario'
  const tablas = grupos.map((g, n) => ({ grupo_id: g.id, fase_id: 7, vista: 'oficial', definitiva: completa && (n > 0 || confirmada), calendario_generado: true, resultados_completos: completa, partidos_oficiales: completa ? 1 : 0, partidos_esperados: 1, pendientes_resolucion: n === 0 && !confirmada ? [pendiente] : [], desempates_usados: n === 0 && ['penales', 'clasificados', 'cuadro'].includes(etapa) ? [1] : [], tabla: g.equipos.map((e, i) => ({ fase_equipo_id: e.id, campeonato_equipo_id: e.campeonato_equipo_id, nombre: e.inscripcion.equipo.nombre, posicion: n === 0 && !confirmada ? null : i + 1, PJ: completa ? 1 : 0, PG: n === 0 ? 0 : i === 0 ? 1 : 0, PE: n === 0 ? 1 : 0, PP: n === 0 ? 0 : i, GF: n === 0 ? 0 : i === 0 ? 1 : 0, GC: n === 0 ? 0 : i, DG: n === 0 ? 0 : i === 0 ? 1 : -1, PTS: completa ? n === 0 ? 1 : i === 0 ? 3 : 0 : 0 })) }))
  const caso = { id: 1, grupo_id: 11, clave: pendiente.clave, posicion_inicio: 1, inscripciones: [1, 4], llave_id: 20, partidos: [extra], llave: { id: 20, numero: 1, estado: confirmada ? 'resuelta' : 'pendiente', ganador_fase_equipo_id: confirmada ? 101 : null, penales_plaza_1: confirmada ? 4 : null, penales_plaza_2: confirmada ? 3 : null } }
  const fase = { id: 7, campeonato_id: 44, nombre: 'Fase de grupos', tipo: 'grupos', estado: confirmada ? 'finalizada' : 'planificacion', clasificados_por_grupo: 1, grupos: etapa === 'inicio' ? [] : grupos, equipos: etapa === 'inicio' ? [] : equipos }
  const candidatos = confirmada ? [1, 2, 3].map((id, i) => ({ campeonato_equipo_id: id, fase_equipo_id: id + 100, origen_tipo: 'grupo_puesto', origen_grupo_id: 11 + i, origen_puesto: 1 })) : []
  const clasificacion = { fase_id: 7, clasificados_por_grupo: 1, huella: 'huella-ficticia', clasificacion_vigente: confirmada, lista_para_clasificar: confirmada, candidatos, confirmacion: confirmada ? firma : null, tablas: etapa === 'inicio' ? [] : tablas, transicion_eliminacion: { habilitada: confirmada && etapa !== 'cuadro', fase_eliminacion_id: etapa === 'cuadro' ? 8 : null } }
  const sorteo = ['11:1', '12:1', '13:1']
  const rondas = [{ modalidad: 'unico', desempate: 'penales' }, { modalidad: 'unico', desempate: 'penales' }]
  const previa = { fase_id: 7, huella: clasificacion.huella, metodo: 'automatico', rondas, sorteo, confirmacion_sorteo: 'c'.repeat(64), cruces: [['11:1', null], ['12:1', '13:1']], clasificados: candidatos, descansos: ['11:1'], cruces_mismo_grupo_inevitables: 0, confirmacion: firma }
  const plaza = (id, posicion, equipo, origen, grupo) => ({ id, posicion, fase_equipo_id: equipo, origen_tipo: origen, origen_grupo_id: grupo, origen_puesto: grupo ? 1 : null })
  const finalista = { ...partido(50, null, 2, 3), fase_id: 8, grupo_id: null, llave_id: 22, numero_serie: 1 }
  const eliminacion = { id: 8, campeonato_id: 44, fase_origen_id: 7, tipo: 'eliminacion', nombre: 'Eliminación de grupos', estado: 'en_curso', equipos: equipos.slice(0, 3), rondas: [
    { ...rondas[0], id: 8, numero: 1, nombre: 'Semifinal', estado: 'en_curso', llaves: [
      { id: 21, numero: 1, estado: 'resuelta', metodo_resolucion: 'bye', ganador_fase_equipo_id: 101, plazas: [plaza(1, 1, 101, 'grupo_puesto', 11), plaza(2, 2, null, 'bye')], partidos: [] },
      { id: 22, numero: 2, estado: 'pendiente', ganador_fase_equipo_id: null, penales_plaza_1: null, penales_plaza_2: null, plazas: [plaza(3, 1, 102, 'grupo_puesto', 12), plaza(4, 2, 103, 'grupo_puesto', 13)], partidos: [finalista] },
    ] }, { ...rondas[1], id: 9, numero: 2, nombre: 'Final', estado: 'planificacion', llaves: [{ id: 23, numero: 1, estado: 'pendiente', ganador_fase_equipo_id: null, plazas: [plaza(5, 1, 101, 'ganador_llave'), plaza(6, 2, null, 'ganador_llave')], partidos: [] }] },
  ] }
  const marcadores = Object.fromEntries([...grupos.flatMap(g => g.partidos), extra, finalista].map(p => [p.id, { local: { nombre: inscripciones[p.participantes[0].campeonato_equipo_id - 1].equipo.nombre, goles: p.id === 40 ? 1 : 0 }, visitante: { nombre: inscripciones[p.participantes[1].campeonato_equipo_id - 1].equipo.nombre, goles: p.id === 40 ? 1 : 0 } }]))
  const historial = [{ id: 1, usuario_id: 1, entidad_tipo: 'fase', entidad_id: 7, accion: 'confirmar_grupos', motivo: 'Distribución ficticia para revisar la interfaz.', fecha: '2026-11-15T20:00:00Z', anterior_json: null, posterior_json: { clasificados_por_grupo: 1, grupos: [[1, 4], [2, 5], [3, 6]] } }]
  return { firma, liga, inscripciones, fase, clasificacion, caso, pendiente, previa, eliminacion, marcadores, historial }
}
