import { useState } from 'react'
import { Link } from 'react-router-dom'
import ilustracion from '../assets/branding/optimizadas/campeonatos.png'
import { Boton, Tarjeta } from './ui'
import Icono from './Icono'
import LogoCampeonato from './LogoCampeonato'

function CodigoCompartible({ codigo, nombre }) {
  const [aviso, setAviso] = useState('')

  async function copiar() {
    try {
      await navigator.clipboard.writeText(codigo)
      setAviso('Código copiado.')
    } catch {
      setAviso('Selecciona el código para copiarlo manualmente.')
    }
  }

  return (
    <div className="mb-4 rounded-xl border border-green-100 bg-green-50 p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="text-xs font-semibold text-marca-oscuro">Código de liga</p>
          <code className="mt-1 block font-mono font-semibold tracking-widest text-marca-oscuro">
            {codigo}
          </code>
        </div>
        <Boton variante="secundario" aria-label={'Copiar código de ' + nombre} onClick={copiar}>
          Copiar
        </Boton>
      </div>
      <p role="status" className="text-sm text-marca-oscuro">
        {aviso}
      </p>
    </div>
  )
}

function LigaCard({ liga, onEliminar, onPublicacion, publicando = false, publica = false }) {
  const estado =
    liga.estado === 'planificacion' ? 'Planificación' : liga.estado.replaceAll('_', ' ')
  return (
    <Tarjeta
      as="article"
      sinRelleno
      className="liga-tarjeta flex h-full flex-col overflow-hidden transition-shadow hover:shadow-md"
    >
      <div className="relative flex h-40 items-center justify-between overflow-hidden border-b border-green-100 bg-[#edf5ef] px-6 sm:h-44">
        <div className="z-1">
          <p className="text-[10px] font-semibold tracking-[.15em] text-marca-oscuro">BEESOCC</p>
          <p className="mt-2 font-titulo text-2xl font-bold text-marca-oscuro">
            Temporada
            <br />
            {liga.temporada}
          </p>
        </div>
        <LogoCampeonato
          ruta={liga.logo}
          className="size-24 sm:size-28"
          respaldo={
            <img
              className="size-36 object-contain sm:size-44"
              src={ilustracion}
              alt=""
              loading="lazy"
              width="384"
              height="384"
            />
          }
        />
      </div>
      <div className="flex flex-1 flex-col p-5 sm:p-6">
        <span className="mb-4 w-fit rounded-full border border-green-100 bg-green-50 px-3 py-1 text-[11px] font-semibold text-marca-oscuro">
          {estado}
        </span>
        <span className="mb-3 text-xs font-semibold text-marca-oscuro">
          {publica || liga.publicado ? 'Publicado' : 'Privado'}
        </span>
        <h2 className="font-titulo text-xl leading-snug font-bold break-words text-oscuro">
          {liga.nombre}
        </h2>
        <p className="mt-3 line-clamp-2 text-sm leading-6 break-words text-gris">
          {liga.descripcion || 'Sin descripción'}
        </p>
        <p className="mt-4 mb-6 flex items-center gap-2 text-xs text-gris">
          <Icono nombre="partidos" />
          {liga.formato === 'todos_contra_todos'
            ? 'Todos contra todos'
            : liga.formato === 'eliminacion_directa'
              ? 'Eliminación directa'
              : liga.formato === 'grupos_eliminacion'
                ? 'Grupos + eliminación'
                : liga.formato}
        </p>
        {!publica && liga.codigo_liga && (
          <CodigoCompartible codigo={liga.codigo_liga} nombre={liga.nombre} />
        )}
        <Boton
          className="mt-auto w-full"
          to={publica ? '/campeonatos/' + liga.id : '/ligas/' + liga.id + '/dashboard'}
        >
          {publica ? 'Ver campeonato' : 'Administrar'}
        </Boton>
        {!publica && (
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-borde pt-3">
            <Link
              className="inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-marca-oscuro hover:bg-green-50"
              to={'/ligas/' + liga.id + '/editar'}
              aria-label={'Editar ' + liga.nombre}
            >
              Editar liga
            </Link>
            {onPublicacion && (
              <button
                type="button"
                className="inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-marca-oscuro hover:bg-green-50 disabled:opacity-50"
                disabled={publicando}
                onClick={() => onPublicacion(liga)}
                aria-label={(liga.publicado ? 'Ocultar ' : 'Publicar ') + liga.nombre}
              >
                {liga.publicado ? 'Ocultar' : 'Publicar'}
              </button>
            )}
            <button
              type="button"
              className="inline-flex min-h-11 items-center rounded-lg px-2 text-sm text-red-700 hover:bg-red-50"
              onClick={() => onEliminar(liga)}
              aria-label={'Eliminar ' + liga.nombre}
            >
              Eliminar
            </button>
          </div>
        )}
      </div>
    </Tarjeta>
  )
}
export default LigaCard
