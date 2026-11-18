# Sprint 6.6B — Integración gráfica

Fecha: 16 de noviembre de 2026. Rama `develop`; HEAD de referencia `522b86fead4b1590aaa0179516854fd4264656b2`.

## Alcance y marca oficial

Se integraron las once ilustraciones aportadas, sin modificar Laravel, servicios, permisos, reglas deportivas, CSS ni datos deportivos. Se conservaron los ocho PNG originales y los catorce archivos nuevos recibidos. No se instalaron dependencias ni se crearon commits.

**Los tres logos nuevos no sustituyen la marca oficial.** La inspección visual detectó diferencias que requieren revisión: el logo normal presenta un borde gris alrededor del eslogan; el logo claro cambia las relaciones de tamaño y composición, y el isotipo cambia su proporción visible. No son únicamente diferencias de margen transparente o recoloración.

Como señal auxiliar, se midió el rectángulo visible con alfa ≥ 128, sin modificar archivos:

| Recurso | Proporción visible ancho/alto |
| --- | ---: |
| Original `logo.png` | 3,7827 |
| Nuevo `logo-normal.png` | 3,8333 |
| Nuevo `logo-fondo-blanco.png` | 4,1816 |
| Original `logo_solo.png` | 0,8174 |
| Nuevo `isotipo.png` | 0,7797 |

Estas medidas no certifican fidelidad geométrica: respaldan la diferencia observada. Navbar, acceso, footer y sidebar conservan sus logos originales. Queda pendiente aprobar o corregir los derivados desde el maestro oficial antes de sustituirlos. No se modificó el favicon.

## Inventario recibido

Rutas relativas a `frontend/src/assets/branding/`. Todos son PNG RGBA con transparencia; no se sobrescribió ninguno.

| Archivo | Carpeta | Dimensiones | Bytes |
| --- | --- | --- | ---: |
| logo-normal.png | nuevos | 2172 × 724 | 652871 |
| logo-fondo-blanco.png | nuevos | 2172 × 724 | 450983 |
| isotipo.png | nuevos | 1254 × 1254 | 541404 |
| inicio.png | ilustraciones-nuevas | 1448 × 1086 | 1259324 |
| campeonatos.png | ilustraciones-nuevas | 1254 × 1254 | 1024391 |
| equipos.png | ilustraciones-nuevas | 1254 × 1254 | 1053057 |
| partidos.png | ilustraciones-nuevas | 1254 × 1254 | 804121 |
| estadisticas.png | ilustraciones-nuevas | 1254 × 1254 | 919249 |
| arbitraje-actas.png | ilustraciones-nuevas | 1254 × 1254 | 1482576 |
| fase-grupos.png | ilustraciones-nuevas | 1254 × 1254 | 1667042 |
| eliminacion-directa.png | ilustraciones-nuevas | 1254 × 1254 | 1139717 |
| penales-desempates.png | ilustraciones-nuevas | 1254 × 1254 | 1528147 |
| plantillas.png | ilustraciones-nuevas | 1254 × 1254 | 1697127 |
| consulta-publica.png | ilustraciones-nuevas | 1254 × 1254 | 1706096 |

## Exportaciones e integración

Se crearon derivados en `frontend/src/assets/branding/optimizadas/`. Los cuadrados se redujeron con interpolación bicúbica de alta calidad y transparencia, utilizando System.Drawing disponible en Windows. Se conservó el lienzo completo: sin recortes, recoloración ni generación artificial. La reducción cambia los píxeles por remuestreo; no se afirma identidad binaria con el original.

| Derivado PNG | Dimensiones | Bytes | Uso |
| --- | --- | ---: | --- |
| inicio.png | 1448 × 1086 | 1259324 | Hero de inicio |
| campeonatos.png | 384 × 384 | 179930 | Tarjetas, estados vacíos, resumen de liga |
| equipos.png | 512 × 512 | 287674 | Inicio, acceso, equipos y jugadores |
| partidos.png | 512 × 512 | 225226 | Inicio, partidos y calendario |
| estadisticas.png | 512 × 512 | 251694 | Inicio y estadísticas |
| arbitraje-actas.png | 256 × 256 | 114010 | Árbitros, mis arbitrajes y partidos arbitrales |
| fase-grupos.png | 256 × 256 | 121095 | Grupos y resumen de ese formato |
| eliminacion-directa.png | 256 × 256 | 83069 | Cuadro y resumen de eliminación |
| penales-desempates.png | 256 × 256 | 117595 | Sección de expedientes de desempate |
| plantillas.png | 256 × 256 | 118349 | Pestaña de plantilla de jugadores |
| consulta-publica.png | 256 × 256 | 113737 | Catálogo y detalle público |

El hero conserva los bytes del archivo recibido: una reducción ensayada aumentaba el peso. No se mantuvo esa exportación más pesada. Las otras diez imágenes reducen sustancialmente el peso para sus usos pequeños.

`IlustracionModulo.jsx` reúne los recursos de encabezados. `EncabezadoPagina.jsx` acepta una ilustración opcional, manteniendo acciones y contenido. Los encabezados especializados reutilizan el mismo componente. Tamaño visible: 64 px en móvil y 96 px desde `sm`; `object-contain`, dimensiones intrínsecas y carga diferida. Las imágenes decorativas llevan `alt=""` junto al título visible. Las ilustraciones informativas existentes conservan sus textos alternativos; el hero mantiene carga prioritaria.

No se añadieron ilustraciones dentro de cada llave, fila de tabla o tarjeta de jugador. Los marcadores dibujados en las ilustraciones son decorativos y no representan resultados del campeonato.

## Verificación

- Vitest: **231 pruebas aprobadas**, incluidas 13 nuevas de integración gráfica.
- Pruebas Node de servicios: **47 aprobadas**. Total frontend: **278**.
- ESLint y Oxlint aprobados; Prettier y `git diff --check` aprobados.
- Build aprobado. Advertencia de paquete JavaScript de 506,57 kB sin comprimir; no se amplió el alcance para reorganizar toda la carga de rutas.
- Sin cambios backend: no se repitieron suites deportivas ni carreras históricas.

Se reutilizó el laboratorio ficticio existente con React, Laravel y MariaDB reales, sin adaptador simulado. Su verificador confirmó servidor `127.0.0.1:3317`, MariaDB 10.4.32, cuenta limitada, cuatro rutas físicas aisladas y API conectada al mismo laboratorio. No se accedió a `beesocc_v2` ni se ejecutaron migraciones.

Revisión focalizada en inicio, catálogo, dashboard de liga, equipos, partidos, estadísticas, grupos y eliminación directa, a 1440 × 1000 y 390 × 844. Se comprobaron encabezados, proporciones, imágenes cargadas y ancho del documento: sin imágenes rotas detectadas ni desplazamiento horizontal global. Tablas y cuadros conservan su desplazamiento interno previsto. Se revisaron también las pestañas de desempates y eliminación de grupos. Esto verifica presentación con datos reales existentes; no repite las operaciones deportivas ni certifica todos los estados posibles.

Las capturas están fuera del repositorio, en la carpeta externa `revision66/integracion66B` del espacio de visualizaciones de esta sesión. Incluyen inicio, catálogo, dashboard, equipos, partidos, estadísticas, grupos y eliminación, con variantes `-escritorio.jpg` y `-movil.jpg`; además, las pestañas de desempates y cuadro de grupos. El JSON externo registra las comprobaciones del ancho y carga; no contiene credenciales ni cookies. Algunas capturas iniciales mostraron carga transitoria y se renovaron al verificar la vista final.

## Conservación y pendientes

Los SHA-256 de los ocho originales coinciden con el inventario previo; `git diff` no incluye modificaciones de esos archivos. Se preservaron también `IDENTIDAD_VISUAL_6_6.md`, `INVENTARIO_GRAFICO_6_6.md` y los recursos entregados por el usuario.

Cambios de esta integración: 19 componentes/páginas existentes, un componente reutilizable, un archivo de pruebas, once derivados PNG y este informe. Los archivos originales nuevos del usuario siguen sin versionar, igual que los dos informes previos. No hay commits ni push de este sprint.

Pendientes: revisión de fidelidad de los tres logos nuevos, aceptación visual del usuario y rediseño posterior por pantalla. El hero de 1,26 MB y la advertencia de tamaño de JavaScript quedan identificados para optimización futura, sin cambiar ahora la identidad ni la arquitectura.

## Ajuste posterior autorizado — logo sobre fondos claros

El usuario aprobó expresamente utilizar `nuevos/logo-fondo-blanco.png` pese a las diferencias registradas en la comparación inicial. Esta autorización sustituye el bloqueo anterior exclusivamente para esa variante. `logo-normal.png` e `isotipo.png` nuevos continúan sin integrarse.

- `AccesoLayout.jsx`: variante clara en las tarjetas blancas de login y registro; original conservado en el panel oscuro.
- `AplicacionLayout.jsx`: variante clara en la cabecera blanca compartida por mis ligas y mis arbitrajes.
- Navbar, footer y sidebar oscuros conservan `logo.png`. No se encontraron otras ubicaciones del logo completo en componentes de React.
- `global.css`: marco específico sin recorte para la variante clara, ancho adaptable de 220–264 px y hasta 288 px en acceso, limitado por el contenedor. Imagen con altura automática y proporción nativa 3:1; no se alteraron sus píxeles.
- Verificados login y registro en escritorio y móvil; acceso móvil a 390 px sin desbordamiento. Capturas externas `login-logo-claro-*` y `registro-logo-claro-*`.
- Tras el ajuste: 231 pruebas Vitest, lint y build aprobados; diferencia Git sin errores de espacio. Las 47 pruebas de servicios aprobadas anteriormente no se repitieron al no cambiar servicios. Persiste la advertencia de tamaño del paquete JavaScript.
- No se modificaron originales, backend, bases de datos ni autenticación; sin commits ni push.
