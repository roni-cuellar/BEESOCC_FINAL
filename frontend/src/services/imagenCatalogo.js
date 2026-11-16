import api from './api.js'

export function validarArchivoImagen(archivo) {
  return archivo &&
    (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type) ||
      archivo.size > 5 * 1024 * 1024)
    ? 'Elige una imagen JPG, PNG o WEBP de hasta 5 MB.'
    : ''
}

export async function guardarCatalogo(ruta, datos, campo, editar = false) {
  const { [campo]: archivo, eliminar_imagen, ...campos } = datos
  const envio = { ...campos, ...(eliminar_imagen ? { eliminar_imagen: true } : {}) }
  if (archivo instanceof File) {
    const formulario = new FormData()
    Object.entries(envio).forEach(([clave, valor]) =>
      formulario.append(clave, typeof valor === 'boolean' ? (valor ? '1' : '0') : String(valor)),
    )
    formulario.append(campo, archivo)
    // PHP procesa los archivos multipart mediante POST; Laravel resuelve PATCH.
    if (editar) formulario.append('_method', 'PATCH')
    return (await api.post(ruta, formulario)).data
  }
  return (await api[editar ? 'patch' : 'post'](ruta, envio)).data
}
