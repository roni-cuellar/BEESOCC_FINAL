import { useCallback, useEffect, useState } from 'react'
import { mensajeError } from '../services/api'

export default function useConsultaPublica(consultar, clave) {
  const [revision, setRevision] = useState(0)
  const [resultado, setResultado] = useState(null)
  const reintentar = useCallback(() => setRevision((n) => n + 1), [])
  useEffect(() => {
    let vigente = true
    consultar().then(
      (datos) => {
        if (vigente) setResultado({ consultar, clave, revision, datos })
      },
      (fallo) => {
        if (vigente) setResultado({ consultar, clave, revision, fallo })
      },
    )
    return () => {
      vigente = false
    }
  }, [consultar, clave, revision])
  const actual =
    resultado?.consultar === consultar &&
    resultado.clave === clave &&
    resultado.revision === revision
  return {
    datos: actual ? resultado.datos : undefined,
    cargando: !actual,
    error: actual && resultado.fallo ? mensajeError(resultado.fallo) : '',
    estadoError: actual ? resultado.fallo?.response?.status : undefined,
    reintentar,
  }
}
