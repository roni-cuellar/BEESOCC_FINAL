# Sprint 6.6 — Inventario gráfico previo al rediseño

Fecha: 16 de noviembre de 2026. Rama `develop`, HEAD `522b86f`. Revisión local de archivos y referencias, sin modificar imágenes, JSX, CSS, rutas o bases de datos.

## Recursos existentes

Hay **ocho imágenes fuente**, todas PNG con transparencia: tres variantes de marca y cinco ilustraciones. Ocupan **6.980.936 bytes, aproximadamente 6,66 MiB**, en conjunto; esto no significa que una visita descargue todas. Los ocho archivos tienen SHA-256 diferentes: no hay duplicados exactos entre las fuentes. `frontend/public` está vacío. Se excluyeron `node_modules` y `dist` del conteo de originales; los archivos con hash de `dist/assets` son productos de compilación.

Peso expresado en KiB (1 KiB = 1.024 bytes). «Mejorar» significa evaluar una futura exportación autorizada, conservando el archivo original; no se creó ninguna versión nueva.

| Archivo, formato y peso | Ruta | Dimensiones | Uso actual | Conservar / Mejorar / Falta |
| --- | --- | --- | --- | --- |
| `logo.png` · PNG · 316,75 KiB (324.357 bytes) | `frontend/src/assets/branding/logo.png` | 1448 × 1086 px | Marca completa: isotipo, nombre y lema. Importada por `Navbar`, `Footer`, `Sidebar`, `AplicacionLayout` y `AccesoLayout` —login y registro—. | **Conservar** como original actual. Revisar con el usuario la fuente maestra y exportaciones para web; no redibujar. |
| `logo_solo.png` · PNG · 562,89 KiB (576.396 bytes) | `frontend/src/assets/branding/logo_solo.png` | 1254 × 1254 px | Isotipo aislado. Favicon declarado en `frontend/index.html`; no se usa como imagen de contenido en React. | **Conservar**. **Mejorar**, previa aprobación, la exportación del favicon por su peso y tamaño excesivos para ese uso. |
| `nombre_logo.png` · PNG · 339,61 KiB (347.757 bytes) | `frontend/src/assets/branding/nombre_logo.png` | 1448 × 1086 px | Nombre BEESOCC y lema, sin isotipo. No se encontraron referencias en el código de la aplicación ni en el HTML de entrada. | **Conservar**, aunque esté sin uso; variante de marca, no archivo duplicado ni candidato automático a eliminación. |
| `ayuda.png` · PNG · 1368,15 KiB (1.400.982 bytes) | `frontend/src/assets/branding/ayuda.png` | 1448 × 1086 px | `InicioPage`: ilustración principal con tablero deportivo, balón y trofeo. Incluye textos, rostros y escudos dibujados dentro de la propia imagen. | **Conservar**. **Mejorar** exportaciones web/responsive si se aprueba; no es una captura del dashboard real. |
| `ayuda_2.png` · PNG · 1094,46 KiB (1.120.730 bytes) | `frontend/src/assets/branding/ayuda_2.png` | 1254 × 1254 px | Trofeo y organización del torneo. `InicioPage`, `LigaCard` —catálogo público y listado privado— y `EstadoVista` —estados vacíos, incluido dashboard cuando corresponde—. | **Conservar**. Reutilización intencional del mismo recurso; **mejorar** exportaciones para tarjetas pequeñas si se aprueba. |
| `ayuda_3.png` · PNG · 1154,12 KiB (1.181.815 bytes) | `frontend/src/assets/branding/ayuda_3.png` | 1254 × 1254 px | Equipos y jugadores ilustrados. `InicioPage` y panel lateral de `AccesoLayout`, compartido por login y registro. Ese panel se oculta en pantallas pequeñas. | **Conservar**. **Mejorar** peso de futuras exportaciones si se aprueba. El escudo y las figuras incrustadas no son archivos independientes de equipo o avatar. |
| `ayuda_4.png` · PNG · 931,70 KiB (954.059 bytes) | `frontend/src/assets/branding/ayuda_4.png` | 1254 × 1254 px | `InicioPage`: calendario, enfrentamiento y silbato. | **Conservar**; evaluar exportación web autorizada. |
| `ayuda_5.png` · PNG · 1049,65 KiB (1.074.840 bytes) | `frontend/src/assets/branding/ayuda_5.png` | 1254 × 1254 px | `InicioPage`: estadísticas y tabla ilustradas. | **Conservar**; evaluar exportación web autorizada. |

### Recursos definidos en código, no como archivos de imagen

| Archivo / recurso | Ruta | Dimensiones | Uso actual | Conservar / Mejorar / Falta |
| --- | --- | --- | --- | --- |
| `Icono.jsx` · SVG inline; archivo completo de 1.392 bytes | `frontend/src/components/Icono.jsx` | `viewBox` 24 × 24; salida predeterminada 20 × 20 px, escalable | Trece trazos de iconos, coloreados con `currentColor`; navegación, botones, tarjetas y avisos. No hay un paquete de iconos externo ni trece archivos SVG separados. | **Conservar** y usar como sistema existente antes de proponer iconos nuevos. |
| Iniciales de equipo/jugador · texto y CSS | `frontend/src/components/ImagenEntidad.jsx`; `frontend/src/styles/aplicacion.css` | 48 × 48 px por defecto | Hasta dos caracteres del nombre; alternativa cuando falta una imagen o su carga falla. No existe un PNG de avatar o escudo predeterminado. | **Conservar**. Un escudo o avatar genérico nuevo es opcional y requiere decisión previa. |
| Fondos de las vistas actuales · colores y clases Tailwind | `frontend/src/styles/tailwind.css`, JSX de páginas/layouts y hojas globales | Responsivas; sin resolución raster ni peso de imagen independiente | Colores de marca, paneles claros, tarjetas, bordes y superficies. No se encontraron fondos fotográficos ni `background-image`/`url()` de imágenes en el CSS fuente. | **Conservar** hasta decidir el rediseño. No hace falta crear un fondo gráfico para que funcionen las vistas. |
| Degradados CSS de compatibilidad | `frontend/src/styles/inicio.css` (`.hero-visual`); `frontend/src/styles/aplicacion.css` (`.liga-card-visual`, `.acceso-layout`) | Responsivos; sin archivo raster | Gradientes lineales y radiales presentes en CSS. No se encontraron esas tres clases en el JSX actual: no deben confundirse con fondos gráficos actualmente aplicados por los nuevos layouts Tailwind. | **Conservar**, sin borrar reglas durante este inventario. |

Iconos y consumidores identificados:

| Nombre del trazo | Uso actual |
| --- | --- |
| `resumen` | Sidebar; también alternativa si se solicita un nombre desconocido. |
| `equipos` | Sidebar y dashboard. |
| `jugadores` | Sidebar —jugadores y árbitros— y dashboard. |
| `partidos` | Sidebar, dashboard, `LigaCard` e inicio. |
| `calendario` | Sidebar, `HeaderDashboard` y dashboard. |
| `tabla` | Sidebar, incluidos los accesos de grupos o cuadro según formato. |
| `estadisticas` | Sidebar. |
| `menu` | Navbar y cabecera del dashboard móvil. |
| `cerrar` | Navbar, Sidebar, `Modal` y `Aviso`. |
| `mas` | `MisLigasPage` y `CalendarioPage`. |
| `puntos` | Menú de acciones de `EquipoCard`. |
| `exito` / `alerta` | `Aviso`, según el estado del mensaje. |

## Imágenes por pantalla

| Pantalla | Imágenes y componentes responsables |
| --- | --- |
| Inicio `/` | `InicioPage.jsx`: `ayuda.png` y `ayuda_2.png` a `ayuda_5.png`. `Navbar` y `Footer` añaden `logo.png`. |
| Navbar | `Navbar.jsx`: `logo.png` e iconos inline de menú/cierre. |
| Login `/login` | `LoginPage.jsx` usa `AccesoLayout.jsx`: `logo.png` y `ayuda_3.png` en el panel lateral de escritorio; logo adicional dentro del formulario. |
| Registro `/registro` | `RegistroPage.jsx`, con los mismos recursos de `AccesoLayout`. No hay una ilustración separada para registro. |
| Footer | `Footer.jsx`: `logo.png`; sin fotografía de fondo. |
| Catálogo `/campeonatos` | `CampeonatosPublicosPage.jsx` utiliza `LigaCard.jsx`: `ayuda_2.png` compartida por las tarjetas, no una portada propia de cada campeonato. También Navbar y Footer. |
| Dashboard `/ligas/:ligaId/dashboard` | `DashboardLayout` → `Sidebar`: `logo.png`. `DashboardPage` usa `Icono` y puede mostrar `ayuda_2.png` mediante `EstadoVista` cuando faltan registros. No importa una fotografía ni una ilustración decorativa propia del dashboard. |
| Equipos, jugadores y plantillas | `EquipoCard`, `EquiposPage`, `JugadoresPage`, `ResumenPublico` e `ImagenCatalogoForm` muestran imágenes dinámicas mediante `ImagenEntidad`. |

### Fotografías, escudos y avatares dinámicos

`ImagenEntidad` recibe `equipo.escudo` o `jugador.foto`; `urlImagen` en `services/api.js` resuelve rutas de `/storage/` o URLs HTTP/HTTPS, incluidas las que entregue Cloudinary. `ImagenCatalogoForm` y `services/imagenCatalogo.js` permiten seleccionar JPEG, PNG o WEBP de hasta 5 MiB según la validación actual. No son recursos gráficos predeterminados guardados en `frontend/src/assets`.

No se consultaron bases ni se descargaron imágenes remotas: sus dimensiones, peso y disponibilidad actual quedan **sin verificar**, no se declaran rotas. Tampoco se inventaría cada fotografía cargada por usuarios. Las pequeñas caras o insignias dentro de las ilustraciones `ayuda*.png` no existen como archivos reutilizables separados.

Las tipografías Inter y Montserrat se referencian mediante Google Fonts en `index.html`; no hay archivos de fuentes locales dentro del frontend. Se registró esa dependencia sin descargar fuentes ni proponer cambios tipográficos.

## Favicon e identidad

**Sí existe favicon y utiliza el isotipo `logo_solo.png` presente en el repositorio**, visualmente correspondiente al símbolo del logo completo. `frontend/index.html` declara `rel="icon"`, tipo `image/png`, con `/src/assets/branding/logo_solo.png`.

El último build local conservado apunta a `/assets/logo_solo-COyLVBV1.png`. El archivo existe y su SHA-256 coincide exactamente con el original: `2277f7b7845ac0de112fa773f900fc060e8fcd530347cef9ef4f4a1cb000a8d4`. No se recompiló ni se modificó el recurso para esta comprobación. No se encontraron favicon ICO/SVG, tamaños pequeños dedicados, `apple-touch-icon` o un manifiesto de iconos PWA en el frontend actual.

## Duplicados, referencias y calidad

- **Duplicados exactos:** ninguno entre los ocho originales. Logo completo, nombre e isotipo son variantes distintas; usar el mismo `ayuda_2.png` en varios componentes no duplica el archivo.
- **Sin uso detectado:** `nombre_logo.png`. Conservar hasta que el usuario decida su función. Los otros siete recursos tienen referencia de aplicación o favicon.
- **Referencias locales rotas:** ninguna en las 13 importaciones de imágenes de `src` y la referencia del favicon verificadas. No se incluyen URLs ficticias de pruebas como si fueran archivos productivos faltantes. La disponibilidad de imágenes subidas depende del servidor y queda fuera de esta revisión sin datos.
- **Resolución:** los originales tienen 1254 o 1448 px de ancho; no se identificó un archivo de baja resolución nominal para sus usos actuales. El raster no sustituye un original vectorial si se requiere ampliar o imprimir.
- **Calidad observada:** al ampliar los PNG se aprecian contornos irregulares o residuos en algunas zonas de transparencia, especialmente alrededor de letras y bordes de ilustraciones. No se atribuye su origen ni se retocan. Conviene contrastarlos con la fuente maestra aprobada antes de nuevas exportaciones.
- **Logos:** `logo.png` y `nombre_logo.png` tienen lienzos con márgenes transparentes amplios. `.marca-logo` y `.marca-logo img` en `global.css` usan un marco con `overflow: hidden` y centrado vertical; esto es presentación CSS, no una variante recortada del archivo. El nombre y lema blancos requieren revisar contraste al elegir fondos claros para el rediseño.
- **Peso:** cinco ilustraciones suman 5.732.426 bytes; el isotipo usado como favicon pesa 576.396 bytes. No hay versiones WEBP/AVIF ni `picture`/`srcSet` en el código fuente actual. Priorizar una decisión sobre exportaciones adecuadas a cada uso, manteniendo los originales intactos.

## Propuestas nuevas — no existen ni se crearon

«Falta» significa que no se encontró en el frontend; no implica que el usuario no conserve el recurso en otro lugar ni que sea obligatorio añadirlo.

| Archivo / recurso propuesto | Ruta propuesta | Dimensiones | Uso previsto | Conservar / Mejorar / Falta |
| --- | --- | --- | --- | --- |
| Fuente maestra oficial vectorial, suministrada o confirmada por el usuario | Por decidir; archivo maestro separado de derivados web | Vectorial, sin tamaño fijo | Exportaciones autorizadas y comprobación de calidad de marca. No recrear ni vectorizar automáticamente el logo. | **Falta en frontend**; solicitar definición del original aprobado antes de crear derivados. |
| Exportaciones ligeras del favicon a partir del isotipo aprobado | Candidatas futuras en `frontend/public/brand/`; sin tocar el href actual | Propuestas 16 × 16 y 32 × 32 px; otros tamaños solo si se requieren | Reducir peso conservando identidad, sin redibujar ni sustituir todavía el favicon. | **Falta / propuesta pendiente**. |
| Exportaciones web de ilustraciones | Candidatas futuras en `frontend/src/assets/illustrations/`; nombres por aprobar | Varias anchuras según tamaño visible; no fijadas aún | Evaluar WEBP/AVIF/PNG optimizado y variantes responsive conservando transparencia y originales. | **Mejorar**, si se aprueba; no son archivos existentes. |
| Variante oficial adecuada a fondos claros, si el usuario dispone de ella | Por decidir con la fuente maestra | Por definir | Mantener legibilidad del nombre/lema sin modificar por cuenta propia los colores o trazos del logo actual. | **Falta por confirmar**; no se autoriza un redibujo. |
| Escudo genérico, avatar neutro, fotografías o fondo de cancha | Solo si se decide su necesidad en 6.6 | Por definir | Alternativas estéticas; las iniciales y fondos CSS actuales ya cubren la ausencia de imágenes. | **Opcional**, no bloqueo funcional ni creación autorizada. |

## Organización propuesta, sin mover archivos

La carpeta actual `frontend/src/assets/branding/` y sus ocho rutas quedan intactas. Para recursos futuros aprobados se propone separar:

```text
frontend/src/assets/
  branding/         # Originales existentes; mantener nombres y referencias.
  illustrations/    # Solo ilustraciones o derivados futuros aprobados.
  placeholders/     # Solo si se aprueban imágenes genéricas.
frontend/public/
  brand/            # Solo exportaciones públicas futuras de favicon, si se aprueban.
```

Los iconos existentes siguen centralizados en `components/Icono.jsx`. Fotos y escudos de usuarios continúan gestionados por Laravel/Cloudinary, no dentro de las carpetas de marca. No crear carpetas vacías ni duplicar todos los originales para aplicar esta propuesta; cualquier migración de rutas o selección de recursos requiere una decisión posterior.

Antes del rediseño, decidir con el usuario y ChatGPT: fuente maestra de marca, usos sobre fondos claros/oscuros, papel de las cinco ilustraciones, tamaños de exportación y necesidad real de fotografías o placeholders. Se conservaron todos los archivos y no se inició ninguna de esas acciones.

## Estado de la entrega

Solo se creó este inventario. Sin modificaciones de JSX, CSS, imágenes o configuración; sin accesos a bases, instalaciones, descargas, commits ni push. Las imágenes se leyeron para obtener metadatos, hashes y revisión visual; no se escribieron versiones nuevas. Git permanece en `develop`, HEAD `522b86f`, con este documento pendiente de revisión.
