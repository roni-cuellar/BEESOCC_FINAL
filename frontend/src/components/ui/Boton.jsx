import { Link } from 'react-router-dom'

const variantes = {
  principal:
    'border-marca-oscuro bg-marca-oscuro text-blanco shadow-sm hover:bg-[#175339] hover:shadow-md',
  secundario:
    'border-borde bg-blanco text-marca-oscuro shadow-xs hover:border-green-200 hover:bg-green-50',
  peligro: 'border-red-700 bg-red-700 text-blanco hover:bg-red-800',
  claro: 'border-white bg-white text-marca-oscuro hover:bg-green-50',
}

export default function Boton({
  variante = 'principal',
  cargando = false,
  disabled,
  type = 'button',
  to,
  className = '',
  children,
  ...props
}) {
  const Control = to ? Link : 'button'
  const bloqueado = disabled || cargando
  const atributos = to
    ? {
        to,
        'aria-disabled': bloqueado || undefined,
        tabIndex: bloqueado ? -1 : props.tabIndex,
        onClick: (e) => {
          if (bloqueado) e.preventDefault()
          else props.onClick?.(e)
        },
      }
    : { type, disabled: bloqueado }
  return (
    <Control
      {...props}
      {...atributos}
      aria-busy={cargando || props['aria-busy'] || undefined}
      className={
        'inline-flex min-h-12 max-w-full items-center justify-center gap-2 rounded-xl border px-5 py-3 text-center font-sans text-sm leading-5 font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca disabled:cursor-not-allowed disabled:opacity-55 aria-disabled:cursor-not-allowed aria-disabled:opacity-55 motion-reduce:transition-none ' +
        (variantes[variante] || variantes.principal) +
        ' ' +
        className
      }
    >
      {cargando ? 'Guardando…' : children}
    </Control>
  )
}
