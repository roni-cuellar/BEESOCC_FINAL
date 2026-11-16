import { useId } from 'react'

// Campo compartido para input, select y textarea; conserva las validaciones nativas.
export default function Campo({
  as: Control = 'input',
  etiqueta,
  error,
  ayuda,
  id,
  className = '',
  children,
  ...props
}) {
  const generado = useId()
  const campoId = id || generado
  const descripcionId = campoId + '-descripcion'
  const descripcion =
    [props['aria-describedby'], (error || ayuda) && descripcionId].filter(Boolean).join(' ') ||
    undefined
  return (
    <div className="grid min-w-0 gap-2 font-sans">
      <label htmlFor={campoId} className="text-sm font-semibold text-oscuro">
        {etiqueta}
      </label>
      <Control
        {...props}
        id={campoId}
        aria-invalid={error ? true : undefined}
        aria-describedby={descripcion}
        className={
          'w-full min-w-0 rounded-xl border bg-blanco px-3 py-3 font-sans text-sm text-oscuro outline-none transition-colors focus:border-marca focus:ring-2 focus:ring-marca/25 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:opacity-60 ' +
          (Control === 'textarea' ? 'min-h-28 resize-y ' : '') +
          (error ? 'border-red-600 ' : 'border-borde ') +
          className
        }
      >
        {children}
      </Control>
      {(error || ayuda) && (
        <p
          id={descripcionId}
          className={'text-sm ' + (error ? 'text-red-700' : 'text-gris')}
          role={error ? 'alert' : undefined}
        >
          {error || ayuda}
        </p>
      )}
    </div>
  )
}
