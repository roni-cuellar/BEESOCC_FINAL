import { create } from 'zustand'
import faseService from '../services/faseService'
import ligaService from '../services/ligaService'
import equipoService from '../services/equipoService'
import arbitroService from '../services/arbitroService'
import partidoService from '../services/partidoService'
import { mensajeError } from '../services/api'

const inicial = {
  fases: [],
  fase: null,
  liga: null,
  inscripciones: [],
  marcadores: {},
  historial: [],
  contexto: '',
  cargando: true,
  operando: false,
  error: '',
  errorOperacion: '',
  mensaje: '',
  previa: null,
  envio: null,
}

// Una instancia por pantalla evita conservar datos privados de otro campeonato.
export function crearEliminacionStore() {
  let version = 0
  let pendiente = false
  return create((set, get) => ({
    ...inicial,
    limpiar: () => {
      version++
      set({ ...inicial })
    },
    invalidarPrevia: () => set({ previa: null, envio: null, errorOperacion: '' }),
    cargar: async (ligaId, arbitral, faseId) => {
      const actual = ++version
      set({ ...inicial, contexto: ligaId + ':' + arbitral, operando: pendiente })
      try {
        const fases = (await faseService.listar(ligaId)).filter((f) => f.tipo === 'eliminacion')
        if (actual !== version) return
        const [liga, inscripciones] = await Promise.all(
          arbitral
            ? [arbitroService.partidos(ligaId).then(() => null), Promise.resolve([])]
            : [ligaService.obtener(ligaId), equipoService.inscripciones(ligaId)],
        )
        if (actual !== version) return
        const elegida = fases.find((f) => String(f.id) === String(faseId)) || fases[0]
        let fase = null,
          historial = [],
          marcadores = {}
        if (elegida) {
          fase = await faseService.obtener(elegida.id)
          if (actual !== version) return
          if (String(fase.campeonato_id) !== String(ligaId)) throw { response: { status: 404 } }
          const partidos = fase.rondas.flatMap((r) => r.llaves.flatMap((l) => l.partidos))
          const respuestas = await Promise.allSettled(
            partidos.map((p) => partidoService.marcador(p.id)),
          )
          for (let i = 0; i < respuestas.length; i++) {
            const r = respuestas[i]
            if (r.status === 'rejected' && [401, 403, 404].includes(r.reason.response?.status))
              throw r.reason
            marcadores[partidos[i].id] =
              r.status === 'fulfilled' ? { datos: r.value } : { error: mensajeError(r.reason) }
          }
          historial = await faseService.auditoria(fase.id)
        }
        if (actual === version)
          set({ fases, fase, liga, inscripciones, marcadores, historial, cargando: false })
      } catch (e) {
        if (actual === version)
          set({
            ...inicial,
            contexto: ligaId + ':' + arbitral,
            cargando: false,
            operando: pendiente,
            error: mensajeError(e),
          })
      }
    },
    operar: async (accion, ligaId, arbitral, texto, faseId) => {
      if (pendiente || get().cargando || get().error) return
      pendiente = true
      const actual = version
      set({ operando: true, errorOperacion: '', mensaje: '' })
      try {
        const resultado = await accion()
        if (actual !== version) return
        await get().cargar(ligaId, arbitral, resultado?.fase_id || faseId)
        if (!get().error) set({ mensaje: texto })
      } catch (e) {
        if (actual === version) {
          const status = e.response?.status
          set({ previa: null, envio: null })
          if ([401, 403, 404].includes(status)) {
            version++
            set({
              ...inicial,
              contexto: ligaId + ':' + arbitral,
              cargando: false,
              error: mensajeError(e),
            })
          } else {
            if (status === 409) await get().cargar(ligaId, arbitral, faseId)
            set({ errorOperacion: mensajeError(e) })
          }
        }
        throw e
      } finally {
        pendiente = false
        set({ operando: false })
      }
    },
    previsualizar: async (datos) => {
      if (pendiente || get().cargando || get().error || !get().fase) return
      pendiente = true
      const actual = version
      const faseAnterior = get().fase
      const envio = structuredClone(datos)
      set({ operando: true, previa: null, envio: null, errorOperacion: '' })
      try {
        const previa = await faseService.previsualizar(get().fase.id, envio)
        if (actual === version) set({ previa, envio })
      } catch (e) {
        if (actual === version) {
          if ([401, 403, 404].includes(e.response?.status)) {
            version++
            set({ ...inicial, contexto: get().contexto, cargando: false, error: mensajeError(e) })
          } else {
            if (e.response?.status === 409)
              await get().cargar(faseAnterior.campeonato_id, false, faseAnterior.id)
            set({ errorOperacion: mensajeError(e) })
          }
        }
      } finally {
        pendiente = false
        set({ operando: false })
      }
    },
    generar: async (ligaId, arbitral) => {
      const { previa, envio, fase } = get()
      if (!previa || !envio || !fase)
        throw {
          response: {
            status: 409,
            data: { message: 'Vuelve a previsualizar el cuadro antes de confirmar.' },
          },
        }
      return get().operar(
        () => faseService.generar(fase.id, { ...envio, confirmacion: previa.confirmacion }),
        ligaId,
        arbitral,
        'Cuadro generado correctamente.',
        fase.id,
      )
    },
  }))
}
