# BEESOCC FINAL — Sprint 6.6D

Fecha: 17 de noviembre de 2026.

## Dictamen

**LISTO PARA ENTREGA del frontend dentro del alcance visual y de usabilidad auditado.** No se encontraron incidencias visuales o funcionales bloqueantes en los recorridos comprobados. Se corrigieron únicamente defectos observados en navegador; se conserva el estilo deportivo aprobado, los recursos oficiales y los cambios de los sprints anteriores.

Permanece una advertencia no bloqueante de rendimiento: el bundle JavaScript supera 500 kB. Esta auditoría no constituye una nueva certificación de todas las transiciones deportivas ni de todos los navegadores/dispositivos posibles.

## Laboratorio y aislamiento

- Motor: MariaDB 10.4.32, exclusivamente `127.0.0.1:3317`.
- Base ficticia: `beesocc_23c_test_20261116_175928_6f1833ac`.
- Archivos del motor: `C:/Users/admin/AppData/Local/Temp/beesocc_23c_mariadb_20261116_125923_54092b08/data`.
- Se verificaron base activa, cuenta limitada, permisos concedidos solo a esa base, directorios de datos/redo/InnoDB/Aria y ausencia de configuración cacheada.
- API real de laboratorio: `127.0.0.1:8013`, usando el servidor aislado existente, que valida el destino antes de cada petición.
- Vite: puerto 5175, con `VITE_API_PROXY_TARGET=http://127.0.0.1:8013`, sin editar archivos de entorno del proyecto.
- Navegación administrativa: `http://beesocc23c-admin.localhost:5175`.
- Consulta pública sin sesión: `http://beesocc66d-publico.localhost:5175`.

No se accedió a `beesocc_v2`, al motor principal ni a sus archivos. No se ejecutaron instaladores, semillas, migraciones o restauraciones de base. Se reutilizó el laboratorio ficticio existente. Las credenciales temporales permanecieron fuera del repositorio y de las capturas.

## Pantallas verificadas en navegador

Escritorio **1440 × 1000**, móvil **390 × 844**, en Brave/Chromium. Las capturas corresponden al área visible, con distintas posiciones de desplazamiento según el módulo.

| Área | Verificación realizada en escritorio y móvil |
| --- | --- |
| Login y Registro | Logos sobre fondos claro/oscuro, proporciones, campos y acciones. Error real de credenciales ficticias, carga con envío bloqueado y contraseñas diferentes con mensaje asociado. No se creó una cuenta. |
| Mis ligas | Tarjetas, nombres, temporada, formato, estado, publicación y acciones. Apertura/cancelación de confirmación móvil. |
| Dashboard | Datos reales del laboratorio: 3 equipos, 3 jugadores, 3 partidos y 1 árbitro. También se revisó planificación con cifras cero y próximos encuentros vacíos. |
| Crear/editar liga | Campos, ayudas, formato y botones; creación y edición reales de una liga ficticia, recuperación de sus datos y corrección de alineación de controles. |
| Modalidad | Selección, guardado y recuperación tras recargar de Fútbol 5, Fútbol 7, Fútbol 11 y personalizada. El mínimo de jugadores permaneció independiente. |
| Equipos | Inscritos, catálogo disponible, estados activo/retirado, acciones y modal de registro. Se canceló el formulario. |
| Jugadores y plantillas | Catálogo y tabla de plantilla, select de equipo, dorsal, posición, fechas, estado y acciones. Modal de registro inspeccionado sin crear jugadores. |
| Árbitros | Asignación vigente, búsqueda por correo, motivo, acciones deshabilitadas e historial. No se cambiaron autorizaciones. |
| Partidos | Tarjetas con resultados oficiales/provisionales, jornadas, filtros y estado vacío al seleccionar cancelados. |
| Calendario | Jornadas, descansos y configuración desplegable; vueltas bloqueadas por partidos existentes, selects y periodos. No se generó calendario ni se guardaron reglas. |
| Acta | Historial real de eventos ficticios, marcador deportivo conservado, resultado oficial administrativo, expedientes y auditoría. Sin registrar goles ni resolver expedientes. |
| Posiciones | Diez columnas y tres equipos conservados; leyenda, contenedor desplazable y foco visible. Se comprobó desplazamiento horizontal por flechas del teclado. |
| Estadísticas | Goleadores con datos, cambio a equipos y sus seis columnas, acciones y categorías. |
| Grupos | Dos grupos con diez columnas por tabla, clasificados oficiales y cambio entre vista oficial/provisional. Navegación entre posiciones, desempates y eliminación. |
| Desempates | Panel, explicación y estado real «Sin empates pendientes de resolución». No se inventaron empates ni se generaron partidos adicionales. |
| Eliminación | Dos rondas, llaves resueltas/pendiente, resultados oficiales/no oficiales, filtro por ronda y enlaces a actas. También se abrió la eliminación posterior a grupos. |
| Público | Catálogo, detalle de liga, calendario/equipos, posiciones, estadísticas, cuadro de eliminación directa y grupos, sin sesión. Error real de campeonato privado y botón de reintento. |

La navegación lateral móvil abre, cierra con Escape y devuelve el acceso al contenido. Las tablas, pestañas y cuadros desplazan su propio contenido; no se ocultaron columnas para hacerlas caber. Las mediciones registradas no detectaron desbordamientos horizontales del documento ni imágenes rotas. La revisión visual no detectó recortes que impidan usar las acciones comprobadas.

## Correcciones confirmadas

1. **Temporada estirada por la ayuda de Formato:** se alinearon al inicio las columnas de Crear/editar liga. Se verificó después en navegador: temporada de 50 px y select de 48 px, sin el estiramiento anterior.
2. **Dos títulos principales en páginas públicas anidadas:** el encabezado compartido admite `tituloAs`, manteniendo `h1` por defecto. Posiciones y estadísticas públicas usan `h2` dentro del detalle, que conserva un único `h1`. Verificado en el DOM.
3. **Texto técnico en el cuadro privado:** se sustituyó «confirmados por Laravel» por «de la competición», conservando la instrucción para abrir el acta.
4. **Grupos con jerarquía y selector comprimidos:** separación y tamaño explícito del título de fase pública y sus calendarios; selector de posiciones apilado en móvil y con ancho suficiente para leer la opción elegida. Verificado tanto en grupos privados como públicos.

No se reorganizaron rutas, servicios, stores o arquitectura. No se modificaron backend, contratos API, reglas deportivas ni dependencias.

## Archivos de esta revisión

Ocho archivos de frontend:

- `frontend/src/pages/CrearLigaPage.jsx`.
- `frontend/src/components/layout/EncabezadoPagina.jsx`.
- `frontend/src/pages/TablaPage.jsx`.
- `frontend/src/pages/EstadisticasPage.jsx`.
- `frontend/src/components/CuadroEliminacion.jsx`.
- `frontend/src/components/grupos/TablasGrupos.jsx`.
- `frontend/src/styles/grupos.css`.
- `frontend/src/pages/DeportePublicoPage.jsx`.

Nuevo informe: `CIERRE_FRONTEND_6_6D.md`.

El inventario inicial contiene 70 archivos previamente pendientes. No se eliminó ninguno. Solo cuatro de esas rutas recibieron ajustes incrementales: CrearLigaPage, EncabezadoPagina, TablaPage y EstadisticasPage. Las demás rutas del inventario conservan su SHA-256 inicial. Los otros cuatro archivos de frontend listados estaban limpios al empezar esta revisión. Los informes y recursos previos se conservaron.

## Datos ficticios utilizados

- Se creó el campeonato **9**, «Laboratorio 6.6D — revisión visual», se editó su descripción y se guardaron los requisitos de modalidad. Queda privado y en planificación, con modalidad personalizada «Fútbol de laboratorio 6.6D» y mínimo de 5 jugadores.
- Se publicó temporalmente el campeonato ficticio **7** para inspeccionar grupos públicos y se volvió a ocultar mediante la interfaz. La lectura final confirma `publicado=0`.
- Los campeonatos 5 y 6 siguen publicados; 7 y 8 siguen privados. Los cuatro continúan en curso.
- No se iniciaron/finalizaron campeonatos ni se alteraron equipos, jugadores, asignaciones, resultados, clasificaciones, actas o calendarios deportivos.

Las únicas escrituras funcionales de la revisión fueron autenticación, creación/edición de la liga ficticia, requisitos de esa liga y el ciclo temporal de publicación indicado. Se conserva la liga de auditoría como evidencia reproducible, sin eliminar trabajo previo.

## Pruebas sobre el código final

| Comando desde la raíz | Resultado |
| --- | --- |
| `npm --prefix frontend run test:unit` | **241 aprobadas**, 15 archivos. |
| `npm --prefix frontend test` | **47 aprobadas**, cero fallos. |
| `npm --prefix frontend run lint` | ESLint y Oxlint aprobados. |
| `npm --prefix frontend run format:check` | Prettier aprobado. |
| `npm --prefix frontend run build` | Aprobado, 192 módulos. |
| `git -c core.safecrlf=false diff --check` | Aprobado. |

**Total: 288 pruebas frontend aprobadas.** La primera comprobación de formato detectó el encabezado editado; se formateó y la ejecución final completa pasó. El build final genera JavaScript de **509,79 kB**, **149,80 kB gzip**, y CSS de **77,60 kB**, **15,79 kB gzip**. El aviso del bundle no impide compilar.

No se añadieron tests que reproduzcan los detalles de CSS. Los recorridos de navegador comprobaron los ajustes y la persistencia de modalidad con Laravel/Sanctum reales. Las pruebas unitarias y de servicios existentes complementan esa revisión. No se ejecutaron suites de backend ni scripts que pudieran consultar la base principal.

## Capturas y evidencia local

Carpeta exacta de revisión:

`C:/Users/admin/.codex/visualizations/2026/11/17/01a1ea31-c1eb-7e21-a630-a0548559d70c/sprint66D`

**63 capturas JPEG** en su subcarpeta `capturas`. Índice con las rutas absolutas de todas las imágenes:

[CAPTURAS_6_6D.md](/C:/Users/admin/.codex/visualizations/2026/11/17/01a1ea31-c1eb-7e21-a630-a0548559d70c/sprint66D/CAPTURAS_6_6D.md).

Ejemplos representativos:

- [Dashboard escritorio](/C:/Users/admin/.codex/visualizations/2026/11/17/01a1ea31-c1eb-7e21-a630-a0548559d70c/sprint66D/capturas/09-dashboard-escritorio.jpg).
- [Dashboard móvil](/C:/Users/admin/.codex/visualizations/2026/11/17/01a1ea31-c1eb-7e21-a630-a0548559d70c/sprint66D/capturas/09-dashboard-movil.jpg).
- [Modalidad móvil](/C:/Users/admin/.codex/visualizations/2026/11/17/01a1ea31-c1eb-7e21-a630-a0548559d70c/sprint66D/capturas/08-modalidad-movil.jpg).
- [Posiciones escritorio](/C:/Users/admin/.codex/visualizations/2026/11/17/01a1ea31-c1eb-7e21-a630-a0548559d70c/sprint66D/capturas/21-posiciones-escritorio.jpg).
- [Posiciones móvil](/C:/Users/admin/.codex/visualizations/2026/11/17/01a1ea31-c1eb-7e21-a630-a0548559d70c/sprint66D/capturas/21-posiciones-movil.jpg).
- [Grupos públicos escritorio](/C:/Users/admin/.codex/visualizations/2026/11/17/01a1ea31-c1eb-7e21-a630-a0548559d70c/sprint66D/capturas/32-grupos-publicos-escritorio.jpg).
- [Grupos públicos móvil](/C:/Users/admin/.codex/visualizations/2026/11/17/01a1ea31-c1eb-7e21-a630-a0548559d70c/sprint66D/capturas/32-grupos-publicos-movil.jpg).

En la misma carpeta están `capturas.json` (rutas, tamaño y SHA-256), `mediciones.json` (anchos del documento/tablas e imágenes), `modalidades.json` (cuatro recuperaciones), `laboratorio-verificado.json` (aislamiento y estado final) y `estado-inicial.json` (huellas del trabajo previo). Las capturas secundarias de ronda y cuadro público reflejan su momento de revisión; el índice distingue la captura anterior del formulario. Las capturas principales de grupos y eliminación se actualizaron tras corregirlos.

## Límites y pendientes no bloqueantes

- Optimización futura del bundle JavaScript y recursos gráficos de mayor tamaño; se evitó cambiar la arquitectura en este cierre.
- Se verificó el estado sin desempates pendientes. No se reprodujo un nuevo torneo con empates activos, porque no era necesario alterar el estado deportivo para esta auditoría visual; las suites existentes de grupos y eliminación pasaron.
- No se repitieron altas/bajas de equipos y jugadores ni transiciones de actas/resultados; se inspeccionaron sus formularios, controles y datos existentes. Tampoco se afirma cobertura de todos los tamaños, navegadores o estados posibles.
- Sin pendientes visuales bloqueantes observados en el alcance recorrido.

## Estado Git

Rama **`develop`**. HEAD **`522b86fead4b1590aaa0179516854fd4264656b2`**, igual al inicial. Árbol con cambios previos conservados y los ajustes de esta revisión, sin staging, commits ni push. No se cambió de rama ni se tocó `main`.
