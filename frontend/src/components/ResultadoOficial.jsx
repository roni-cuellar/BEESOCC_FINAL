import { esResultadoOficial, fechaExpediente } from '../utils/correcciones'

export default function ResultadoOficial({ partido }) {
  const oficial = esResultadoOficial(partido)
  return (
    <div className={'resultado-oficial ' + (oficial ? 'resultado-confirmado' : '')}>
      <strong>
        {oficial
          ? 'Resultado oficial'
          : partido.estado === 'finalizado'
            ? 'Finalizado sin oficialización'
            : 'Resultado provisional'}
      </strong>
      {oficial && (
        <span>
          {partido.tipo_resultado === 'administrativo' ? 'Administrativo' : 'Deportivo'} ·{' '}
          {fechaExpediente(partido.oficializado_at)}
        </span>
      )}
      {!oficial && partido.estado === 'finalizado' && (
        <span>Se conserva el registro histórico. No se oficializa automáticamente.</span>
      )}
    </div>
  )
}
