# Componentes base

Importar desde `components/ui`. No se movieron los componentes existentes para
mantener sus consumidores y las rutas actuales.

| Componente | Uso |
| --- | --- |
| Boton | `variante`: principal, secundario, peligro o claro; `to` para navegación; `cargando`, `disabled`, `type` y eventos nativos |
| Campo | Input por defecto; `as="select"` o `as="textarea"`; `etiqueta`, `error`, `ayuda` y atributos nativos |
| Tarjeta | Contenedor; `as="form"` conserva `onSubmit` y validación nativa; `sinRelleno` para imágenes y tablas |
| Modal | Diálogo nativo existente; `titulo`, `onCerrar`, `bloqueado` |
| Confirmacion | Diálogo existente; espera `onConfirmar`, muestra errores HTTP y bloquea doble confirmación |
| EstadoVista | Carga, error con reintento o vacío con ilustración y acción opcional |
| Aviso | Mensaje accesible de error o éxito y cierre opcional |

`components/layout/EncabezadoPagina` recibe `titulo`, `descripcion` y `acciones`.
Los layouts, navbar, sidebar y header actuales conservan sus ubicaciones.

```jsx
<Campo etiqueta="Nombre" name="nombre" value={nombre}
  onChange={e => setNombre(e.target.value)} required error={errorNombre} />
<Campo as="select" etiqueta="Equipo" value={equipoId}
  onChange={e => setEquipoId(e.target.value)}>
  <option value="">Selecciona un equipo</option>
</Campo>
<Boton type="submit" cargando={guardando}>Guardar</Boton>
```

El botón es `type="button"` por defecto para evitar envíos accidentales.
Con `to` utiliza Link de React Router; no anidar un botón dentro de un enlace.
Modal restaura el foco al disparador al cerrar; Confirmacion lo recupera tras
un error de API. Los formularios pueden pasar una referencia a Tarjeta en React 19.
Campo vincula etiqueta, ayuda y error mediante IDs y atributos ARIA; no inventa
validaciones de negocio. Se mantienen las validaciones del navegador y Laravel.

Tokens Tailwind: `bg-marca`, `bg-marca-oscuro`, `text-oscuro`, `text-gris`,
`border-borde`, `bg-blanco`, `font-titulo` y `font-sans`.

La importación completa de Tailwind incluye Preflight. Se restauran en su capa base
el peso previo de títulos, listas y centrado de diálogos. Las utilidades utilizan
`important` mientras conviven con los estilos antiguos sin capas; así los nuevos
componentes pueden controlar su aspecto sin eliminar las reglas heredadas.
No extender este uso de prioridad a CSS nuevo: al migrar cada módulo, retirar
sus reglas antiguas y evaluar eliminar `important` cuando deje de ser necesario.

Primera adopción: crear/editar campeonato, botones de confirmación, indicador de
carga y tipografía de mensajes. El resto de módulos se migra de forma gradual.
