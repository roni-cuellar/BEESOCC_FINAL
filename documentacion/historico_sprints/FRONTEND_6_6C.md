# Sprint 6.6C — Cierre visual del frontend BEESOCC

Fecha: 17 de noviembre de 2026. Rama de trabajo: `develop`.

## 1. Pantallas mejoradas

El árbol de trabajo recibido ya contenía un rediseño amplio sin commit y el informe `REDISENO_VISUAL_6_6C.md`. Se conservaron esos cambios. Esta entrega completa detalles de presentación y vuelve a ejecutar las verificaciones sobre el conjunto actual; no atribuye los cambios previos a esta revisión.

| Pantallas | Estado del cierre |
| --- | --- |
| Login y Registro | Se conserva el acceso con panel deportivo y logos para fondo oscuro y claro. Se añaden estados accesibles de envío, se permite el autocompletado del formulario y se explica el límite de contraseña existente. |
| Mis ligas y Dashboard | Se conserva la portada, tarjetas, resumen de datos, accesos y organización visual del ciclo ya implementados. |
| Equipos y jugadores | Se conservan encabezados ilustrados, controles compartidos, formularios, tarjetas y estados existentes. |
| Partidos y calendario | Se mantienen las mejoras previas de presentación, controles y estados, sin alterar programación ni resultados. |
| Posiciones | Se adopta el encabezado compartido, se explican todas las siglas y el desplazamiento móvil, se identifican las cabeceras de columna y se permite enfocar y desplazar la tabla con teclado. |
| Estadísticas | Se adopta el encabezado compartido conservando la ilustración; la tabla recibe nombre accesible, caption y acceso por teclado. |
| Grupos y eliminación | Se conservan las mejoras previas de encabezados, ilustraciones y tablas, junto con su desplazamiento interno. |
| Catálogo y detalle público | Se conservan branding y encabezados previos. Posiciones y estadísticas públicas reciben también las mejoras de sus páginas compartidas. |
| Crear/editar liga | Se explica que formato organiza la competición y modalidad se configura en el dashboard. No se cambia el formulario enviado. |

## 2. Componentes mejorados

- Se reutiliza `EncabezadoPagina` en posiciones y estadísticas para mantener la misma jerarquía, separación y distribución responsive.
- Se reutilizan `Campo` y `Boton` en acceso; la ayuda de contraseña queda asociada al control por el componente existente.
- Las tablas de posiciones y estadísticas ofrecen regiones enfocables con contorno visible; se conserva cada columna y dato.
- El foco global también cubre `summary` y regiones con `tabindex="0"`.
- Se corrige una regla posterior que sobrescribía el resaltado verde de filas de tabla.
- La preferencia de movimiento reducido desactiva también las animaciones CSS, incluido el spinner heredado.
- Se conservan los ajustes existentes en cards, confirmaciones, modales, sidebar, layout, estados y resumen deportivo.

## 3. Archivos modificados

### Ajustes realizados en esta revisión

1. `frontend/src/pages/LoginPage.jsx`.
2. `frontend/src/pages/RegistroPage.jsx`.
3. `frontend/src/pages/CrearLigaPage.jsx`.
4. `frontend/src/pages/TablaPage.jsx`.
5. `frontend/src/pages/EstadisticasPage.jsx`.
6. `frontend/src/styles/global.css`.
7. `frontend/src/styles/aplicacion.css`.
8. `FRONTEND_6_6C.md` (nuevo).

### Cambios previos conservados respecto a HEAD

Además de las rutas coincidentes de la lista anterior, el árbol recibido ya incluía:

- Componentes: `AccesoLayout.jsx`, `AplicacionLayout.jsx`, `CicloCampeonatoPanel.jsx`, `Confirmacion.jsx`, `EstadoVista.jsx`, `LigaCard.jsx`, `Modal.jsx`, `Sidebar.jsx`, `grupos/DesempatesGrupos.jsx`, `layout/EncabezadoPagina.jsx`, `ui/Boton.jsx`, `ui/Campo.jsx` y `ui/Tarjeta.jsx`, dentro de `frontend/src/components/`.
- Páginas: `ArbitrosPage.jsx`, `CalendarioPage.jsx`, `CampeonatosPublicosPage.jsx`, `DashboardPage.jsx`, `DetallePublicoPage.jsx`, `EliminacionPage.jsx`, `EquiposPage.jsx`, `GruposPage.jsx`, `InicioPage.jsx`, `JugadoresPage.jsx`, `MisArbitrajesPage.jsx`, `MisLigasPage.jsx`, `PartidosArbitrajePage.jsx` y `PartidosPage.jsx`, dentro de `frontend/src/pages/`.
- Nuevos componentes previos: `frontend/src/components/IlustracionModulo.jsx` y `frontend/src/components/ResumenDeportivo.jsx`.
- Utilidad previa: `frontend/src/utils/modalidadCampeonato.js`.
- Pruebas previas: `frontend/tests/unit/ciclo.test.jsx`, `integracion-grafica.test.jsx` y `resumen-deportivo.test.jsx`.
- Recursos previos en `frontend/src/assets/branding/ilustraciones-nuevas/`, `nuevos/` y `optimizadas/`.
- Informes previos: `IDENTIDAD_VISUAL_6_6.md`, `INTEGRACION_GRAFICA_6_6.md`, `INVENTARIO_GRAFICO_6_6.md` y `REDISENO_VISUAL_6_6C.md`.

## 4. Decisiones visuales

- Conservar verde BEESOCC, fondos claros, superficies blancas, bordes suaves e imágenes integradas.
- Reutilizar componentes y estilos existentes; sin nuevas dependencias ni reorganización de arquitectura.
- Mantener tablas completas, con desplazamiento horizontal dentro de su contenedor y explicación visible en posiciones.
- Mantener formato deportivo y modalidad como conceptos distintos: el selector de modalidad existente sigue en el ciclo del campeonato, con Fútbol 5, 7, 11 y personalizada.
- Mantener los límites y validaciones existentes; el registro solo explica el rango que ya exigía.
- No sustituir mensajes específicos de carga de acceso por el texto genérico de guardado del botón compartido.

## 5. Verificaciones ejecutadas y resultado

Comandos desde la raíz del repositorio, sobre el estado final del código:

| Comando | Resultado |
| --- | --- |
| `npm --prefix frontend run test:unit` | 241 pruebas aprobadas, 15 archivos. |
| `npm --prefix frontend test` | 47 pruebas de servicios aprobadas. |
| `npm --prefix frontend run lint` | ESLint y Oxlint aprobados. |
| `npm --prefix frontend run format:check` | Aprobado. |
| `npm --prefix frontend run build` | Aprobado; aviso de bundle JavaScript superior a 500 kB. |
| `git diff --check` | Sin errores de espacios; Git informa conversión LF/CRLF por la configuración local. |

Total: **288 pruebas frontend aprobadas**. Se ejecutaron las suites existentes; no se añadieron tests para estos cambios de presentación. No fue necesario ejecutar pruebas de backend: esta revisión no modifica contratos, servicios ni Laravel.

Revisión de navegador realizada en esta entrega sobre `http://localhost:5173`:

- Login y Registro en escritorio y con viewport de 390 × 844.
- Logos, jerarquía, campos, acción principal y navegación entre ambas pantallas visibles y coherentes.
- Registro móvil: ancho de documento y contenido de 375 px (la barra vertical ocupa el resto del viewport), sin desbordamiento horizontal y con cero imágenes rotas.
- Se restauró el tamaño original del navegador. No se enviaron formularios ni se crearon cuentas.

El informe anterior documenta un recorrido más amplio con laboratorio. Ese recorrido **no se repitió** ni se presenta como comprobación nueva en esta entrega. Las vistas autenticadas, tablas con datos y estados deportivos se verificaron aquí por revisión de código y suites existentes, sin escribir datos para obtener capturas.

## 6. Pendientes y límites

- Pendiente revisión visual final de las vistas autenticadas y tablas con datos después de estos últimos ajustes; no se afirma cobertura visual nueva de todos sus estados.
- Optimización futura del bundle: JavaScript de 509,52 kB sin gzip (149,73 kB con gzip). No bloquea el build; se conserva como advertencia de rendimiento.
- La aceptación estética final por pantalla corresponde al usuario.

Se trabajó en `develop`, sin commit ni push, sin tocar `main`, sin ejecutar migraciones, sin acceder a la base principal y sin cambiar backend, endpoints, contratos API ni lógica deportiva. Los cambios previos sin commit permanecen conservados.
