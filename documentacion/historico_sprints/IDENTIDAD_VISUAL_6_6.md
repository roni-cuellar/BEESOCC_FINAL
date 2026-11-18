# Sprint 6.6A — Identidad visual y derivados técnicos

Fecha: 16 de noviembre de 2026. Rama `develop`, HEAD `522b86f`. Se consultó `INVENTARIO_GRAFICO_6_6.md` y se analizaron los ocho PNG originales de `frontend/src/assets/branding/`.

## Resultado y límite de fidelidad

**Preparados:** tres derivados PNG de marca con colores originales, cinco ilustraciones PNG optimizadas sin pérdida, cuatro tamaños de favicon y una lámina de contraste con archivos reales. Todo se guardó fuera del repositorio, sin integrarlo en React, CSS ni HTML.

**No exportados:** logo completo y versión textual para fondo claro. Se detuvo exclusivamente esa recoloración conforme a la condición indicada por el usuario. No se generaron aproximaciones ni máscaras aceptadas como definitivas.

Para completar las variantes claras se necesita el **maestro editable aprobado**, preferiblemente SVG/AI o PDF vectorial con BEE, SOCC, eslogan e isotipo separables. No basta un PDF que contenga únicamente el mismo PNG incrustado. La operación futura autorizada será recolorear BEE y el eslogan a `#0F3D2E`, conservando SOCC, abeja, proporciones, trazos y composición.

## Por qué se detuvo la recoloración

Se analizaron píxeles RGBA, zonas espaciales de BEE/eslogan y componentes conectados de alfa, sin escribir en los originales. Hay píxeles mixtos en los bordes: blancos, verdes y otros tonos, algunos con opacidad muy baja. Además, texto blanco y zonas verdes pertenecen a un mismo componente conectado:

| Original | Componente de alfa compartido, coordenadas inclusivas | Evidencia |
| --- | --- | --- |
| `logo.png` | x 449–1407; y 469–696 | Incluye píxeles blancos y verdes de alta opacidad; no es una máscara independiente de BEE/eslogan. |
| `nombre_logo.png` | x 32–1205; y 416–696 | Texto blanco y parte verde conectados por los bordes/transparencias del raster. |

Un componente conectado no demuestra que cualquier edición de PNG sea imposible. Sí impide asumir que existe una separación exacta por objetos. Usar rectángulos más umbrales de luminosidad requeriría decidir qué hacer con los píxeles mixtos; no se pudo acreditar una máscara al 100 % fiel sin estimar bordes. Por ello no se aplicó sustitución global, selección aproximada, reconstrucción de tipografía, limpieza ni redibujo. Se solicita la fuente editable antes de continuar esas dos variantes.

## Ubicación de la entrega

Los recursos se guardaron en la carpeta externa de capturas:

```text
revision66/identidad_6_6A/
  logos/
  ilustraciones/
  favicon/
  COMPARACION_VARIANTES_BEESOCC.png
  derivados.json
```

La carpeta `revision66` es la misma utilizada para las capturas y el muestrario de Sprint 6.6, fuera de Git. Todas las rutas de las tablas siguientes son relativas a `identidad_6_6A/`. No se crearon carpetas de derivados en los assets del frontend y las rutas actuales siguen intactas.

## Derivados existentes

PNG RGBA de ocho bits por canal, con las mismas dimensiones y píxeles que el correspondiente original. Peso exacto en bytes:

| Archivo / ruta externa relativa | Dimensiones | Formato | Peso original → derivado | Reducción | Validación y uso previsto |
| --- | --- | --- | --- | --- | --- |
| `logos/logo-fondo-oscuro.png` | 1448 × 1086 | PNG | 324.357 → 283.693 | 12,54 % | RGBA idéntico. Logo completo para fondo oscuro; no lleva un fondo pintado dentro del archivo. |
| `logos/nombre-logo-fondo-oscuro.png` | 1448 × 1086 | PNG | 347.757 → 294.467 | 15,32 % | RGBA idéntico. Variante textual con colores originales. |
| `logos/isotipo-optimizado.png` | 1254 × 1254 | PNG | 576.396 → 465.109 | 19,31 % | RGBA idéntico. Símbolo original, sin reinterpretaciones. |
| `ilustraciones/ayuda.png` | 1448 × 1086 | PNG | 1.400.982 → 1.259.324 | 10,11 % | RGBA idéntico; ilustración principal conservada. |
| `ilustraciones/ayuda_2.png` | 1254 × 1254 | PNG | 1.120.730 → 1.024.391 | 8,60 % | RGBA idéntico; trofeo y organización. |
| `ilustraciones/ayuda_3.png` | 1254 × 1254 | PNG | 1.181.815 → 1.053.057 | 10,89 % | RGBA idéntico; equipos y jugadores. |
| `ilustraciones/ayuda_4.png` | 1254 × 1254 | PNG | 954.059 → 804.121 | 15,72 % | RGBA idéntico; calendario y enfrentamientos. |
| `ilustraciones/ayuda_5.png` | 1254 × 1254 | PNG | 1.074.840 → 919.249 | 14,48 % | RGBA idéntico; estadísticas y posiciones. |

**Total de los ocho PNG a resolución original:** 6.980.936 → **6.103.411 bytes**, ahorro de **877.525 bytes / 12,57 %**, sin perder colores, transparencia, detalle o composición. Los bordes y posibles residuos que ya existían se conservaron; no se hizo una mejora artificial de calidad.

### Favicons

Se exportaron directamente desde el lienzo completo de `logo_solo.png`, con escalado uniforme, alfa y remuestreo bicúbico. No hay recortes, recoloración ni reconstrucción del símbolo.

| Archivo / ruta externa relativa | Dimensiones | Formato | Peso | Uso propuesto |
| --- | --- | --- | --- | --- |
| `favicon/favicon-16.png` | 16 × 16 | PNG transparente | 569 bytes | Tamaño pequeño para pestaña; sujeto a revisión de legibilidad. |
| `favicon/favicon-32.png` | 32 × 32 | PNG transparente | 1.219 bytes | Candidato principal para favicon; aproximadamente 99,79 % menos que el PNG actual de 576.396 bytes. |
| `favicon/favicon-48.png` | 48 × 48 | PNG transparente | 2.041 bytes | Tamaño intermedio. |
| `favicon/favicon-64.png` | 64 × 64 | PNG transparente | 2.953 bytes | Revisión y pantallas de mayor densidad. |

Cambiar de resolución implica cambiar la matriz de píxeles: los favicons **no** se presentan como idénticos píxel a píxel al isotipo de 1254 px. Se conserva la geometría mediante una transformación uniforme del archivo entero. Se verificaron tamaños y presencia de transparencia; la lámina permite revisar los iconos a tamaño real. `index.html` sigue utilizando su favicon anterior: no se aplicó ninguno de estos archivos.

### Elección del formato

Para esta entrega se priorizó PNG sin pérdida. Se utilizaron Node/zlib y las bibliotecas gráficas ya disponibles en Windows; no se instalaron librerías. No se verificó un codificador WebP sin pérdida disponible en el entorno y no se añadió una dependencia para cambiar de formato. **No se exportaron WebP ni se disfrazaron PNG con esa extensión.** Una exportación WebP posterior puede evaluarse cuando se acuerde, manteniendo estos PNG validados como referencia.

No se redujo la resolución de las cinco ilustraciones para ganar peso ni se decidió todavía un recorte para tarjetas. Los tamaños específicos de interfaz deben elegirse al aprobar cada pantalla de 6.6.

## Método y comprobaciones

1. Lectura de los ocho originales y registro de SHA-256; nunca se abrieron en modo de escritura.
2. Decodificación de los PNG RGBA8 existentes y evaluación de filtros/recompresión IDAT con zlib. Se eligió solo un resultado de menor peso; se conservaron los demás bloques del PNG y el lienzo completo. No se tocaron los canales RGBA.
3. Comparación completa de la matriz RGBA decodificada de cada derivado con su fuente: **8/8 idénticas**, incluidos los píxeles transparentes. El canal alfa permanece intacto, por lo que no se modifica el suavizado original.
4. Segunda comprobación con el decodificador independiente de Windows: **8/8 imágenes con los mismos píxeles ARGB**.
5. Validación de dimensiones, transparencia y hashes de los cuatro favicons. Separación expresa entre compresión sin pérdida y remuestreo de tamaño.
6. Revisión visual de originales y derivados sobre blanco y `#0F3D2E` en la lámina real. La matriz es idéntica para los derivados grandes; no se afirma una verificación de firma de procedencia de los PNG.
7. Revalidación de los SHA-256 de todos los originales al cerrar la entrega. `derivados.json` conserva dimensiones, pesos, porcentajes, hashes de origen/salida/RGBA y límites de validación.

Los ejecutores locales de análisis y exportación se conservaron en la misma carpeta externa para reproducibilidad. No forman parte de Laravel, React ni una funcionalidad del proyecto.

## Lámina de comparación

`COMPARACION_VARIANTES_BEESOCC.png`: **5400 × 4000 px**, **2.297.171 bytes**. Presenta:

- Arriba: los tres originales sobre blanco, expresamente etiquetados como diagnóstico, **no como variantes claras**.
- Debajo: los tres PNG optimizados sobre verde oscuro, respetando tamaño nativo, proporciones y transparencia.
- Aviso visible de las dos variantes claras pendientes del maestro editable.
- Los cuatro favicons reales con sus dimensiones, sin alterar los archivos para simular una mayor resolución.

No se simuló una variante que no pudo validarse. El muestrario anterior y los originales permanecen conservados.

## Seis ilustraciones planificadas — no generadas

Dirección común propuesta para revisión con el usuario y ChatGPT: lenguaje semitridimensional de las cinco ilustraciones originales, verde, blanco y verde oscuro, volumen suave, iluminación y sombras coherentes, fondo transparente y elementos deportivos claros. Las referencias son los archivos reales `ayuda.png` y `ayuda_2.png` a `ayuda_5.png`; no una reinterpretación libre de marca. Lienzo de trabajo cuadrado, por ejemplo 1254 × 1254, sujeto a aprobación antes de generar.

No pedir a un generador que reconstruya el logo, la tipografía o el isotipo. Si la composición futura requiere identidad de marca, reservar su ubicación para incorporar el recurso oficial real después de aprobar el diseño. Evitar datos personales, marcas de equipos reales, cifras que aparenten resultados reales y texto pequeño ilegible.

| Ilustración futura | Composición propuesta para discutir | Referencias visuales | Límites |
| --- | --- | --- | --- |
| Arbitraje y actas | Silbato, tarjetas y tablet o acta con líneas neutras; balón como apoyo. | `ayuda_4` para objetos deportivos, `ayuda` para paneles. | Sin firmas, documentos personales ni un resultado oficial inventado. |
| Fase de grupos y clasificación | Dos paneles de grupos con escudos genéricos y puestos visualmente claros; conexión hacia siguiente fase. | `ayuda_2` y `ayuda_5`. | No elegir clasificados ni representar reglas nuevas. |
| Eliminación directa | Cuadro de llaves con plazas neutras, balón y trofeo; distribución comprensible. | `ayuda_2` y `ayuda_4`. | Sin ganador arbitrario ni logotipos de clubes reales. |
| Penales y desempates | Portería, balón en punto penal y panel de seguimiento sin marcador concreto. | `ayuda_4` para balón/objetos y `ayuda_5` para datos. | No asociar la ilustración a una resolución DP-03 ni cambiar reglas. |
| Inscripciones y plantillas | Camisetas, fichas y escudos genéricos, con mini formación deportiva. | `ayuda_3`. | Sin nombres, nacimientos o identificadores personales; figuras ilustradas, no fotografías reales. |
| Consulta pública | Teléfono y pantalla con calendario, tabla y partidos; persona o símbolo de consulta opcional. | `ayuda` y `ayuda_5`. | Comunicar consulta abierta sin introducir login obligatorio ni datos administrativos privados. |

Estas fichas **no son recursos existentes, imágenes generadas ni decisiones visuales aprobadas**. Falta acordar con ChatGPT composición, tamaño final, necesidad y aprobación de cada una. No se integró ninguna.

## Estado final

Los ocho originales conservan sus hashes y sus rutas. No se modificaron React, CSS, HTML, branding fuente, configuración o bases. Sin instalaciones, IA generativa, commits ni push. En Git solo quedan pendientes el inventario anterior y este documento; los doce PNG derivados, la comparación y el manifiesto están fuera del repositorio.

Pendiente imprescindible para completar la parte bloqueada: recibir el maestro editable aprobado con los elementos del logo separados. Hasta entonces no se exportan las variantes claras ni se inicia su integración.
