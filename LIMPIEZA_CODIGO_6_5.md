# Sprint 6.5 — Código legible y organizado

Fecha: 16 de noviembre de 2026. Rama `develop`. Sprint exclusivamente de formato y legibilidad, sin rediseño visual ni cambios deportivos.

## Protección de Sprint 6.4

Las 19 rutas coincidieron exactamente con el inventario externo aprobado. Se verificaron los SHA-256 tanto de los archivos de trabajo como de sus copias, se revisaron campos privados y posibles secretos y se prepararon únicamente esas rutas. `git diff --cached --check` aprobó antes del commit local **`59830154188fd69d372e90a97f59dacae1d57ed1`**, `feat: completar consulta publica de los tres formatos`. No se hizo push ni se modificaron commits anteriores.

## Configuración

| Herramienta | Configuración y alcance |
| --- | --- |
| Prettier 3.10.1 | Dos espacios, comillas simples en JavaScript, sin punto y coma, ancho orientativo de 100 y finales LF. `.prettierrc.json` y `.prettierignore` en frontend. |
| ESLint 10.15.0 | Configuración plana recomendada de `@eslint/js` 10.0.1, JSX nativo, globals declarados y sin reglas de formato. Compatible con Node instalado 22.18.0. |
| Oxlint | Se conserva la herramienta y su configuración anterior, incluidas las reglas React. `npm run lint` ejecuta ESLint y Oxlint. |
| Laravel Pint | Se conserva el estilo Laravel existente. Se aplicó a aplicación/rutas y se comprobó sobre PHP versionado, sin incluir herramientas privadas ignoradas. |
| VS Code | Recomendaciones y formato al guardar para JS, JSX, CSS y PHP, con ruta relativa al Pint de backend. Los archivos de configuración del proyecto no existían; no se sobrescribieron ajustes personales ni se instalaron extensiones. |

Scripts nuevos: `npm run format`, `npm run format:check`, `npm run lint:eslint` y `npm run lint:oxlint`. No se instaló un plugin de formato para ESLint: Prettier y ESLint tienen responsabilidades separadas. Las cuatro dependencias nuevas finales son de desarrollo: Prettier, ESLint, `@eslint/js` y `globals`. Las versiones de todas las dependencias productivas del lockfile permanecen intactas. El lockfile solo cambió mediante npm para registrar esas herramientas, no mediante formato masivo.

## Trabajo por grupos

Se probó primero con `EstadoVista.jsx`, `AccionesEstadoPartido.jsx` y **`grupoService.js`**, nombre real del servicio indicado como `gruposService.js` en el alcance. Se conservaron su nombre, exportaciones y endpoints.

Después del piloto se inventariaron 117 archivos frontend: siete ya cumplían el formato y 110 necesitaban aplicación. Se continuó por componentes, páginas/rutas, servicios/stores/utilidades, CSS y pruebas, seguido de configuración e HTML de entrada. Los patrones fueron explícitos por tipo y carpeta; no se formatearon `node_modules`, `vendor`, `dist`, lockfiles, HTML de evidencias de navegador, SQL, secretos, respaldos ni documentación histórica.

Resultado: **122 archivos de código organizados: 112 JS/JSX/CSS/pruebas frontend, un HTML de entrada y nueve PHP**. La comparación completa incluyó 117 archivos frontend y 79 PHP de aplicación/rutas; los que ya cumplían el estilo se conservaron. Las rutas Laravel ya estaban conformes con Pint y no se modificaron.

Cambios principales:

- JSX con propiedades, formularios, condiciones y elementos separados en líneas. Objetos, servicios Axios, stores y CSS con indentación y espaciado uniforme.
- `EstadoVista` conserva la prioridad carga → error → vacío; se añadieron bloques explícitos y separaciones entre ramas.
- `AccionesEstadoPartido` usa la función local `textoAccion` para reemplazar un ternario anidado. Se conservan destinos, permisos, bloqueo y callback. La prueba arbitral existente también comprueba las etiquetas de iniciar, reanudar, suspender y finalizar.
- Listas largas de campos distribuidas en cinco modelos, dos controladores y dos servicios. Sin cambios de validación, campos, modelos serializados ni datos.
- Se conservaron nombres públicos, orden de ejecución e importaciones con posibles efectos laterales. No se crearon componentes, stores o servicios nuevos para fragmentar módulos que ya funcionaban.

## Comprobación de equivalencia y calidad

Se comparó cada archivo frontend con el resultado de ejecutar el mismo Prettier sobre su versión en HEAD: **114 de 117 coinciden exactamente con ese resultado**. Las tres excepciones revisadas son los dos componentes señalados y la prueba arbitral reforzada. Así se separa el formato automático del pequeño ajuste de legibilidad.

Los **79 PHP conservan tokens equivalentes** respecto a HEAD, descontando espacios, comentarios y comas finales permitidas. Las cadenas de campos y las expresiones permanecen iguales. No se cambiaron migraciones. Las comparaciones se ejecutaron como comprobaciones locales, sin añadir infraestructura de pruebas a la aplicación.

| Verificación | Resultado |
| --- | --- |
| Prettier check | Aprobado sobre los patrones de código, pruebas y configuración frontend. |
| ESLint y Oxlint | Aprobados, sin errores ni advertencias. |
| Pint | Aprobado sobre PHP versionado; aplicación y rutas conservan el estilo Laravel. |
| Backend SQLite / kernel Laravel | **219 pruebas, 7.123 aserciones aprobadas.** SQLite en memoria; no es HTTP de red ni prueba sobre MariaDB. |
| Frontend | **265 pruebas aprobadas: 218 Vitest y 47 Node.** Incluyen los contratos y comportamientos de los tres formatos. |
| Build | Aprobado: JS 504,48 kB, gzip 148,31 kB; CSS 72,40 kB. |
| npm audit | Cero avisos en el árbol final, incluidas las dependencias de desarrollo. |
| Diferencias | Revisadas; `git diff --check` y comprobación del índice sin errores. |

No se repitieron HTTP, navegadores ni carreras históricas: no cambiaron contratos, datos, operaciones deportivas o diseño. Sus evidencias anteriores no se presentan como nuevas pruebas de este sprint. No se iniciaron laboratorios ni servidores.

## Advertencia de bundle

La advertencia por superar 500 kB continúa visible. `AppRoutes.jsx` importa estáticamente todas las páginas; sus formularios, cuadros y dependencias terminan en el mismo paquete inicial. No depende del número de líneas que ocupa el código fuente después del formato.

Una compilación de análisis con Vite, sin escribir artefactos, agrupó `renderedLength` por paquete:

| Grupo | Bytes de módulos antes de la minificación final |
| --- | ---: |
| React DOM | 536.687 |
| Aplicación | 353.607 |
| Axios | 105.116 |
| React Router | 93.179 |
| React | 17.166 |
| Scheduler | 9.212 |
| Zustand | 1.619 |

Estos tamaños de módulos no son tamaños de descarga ni se comparan directamente con el archivo final comprimido. React DOM y la carga conjunta de las pantallas explican la mayor parte. Una futura división por rutas requerirá alcance aprobado y sus regresiones; no se implementó, no se elevó el umbral para esconder el aviso y no se actualizaron dependencias productivas para intentar reducirlo.

## Guía, integridad y Git

`GUIA_CODIGO_6_5.md` explica las carpetas, componentes, servicios, stores, flujo React–Laravel, comandos y ejemplos antes/después. Está orientada al estudio y sustentación del SENA.

La principal no se consultó ni se abrió una conexión a ella durante este sprint. No se ejecutaron migraciones contra MariaDB, ni se modificaron bases, históricos o sesiones. Las pruebas backend usaron exclusivamente SQLite en memoria. 000400 continúa fuera del alcance y pendiente de autorización; no se volvieron a ejecutar herramientas del cierre 6.3.

Estado final: `develop` en `5983015`, **131 rutas de 6.5 pendientes** —124 modificadas y siete nuevas—, sin staging, sin commit adicional y sin push. El código, configuraciones y dos guías quedan respaldados externamente con inventario SHA-256; sin credenciales ni evidencias privadas dentro de la selección. No se modificó `main` ni se inició el rediseño 6.6.
