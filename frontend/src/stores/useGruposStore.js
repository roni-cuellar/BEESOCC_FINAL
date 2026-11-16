import { create } from 'zustand'
import grupoService from '../services/grupoService'
import faseService from '../services/faseService'
import ligaService from '../services/ligaService'
import equipoService from '../services/equipoService'
import arbitroService from '../services/arbitroService'
import partidoService from '../services/partidoService'
import { mensajeError } from '../services/api'

const inicial = {
  contexto: '',
  liga: null,
  fase: null,
  eliminacion: null,
  inscripciones: [],
  clasificacion: null,
  tablas: [],
  expedientes: {},
  historial: [],
  historialCuadro: [],
  marcadores: {},
  vista: 'oficial',
  cargando: true,
  operando: false,
  previa: null,
  error: '',
  errorOperacion: '',
  mensaje: '',
}
const clavesCuadro = ['metodo', 'rondas', 'sorteo', 'confirmacion_sorteo', 'cruces', 'confirmacion']
export const envioCuadroGrupos = (vista) =>
  Object.fromEntries(clavesCuadro.map((c) => [c, vista[c]]))

export function crearGruposStore() {
  let version = 0,
    revisionPrevia = 0,
    pendiente = false
  return create((set, get) => ({
    ...inicial,
    limpiar: () => {
      version++
      revisionPrevia++
      set({ ...inicial })
    },
    invalidarPrevia: () => {
      revisionPrevia++
      set({ previa: null, errorOperacion: '' })
    },
    cargar: async (ligaId, arbitral = false, vista = get().vista) => {
      const actual = ++version
      revisionPrevia++
      const contexto = `${ligaId}:${arbitral}`
      set({ ...inicial, contexto, vista, operando: pendiente })
      try {
        const [fases, liga, inscripciones] = await Promise.all([
          faseService.listar(ligaId),
          arbitral ? arbitroService.partidos(ligaId).then(() => null) : ligaService.obtener(ligaId),
          arbitral ? Promise.resolve([]) : equipoService.inscripciones(ligaId),
        ])
        if (actual !== version) return
        if (liga && liga.formato !== 'grupos_eliminacion') throw { response: { status: 404 } }
        const elegida = fases.find((f) => f.tipo === 'grupos')
        let fase = null,
          clasificacion = null,
          tablas = [],
          expedientes = {},
          historial = [],
          eliminacion = null,
          historialCuadro = [],
          marcadores = {}
        if (elegida) {
          fase = await faseService.obtener(elegida.id)
          if (actual !== version) return
          if (String(fase.campeonato_id) !== String(ligaId) || fase.tipo !== 'grupos')
            throw { response: { status: 404 } }
          const [oficial, casos, auditoria] = await Promise.all([
            grupoService.clasificacion(fase.id),
            Promise.all(fase.grupos.map(async (g) => [g.id, await grupoService.desempates(g.id)])),
            faseService.auditoria(fase.id),
          ])
          clasificacion = oficial
          expedientes = Object.fromEntries(casos)
          historial = auditoria
          if (actual !== version) return
          tablas =
            vista === 'oficial'
              ? oficial.tablas
              : await Promise.all(fase.grupos.map((g) => grupoService.tabla(g.id, vista)))
          const destino = oficial.transicion_eliminacion?.fase_eliminacion_id
          if (destino) {
            eliminacion = await faseService.obtener(destino)
            if (
              String(eliminacion.campeonato_id) !== String(ligaId) ||
              String(eliminacion.fase_origen_id) !== String(fase.id) ||
              eliminacion.tipo !== 'eliminacion'
            )
              throw { response: { status: 404 } }
            historialCuadro = await faseService.auditoria(destino)
          }
          const partidos = [
            ...fase.grupos.flatMap((g) => g.partidos || []),
            ...Object.values(expedientes).flatMap((cs) => cs.flatMap((c) => c.partidos)),
            ...(eliminacion?.rondas || []).flatMap((r) => r.llaves.flatMap((l) => l.partidos)),
          ]
          const unicos = [...new Map(partidos.map((p) => [p.id, p])).values()]
          const respuestas = await Promise.allSettled(
            unicos.map((p) => partidoService.marcador(p.id)),
          )
          respuestas.forEach((r, i) => {
            if (r.status === 'rejected' && [401, 403, 404].includes(r.reason.response?.status))
              throw r.reason
            marcadores[unicos[i].id] =
              r.status === 'fulfilled' ? { datos: r.value } : { error: mensajeError(r.reason) }
          })
        }
        if (actual === version)
          set({
            liga,
            inscripciones,
            fase,
            clasificacion,
            tablas,
            expedientes,
            historial,
            eliminacion,
            historialCuadro,
            marcadores,
            cargando: false,
          })
      } catch (e) {
        if (actual === version)
          set({
            ...inicial,
            contexto,
            vista,
            operando: pendiente,
            cargando: false,
            error: mensajeError(e),
          })
      }
    },
    operar: async (accion, ligaId, arbitral, mensaje) => {
      if (pendiente || get().cargando || get().error) return
      pendiente = true
      const actual = version
      get().invalidarPrevia()
      set({ operando: true, mensaje: '', errorOperacion: '' })
      try {
        await accion()
        if (actual !== version) return
        const recarga = version + 1
        await get().cargar(ligaId, arbitral)
        if (version === recarga && !get().error) set({ mensaje })
      } catch (e) {
        if (actual === version) {
          if ([401, 403, 404].includes(e.response?.status)) {
            version++
            set({
              ...inicial,
              contexto: `${ligaId}:${arbitral}`,
              cargando: false,
              operando: true,
              error: mensajeError(e),
            })
          } else {
            if (e.response?.status === 409) {
              const recarga = version + 1
              await get().cargar(ligaId, arbitral)
              if (version !== recarga) return
            }
            set({ errorOperacion: mensajeError(e) })
          }
        }
        throw e
      } finally {
        pendiente = false
        set({ operando: false })
      }
    },
    previsualizar: async (tipo, datos, grupoId) => {
      if (
        pendiente ||
        get().cargando ||
        get().error ||
        !get().fase ||
        get().contexto.endsWith(':true')
      )
        return
      pendiente = true
      const actual = version,
        revision = ++revisionPrevia,
        faseId = get().fase.id
      const envio = structuredClone(datos)
      set({ operando: true, previa: null, errorOperacion: '', mensaje: '' })
      try {
        const servicio = {
          distribucion: 'previsualizarDistribucion',
          desempate: 'previsualizarDesempate',
          eliminacion: 'previsualizarEliminacion',
        }[tipo]
        const vista = await grupoService[servicio](grupoId ?? faseId, envio)
        if (actual === version && revision === revisionPrevia)
          set({ previa: { tipo, vista, faseId, grupoId } })
      } catch (e) {
        if (actual === version) {
          const [ligaId, arbitral] = get().contexto.split(':')
          if ([401, 403, 404, 409].includes(e.response?.status)) {
            const recarga = version + 1
            await get().cargar(ligaId, arbitral === 'true')
            if (version !== recarga) return
          }
          if ([401, 403, 404].includes(e.response?.status))
            set({
              ...inicial,
              contexto: get().contexto,
              cargando: false,
              operando: true,
              error: mensajeError(e),
            })
          else set({ errorOperacion: mensajeError(e) })
        }
      } finally {
        pendiente = false
        set({ operando: false })
      }
    },
    confirmarPrevia: async (ligaId, arbitral) => {
      const p = get().previa
      if (!p || arbitral || p.faseId !== get().fase?.id)
        throw {
          response: {
            status: 409,
            data: { message: 'Actualiza y vuelve a previsualizar antes de confirmar.' },
          },
        }
      const { vista: v } = p
      const accion =
        p.tipo === 'distribucion'
          ? () =>
              grupoService.confirmarDistribucion(
                p.faseId,
                Object.fromEntries(
                  [
                    'metodo',
                    'cantidad_grupos',
                    'clasificados_por_grupo',
                    'grupos',
                    'confirmacion',
                  ].map((c) => [c, v[c]]),
                ),
              )
          : p.tipo === 'desempate'
            ? () =>
                grupoService.generarDesempate(p.grupoId, {
                  clave: v.clave,
                  confirmacion: v.confirmacion,
                })
            : () => grupoService.generarEliminacion(p.faseId, envioCuadroGrupos(v))
      return get().operar(accion, ligaId, arbitral, 'Confirmación registrada correctamente.')
    },
  }))
}
