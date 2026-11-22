import { expect, it } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import PlantillaForm from '../../src/components/PlantillaForm'
import SelectorJugador from '../../src/components/SelectorJugador'
import { buscarJugadores, jugadoresDisponibles } from '../../src/utils/inscripciones'

const jugadores = [
  { id: 1, nombre: 'Ángela María', apellido: 'Muñoz' },
  { id: 2, nombre: 'Ana', apellido: 'Pérez' },
  { id: 3, nombre: 'Ana', apellido: 'Pérez' },
]

it('filtra nombres y apellidos parciales con mayúsculas, tildes y espacios', () => {
  expect(buscarJugadores(jugadores, '  ANG    muN  ').map((j) => j.id)).toEqual([1])
  expect(buscarJugadores(jugadores, 'perez').map((j) => j.id)).toEqual([2, 3])
  expect(buscarJugadores(jugadores, 'Inexistente')).toEqual([])
  expect(buscarJugadores(jugadores, '')).toEqual(jugadores)
})

it('excluye inscripciones activas de cualquier equipo y permite reutilizar las históricas', () => {
  expect(
    jugadoresDisponibles(jugadores, [
      { jugador_id: '2', fecha_salida: null, campeonato_equipo_id: 21 },
      { jugador_id: 3, fecha_salida: '2026-11-01', campeonato_equipo_id: 18 },
    ]).map((j) => j.id),
  ).toEqual([1, 3])
})

it('ofrece lista manual, selección identificable y estado vacío accesibles', () => {
  const seleccionado = renderToStaticMarkup(<SelectorJugador jugadores={jugadores} valor="3" />)
  expect(seleccionado).toContain('role="combobox"')
  expect(seleccionado).toContain('role="listbox"')
  expect(seleccionado).toContain('Ver lista completa')
  expect(seleccionado).toContain('Seleccionado: Ana Pérez · #3')
  expect(seleccionado).toContain('Limpiar selección')
  expect(renderToStaticMarkup(<SelectorJugador jugadores={[]} />)).toContain(
    'No hay jugadores disponibles',
  )
})

it('el selector solo aparece al inscribir y conserva los campos de inscripción', () => {
  const plantilla = { dorsal: 9, posicion: 'Defensa', fecha_ingreso: '2026-11-01' }
  const html = renderToStaticMarkup(<PlantillaForm modo="inscribir" jugadores={jugadores} />)
  for (const texto of [
    'role="combobox"',
    'name="dorsal"',
    'name="posicion"',
    'name="fecha_ingreso"',
  ]) {
    expect(html).toContain(texto)
  }
  expect(renderToStaticMarkup(<PlantillaForm modo="editar" plantilla={plantilla} />)).not.toContain(
    'role="combobox"',
  )
  expect(renderToStaticMarkup(<PlantillaForm modo="salida" plantilla={plantilla} />)).toContain(
    'name="fecha_salida"',
  )
})
