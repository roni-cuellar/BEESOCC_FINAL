export default function EncabezadoPagina({ titulo, descripcion, acciones }) {
  return (
    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="min-w-0">
        <h1 className="font-titulo text-3xl font-bold text-oscuro sm:text-4xl">{titulo}</h1>
        {descripcion && <p className="mt-2 font-sans text-sm text-gris">{descripcion}</p>}
      </div>
      {acciones && <div className="flex flex-wrap gap-3">{acciones}</div>}
    </div>
  )
}
