# Sprint 6.6C — Rediseño y pulido visual

## Estado y protección de avances

Rama `develop`, HEAD `522b86fead4b1590aaa0179516854fd4264656b2`. Antes de editar se respaldaron **54 rutas pendientes**, con copia externa e inventario SHA-256 comparado archivo por archivo. El respaldo conserva la integración gráfica, la variante clara autorizada y la corrección del selector de modalidad. No se revirtieron esos avances, ni se preparó staging, commit o push.

El respaldo está en la carpeta externa de visualizaciones de esta sesión, `sprint66C/preexistentes`. Las capturas están separadas en `sprint66C/capturas`. Ninguna evidencia de sesión, cookie, credencial, SQL o base local se incorpora al repositorio.

## Mejoras realizadas

| Área | Resultado |
| --- | --- |
| Botones | Altura mínima de 48 px en el componente compartido, variantes consistentes, sombras discretas, hover, foco y bloqueo conservados. |
| Formularios | Etiquetas separadas, bordes suaves, texto de controles a 16 px, foco visible, estados de error y deshabilitado. Se mantienen campos, tipos, límites, obligatoriedad y eventos originales. |
| Tablas | Cabeceras verde claro, separación de filas, cifras tabulares, filas alternas y resaltado al pasar o enfocar controles. Se conserva toda la información y el desplazamiento interno. |
| Modales | Cabecera y acciones separadas, esquinas y espaciado coherentes, botones adaptables en móvil y desplazamiento vertical contenido. Se conservan diálogo nativo, bloqueo durante operaciones y recuperación del foco. |
| Tarjetas y encabezados | Bordes y sombras suaves, jerarquía tipográfica común, encabezados de módulo separados y tarjetas de liga más compactas. |
| Login y Registro | Superficie de acceso con sombra suave, panel deportivo y controles compartidos. La variante clara sigue en la tarjeta blanca y el original en el panel oscuro. No cambia autenticación. |
| Mis ligas | Cabecera propia, acento de marca, tarjetas uniformes, acciones y estados conservados. |
| Dashboard | Portada verde oscuro con nombre real, temporada y estado; cuatro cifras consultadas; ciclo organizado; accesos rápidos; auditoría secundaria desplegable. |
| Sidebar | Opción activa con acento verde y borde sutil; se conserva apertura móvil, cierre con Escape y gestión de foco. |

Los mismos componentes y reglas CSS se aplican a equipos, jugadores, plantillas, árbitros, calendario, partidos, actas, posiciones, estadísticas, grupos, desempates, eliminación y consulta pública. No se añadieron ilustraciones redundantes ni recursos gráficos nuevos.

## Cifras y contratos del dashboard

Equipos, jugadores y partidos conservan sus consultas y criterios anteriores. Se añade únicamente la lectura ya existente `arbitroService.vigente(id)`, correspondiente a `GET /api/campeonatos/{id}/arbitro`. El contrato devuelve una asignación vigente o `null`; por eso la cifra es 1 o 0 cuando la consulta tiene éxito. Un fallo se muestra como no disponible, nunca como cero inventado.

`ResumenDeportivo.jsx` presenta las cifras recibidas y diferencia carga, ausencia de respuesta y cero real. En el laboratorio se observaron **3 equipos, 3 jugadores, 3 partidos y 1 árbitro asignado**.

Se conserva el selector de modalidad corregido: Fútbol 5, Fútbol 7, Fútbol 11 y Personalizada. Laravel admite texto libre de hasta 50 caracteres; los valores existentes no se normalizan ni reemplazan automáticamente. El mínimo de jugadores sigue siendo independiente. No se modificaron el envío de requisitos, inicio, finalización, autorizaciones ni auditoría.

## Comprobaciones realizadas

- **241 pruebas Vitest y 47 pruebas Node aprobadas: 288 frontend en total.**
- Tres regresiones nuevas para cifras reales, ceros, carga y fallos parciales del resumen.
- Las pruebas existentes de formularios, navegación, autenticación, modalidad, imágenes y los tres formatos siguen aprobando.
- Prettier, ESLint y Oxlint aprobados; `git diff --check` sin incidencias.
- Build aprobado. Permanece la advertencia de tamaño de JavaScript: aproximadamente 509 kB sin comprimir. No se cambió la arquitectura de carga de rutas para ocultarla.

Comandos desde la raíz del repositorio:

```powershell
npm --prefix frontend run test:unit
npm --prefix frontend test
npm --prefix frontend run format:check
npm --prefix frontend run lint
npm --prefix frontend run build
git diff --check
```

## Navegador real y responsive

Se reutilizó el laboratorio ficticio existente. El verificador confirmó MariaDB 10.4.32 en `127.0.0.1:3317`, cuenta limitada, rutas físicas independientes y API conectada al mismo laboratorio. React utiliza Laravel y Sanctum reales; las capturas no utilizan un adaptador simulado.

Se revisaron escritorio a **1440 × 1000** y móvil a **390 × 844**:

- Dashboard con cifras reales, Mis ligas, Login y Registro.
- Formulario de creación de liga, confirmación de publicación y sidebar móvil.
- Tabla de posiciones: diez columnas conservadas, con tabla más ancha que el área móvil pero documento sin desbordamiento horizontal.
- Equipos, jugadores y plantillas, arbitraje, calendario y configuración, partidos, acta, estadísticas, grupos, desempates y eliminación directa.
- Catálogo y detalle públicos de un campeonato ficticio publicado.

Se abrieron y cancelaron confirmaciones; se comprobaron navegación y cierre del sidebar con Escape. No se guardaron formularios ni se confirmaron operaciones deportivas o de publicación. Se conservaron los datos ficticios existentes. Las pruebas de servicios y componentes verifican los contratos de envío; este recorrido visual no sustituye una nueva prueba integral de escritura.

Los campeonatos disponibles estaban en curso: el formulario del ciclo en planificación se cubre mediante las pruebas de modalidad y contratos, sin crear campeonatos ni modificar estados para obtener una captura. La configuración del calendario se revisó tras completar sus consultas; algunos estados transitorios de carga del laboratorio no constituyen fallos deportivos.

Sin imágenes rotas detectadas ni desbordamiento horizontal global en las vistas comprobadas. Tablas, pestañas y cuadros conservan sus desplazamientos internos. Las capturas son del área visible; no representan todas las filas ni todos los estados posibles.

## Archivos de este ajuste

Modificados respecto al respaldo inicial de 6.6C:

- `frontend/src/styles/aplicacion.css`.
- `frontend/src/components/ui/Boton.jsx`, `Campo.jsx` y `Tarjeta.jsx`.
- `frontend/src/components/Modal.jsx` y `Confirmacion.jsx`.
- `frontend/src/components/AccesoLayout.jsx`, `LigaCard.jsx` y `Sidebar.jsx`.
- `frontend/src/components/layout/EncabezadoPagina.jsx`.
- `frontend/src/components/CicloCampeonatoPanel.jsx`.
- `frontend/src/pages/MisLigasPage.jsx` y `DashboardPage.jsx`.

Nuevos: `frontend/src/components/ResumenDeportivo.jsx`, `frontend/tests/unit/resumen-deportivo.test.jsx` y este informe. **16 rutas propias de 6.6C**; los demás pendientes pertenecen a los ajustes anteriores conservados.

## Límites y pendientes

No se modificaron Laravel, rutas/API, servicios, stores, autenticación, reglas deportivas, dependencias, archivos originales de marca ni bases principales. No se ejecutaron migraciones. Los logos nuevos no aprobados siguen sin sustituir los originales; la variante clara expresamente autorizada se conserva.

Pendientes: aceptación visual del usuario por pantalla y eventual optimización del paquete JavaScript. No se han realizado commits ni push. La comparación incremental con el respaldo permite revisar este pulido sin confundirlo con los cambios anteriores.
