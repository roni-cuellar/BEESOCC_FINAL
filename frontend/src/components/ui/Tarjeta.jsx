export default function Tarjeta({
  as: Elemento = 'section',
  sinRelleno = false,
  className = '',
  children,
  ...props
}) {
  return (
    <Elemento
      {...props}
      className={
        'min-w-0 rounded-2xl border border-borde bg-blanco shadow-sm ' +
        (sinRelleno ? '' : 'p-5 sm:p-6 ') +
        className
      }
    >
      {children}
    </Elemento>
  )
}
