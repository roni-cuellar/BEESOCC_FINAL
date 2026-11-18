// Laravel admite texto libre; estas opciones son accesos rápidos, no un catálogo restrictivo.
export const modalidadesCampeonato = ['Fútbol 5', 'Fútbol 7', 'Fútbol 11']

export function seleccionModalidad(valor) {
  return !valor || modalidadesCampeonato.includes(valor) ? valor : 'personalizada'
}
