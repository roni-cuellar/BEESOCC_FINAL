import IlustracionModulo from '../IlustracionModulo'

export default function EncabezadoPagina({
  titulo,
  descripcion,
  acciones,
  ilustracion,
  imagen,
  tituloAs: Titulo = 'h1',
}) {
  return (
    <div className="encabezado-modulo mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 items-center gap-4">
        {imagen ?? (ilustracion && <IlustracionModulo tipo={ilustracion} />)}
        <div className="min-w-0">
          <Titulo className="font-titulo text-3xl font-bold text-oscuro sm:text-4xl">
            {titulo}
          </Titulo>
          {descripcion && <p className="mt-2 font-sans text-sm text-gris">{descripcion}</p>}
        </div>
      </div>
      {acciones && <div className="flex flex-wrap gap-3">{acciones}</div>}
    </div>
  )
}
