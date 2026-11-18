# Auditoría integral y plan de cierre de BEESOCC FINAL

Fecha: 15 de noviembre de 2026. Rama: `develop`.
HEAD y referencia local `origin/develop`: `adda47717a205663aa546a47e8b79a013a07d4cd`.
Auditoría de análisis: no se implementaron funcionalidades, no se cambiaron
dependencias, migraciones, configuración ni documentos maestros. No se hicieron
commits ni push. No se iniciaron servidores o laboratorios ni se repitieron las
suites completas.

## 1. Dictamen y límites de la entrega

**El núcleo privado permite una demostración controlada de los tres formatos.
Todavía no está completo todo el alcance de BEESOCC FINAL definido en el MER y
el Manual Técnico.** Hay defectos concretos en contratos de imágenes, brechas
de ciclo de vida y consulta pública, decisiones deportivas pendientes y avisos
de dependencias que deben tratarse antes de una entrega operativa general.

No se constató una intrusión, una fuga de datos privados ni corrupción de la
principal. Tampoco se hizo una prueba de penetración exhaustiva. La ausencia
de un fallo en los recorridos deportivos no certifica todos los catálogos,
reglas excepcionales, navegadores o configuraciones de despliegue.

Para una sustentación local se recomienda una base ficticia preparada, roles
separados, un guion probado y una declaración explícita del alcance pendiente.
No presentar como disponible la resolución administrativa, un cierre global
del campeonato o los cuadros públicos que aún faltan. La exposición en Internet
requiere una revisión de seguridad y despliegue separada.

### Significado de prioridades

- **P0:** bloqueo de seguridad, integridad o de la salida concreta comprometida.
- **P1:** defecto funcional o requisito obligatorio incompleto del alcance FINAL.
- **P2:** mejora importante de calidad, experiencia o verificabilidad.
- **P3:** ampliación opcional o de una versión posterior.

No hay un P0 de explotación o pérdida de datos demostrado en este ensayo.
La salida pública tiene una **puerta P0 condicional**: no autorizar exposición
general sin cerrar los avisos aplicables, definir un entorno soportado y
verificar HTTPS, cookies, secretos y permisos de despliegue. Eso no significa
que el entorno local actual haya sido publicado o comprometido.

## 2. Evidencia y método

Se inspeccionaron rutas, controladores, Policies, modelos, migraciones,
servicios Laravel, rutas React, stores, formularios y componentes. Se extrajo
el texto de los dos DOCX consolidados para cotejar requisitos; no se modificó
su contenido ni se hizo una nueva revisión de su maquetación.

Fuentes principales:

- `docs/BEESOCC_FINAL_03_Modelo_Entidad_Relacion_Consolidado.docx`.
- `docs/BEESOCC_FINAL_Manual_Tecnico_Consolidado.docx`.
- Informes de autenticación, autorización, publicación y arbitraje en `backend/tests/`.
- `backend/tests/ELIMINACION_DIRECTA_4_1_REGLAS_PROPUESTAS.md`, 4.2 y 4.5.
- `backend/tests/GRUPOS_5_3.md`, 5.4, 5.5 y 5.7; `frontend/tests/GRUPOS_5_6.md`.
- Evidencias agregadas de 3.5, 4.5, 5.4 y 5.7 y código de sus verificadores.

| Tipo de evidencia | Qué acredita | Límite |
| --- | --- | --- |
| Backend de 5.7: 188 pruebas, 4.489 aserciones | Principalmente pruebas Feature del kernel Laravel con SQLite en memoria, además de Unit | No son 188 pruebas HTTP de red ni garantizan bloqueos InnoDB |
| Frontend de 5.7: 47 Node + 193 Vitest = 240 | Servicios, stores, renderizado y contratos con datos/promesas simulados | No equivalen a 240 interacciones DOM o recorridos reales |
| HTTP de red 5.4: 380 comprobaciones | Laravel, sesiones/CSRF, MariaDB aislada y cuatro carreras nuevas | Evidencia anterior; no se volvió a ejecutar |
| HTTP de red 5.7: 194 comprobaciones en 11 modos | Preparaciones, permisos, conflictos, correcciones y compatibilidad reales | No contar cada petición del navegador como otra prueba independiente |
| Navegador real 3.5, 4.5 y 5.7 | Sesiones, actas, correcciones, penales y avance; grupos en 5.7 | Algunos casos largos se prepararon por API; la tabla de 5.7 los distingue |
| Visual 5.6 con API simulada | Flujos y errores del prototipo de grupos | No usarlo como prueba de integración real |
| Visual 5.7 con API real | Escritorio 1280 × 720 y marco de contenido 390 × 844 | El marco móvil no es un teléfono físico ni prueba gestos/teclado móvil |
| Auditoría 6.0 | Revisión estática, validación focal de imágenes, conteos READ ONLY, Pint y auditorías de dependencias | Sin nuevo E2E, laboratorio, carrera o suite completa |

Los totales anteriores son resultados conservados de sus sprints, no pruebas
nuevas de 6.0 ni una suma de cobertura independiente. La revisión del código
puede descubrir contratos que los escenarios previos no ejercitaron.

## 3. Protección de los siete archivos de 5.7

Se localizaron respaldos externos de 5.3–5.6. No incluían los siete archivos
nuevos de 5.7. Se creó un respaldo actualizado **fuera del repositorio y de
TEMP**, en un directorio de Documentos con ACL limitada al usuario local y
SYSTEM. Contiene un ZIP de los archivos versionados de HEAD, las siete copias
pendientes y un inventario SHA-256. Cada copia coincide con su original; el
ZIP también tiene huella registrada. No es un respaldo SQL nuevo ni contiene
sesiones, cookies, entornos privados o dependencias instaladas.

La ruta exacta y las huellas completas permanecen en el inventario externo;
el puntero está en `backend/tests/Integral/privadas-locales/sprint60-respaldo-ruta.txt`,
carpeta ignorada. No se revelan rutas personales ni credenciales en este informe.
Una copia en el mismo equipo no sustituye una segunda copia en otro soporte.

| Ruta exacta revisada | Contenido |
| --- | --- |
| `backend/tests/Evidencias/sprint57-evidencia.json` | Agregados de resultados, sin cuentas o claves reales |
| `backend/tests/GRUPOS_5_7.md` | Recorrido, límites y reproducción del laboratorio |
| `backend/tests/Integral/sprint57-http.mjs` | Cliente HTTP real; claves sintéticas derivadas y cookies externas |
| `backend/tests/Integral/sprint57-sembrar.php` | Guardas de aislamiento y datos ficticios; migra únicamente el laboratorio vacío |
| `backend/tests/Integral/sprint57-servidor.php` | Router aislado y latencia GET controlada; trazas sin cuerpos/cookies |
| `backend/tests/Integral/sprint57-verificar.php` | Transacción de solo lectura y comprobaciones de integridad del laboratorio |
| `frontend/tests/browser/grupos-real.html` | Marco responsive de la aplicación normal; no sustituye su API |

Los siete conservaron sus bytes. La revisión de espacios no encontró errores;
Git avisa de una conversión de fin de línea prevista para el HTML, no de un
fallo funcional. La inspección y búsqueda de patrones de secretos en archivos
versionados y la revisión de los siete no detectaron claves privadas, tokens
reales, SQL, bases locales o evidencias privadas para añadir. Esto no es un
escáner de todos los secretos posibles ni de todo el historial Git.

La migración 000200 ya está versionada en HEAD y no pertenece a estas siete
rutas. No se ejecutó de nuevo. Su SHA-256 aprobado sigue siendo
`c48ee2d69c8a6b1c3322401b4f7573e2120833c57b84d5836c90619ba70baebb`.

**Commit propuesto, no creado:**
`test: validar integracion React real de grupos y eliminacion`.
Seleccionar únicamente las siete rutas de la tabla, revisar contra el HEAD
vigente y ejecutar `git diff --cached --check` después de una autorización
para preparar el commit. No usar `git add .`. Este informe 6.0 debe ir en otro
commit documental posterior; no mezclarlo con la evidencia de 5.7.

## 4. Estado funcional por módulo

“Probado” describe los caminos cubiertos, no una ausencia total de defectos.
Las columnas HTTP y navegador se basan en los informes conservados; no se
amplía su alcance por inferencia.

| Módulo | Implementación y prueba automatizada | HTTP / navegador real acreditado | Pendiente o fallo conocido |
| --- | --- | --- | --- |
| Autenticación y sesiones | Registro, login, logout, hash, regeneración de sesión, Sanctum, CSRF y límite de intentos; `AuthTest`, pruebas frontend | Sesiones reales en 3.5/4.5/5.7; login de tres roles en 5.7 | Homologar despliegue HTTPS. Recuperación de contraseña/verificación de correo no implementadas; no se identificó obligación aprobada de incluirlas en esta entrega |
| Administrador y árbitro | Propiedad por competición; asignación vigente, reemplazo, revocación y prohibición de autoarbitraje; Policies y `AsignacionArbitroTest`/`ControlArbitralTest` | HTTP y navegador 3.5/4.5/5.7; 403 y revocación | No hay un “rol global” único: es contextual por campeonato, conforme al diseño. Históricos sin dueño requieren decisión humana |
| Espectador y publicación | API pública separada, opt-in `publicado`, lista de campos, `no-store`, ocultar y 404; `PublicacionCampeonatoTest` y frontend | Navegador público inicial 1.6 principalmente vacío; no atribuirle una demo completa con datos de los tres formatos | Vista pública de grupos/cuadros ausente; estadísticas públicas conservan régimen provisional heredado |
| Campeonatos | CRUD, dueño automático, tres formatos y bloqueo del cambio con estructura; `ApiCrudTest`/`CampeonatoPropiedadTest` | Creación real desde React en 5.7 | Sin flujo global de iniciar/cerrar/archivar campeonato; estados de fases no sustituyen ese ciclo |
| Equipos | Catálogo por dueño, CRUD, inscripción, guardas de borrado; pruebas CRUD y autorización | Preparación HTTP en laboratorios | Cambio de escudo incompatible entre React y API; edición sin archivo puede borrar el escudo existente |
| Jugadores | Catálogo por dueño, datos y fechas, guardas de borrado; pruebas CRUD | Preparación HTTP sin fotos en 5.7 | Edición de foto URL falla; subida de archivo en alta también contradice su validador |
| Inscripciones de equipos | Unicidad física, pertenencia, estado activo/retirado y protección tras siembra/distribución | HTTP real 5.4/5.7 | Retiro no equivale a descalificación ni resuelve pendientes 3–0; cambio de estado no usa el bloqueo compartido de los servicios deportivos |
| Plantillas y dorsales | Altas, cierre individual e historial; unicidad activa validada secuencialmente; `ApiDeportivaTest` | API real para preparar plantillas; eventos validan pertenencia/vigencia | Sin cierre global al inicio, mínimos o aplicación de ventanas; sin garantía física/transaccional de unicidad activa concurrente |
| Calendario y partidos | Vueltas de liga, grupos y series; programación separada del acta y estados de partido; pruebas de tres formatos | HTTP 4.5/5.4/5.7 y calendario de grupos desde React | Falta homologar habilitación por plantilla mínima/cerrada. No hay cierre integral del torneo |
| Eventos y actas | Gol, autogol, asistencia ligada a gol, tarjetas, faltas, minuto y permisos; `ControlArbitralTest`, `ApiDeportivaTest` | Registro y oficialización reales desde React | DP-07 conserva semántica diaria anterior; no es precisión temporal definitiva |
| Resultados oficiales | Finalización deportiva, bloqueo ordinario y etiqueta oficial; `ResultadoOficialTest` | 3.5/4.5/5.7 | 3–0 administrativo no disponible; reportes/tablas heredadas no deben confundirse con clasificación oficial de grupos |
| Correcciones y auditoría | Solicitud, aprobación, consumo único, lote atómico, antes/después y propagación conservando IDs | Corrección real de acta en 3.5/4.5; en 5.7 por HTTP y recarga React | Tandas y revisión extraordinaria con descendientes iniciados se rechazan; no tienen procedimiento alternativo implementado |
| Estadísticas y tablas | Seis estadísticas y tabla de liga; tabla regular separada de extras en grupos; `ApiResultadosTest`/`GruposEliminacionTest` | HTTP y consulta real en sprints de integración; tabla de liga en React 4.5/5.7 | Estadísticas incluyen en curso/suspendidos; liga usa desempate visual por nombre/ID cuando agota sus criterios |
| Todos contra todos | Calendario y puntos de 1/2 vueltas, equipos impares y estadísticas heredadas | HTTP real y consulta React de tabla | No trasladar automáticamente al formato liga las garantías nuevas de desempate/clasificación oficial que solo implementa grupos |
| Eliminación directa | Siembra, byes, único/ida-vuelta, tanda, avance, procedencias y corrección; `EliminacionDirectaTest` | Recorrido real 4.5; compatibilidad y avance React en 5.7 | Falta resolución administrativa, corrección de tanda y cuadro público |
| Grupos + eliminación | Distribución firmada, calendario, minitablas, extras, liguillas, clasificación y transición; `GruposTest`/`GruposEliminacionTest` | 380 comprobaciones 5.4, 194 comprobaciones parciales 5.7 y recorrido React real | Núcleo privado del alcance 5.3 completo; pendientes generales administrativos, plantillas y lectura pública no quedan resueltos por ello |

## 5. Hallazgos priorizados y esfuerzo

Estimaciones en **días-persona**, incluyendo regresión focal y documentación
del cambio, sin esperas por decisiones o autorizaciones. No son compromisos
de calendario ni autorizan implementación. Las tareas relacionadas comparten
trabajo; no se deben sumar mecánicamente todos los rangos.

| ID / prioridad | Qué falta y dónde | Impacto y evidencia | Dependencia / riesgo / esfuerzo |
| --- | --- | --- | --- |
| S01 · P1; puerta P0 antes de exposición | Tratar nueve avisos del `backend/composer.lock` y homologar runtime/despliegue | Audit falló con seis paquetes afectados. MariaDB 10.4 terminó mantenimiento; PHP local 8.2.12 es una revisión antigua. No se demostró explotación | Seleccionar destino y autorizar mantenimiento acotado; riesgo medio/alto de compatibilidad. 1–3 días de triaje/actualización de paquetes; 2–4 adicionales si se cambia runtime, siempre ensayado fuera de la principal |
| F01 · P1 | Unificar contratos de imágenes en `JugadorController.php:30,93`, `EquipoController.php:100`, `JugadorForm.jsx:12` y `equipoService.js:28` | URL válida en alta de jugador se rechaza al editar; archivo de escudo se envía como File y se valida como string. Alta de foto File también se rechaza. Con escudo sin nuevo archivo, formulario envía null y API lo guarda, perdiendo la referencia | Decidir URL o archivo por operación y distinguir omitir/conservar/eliminar. No requiere regla deportiva. Riesgo bajo/medio; 1–2 días |
| I01 · P1 | Serialización y unicidad activa en `PlantillaController.php:50–74,115–126` y migración `create_plantillas_table` | `exists()` seguido de escritura, sin transacción/bloqueo común ni índice único activo; jugador/dorsal concurrente no garantizados. Verificado en código, no mediante carrera nueva | Diseñar bloqueo y protección física compatible con históricos; carreras focales nuevas en copia. Riesgo alto de integridad; 2–3 días |
| F02 · P1 | Ciclo global y plantillas cerradas: `CampeonatoController`, `PlantillaController`, `ConfiguracionCampeonatoController`, `PartidoController` | D-12 exige cierre al inicio. Fechas de inscripción/transferencia se guardan pero no se consultan al crear/cerrar plantilla. Estado inicial de campeonato sigue planificación; solo fases/rondas se actualizan automáticamente | Aprobar transiciones/cierre y excepciones. No inventar política de archivo. Compartir bloqueo con I01; riesgo alto; 3–5 días |
| D01 · P1 antes de elegibilidad definitiva | DP-07 en `EventoEstadisticoService::comprobarVigencia` y plantillas DATE | Se admite el día de salida y se usa fecha del partido o now si no se programó. No está cerrado el instante del mismo día. Es comportamiento conservado, no un fallo nuevo del sprint | Decisión del promotor sobre instante/zona/partidos sin hora; evaluar migración solo si necesaria. Riesgo alto sobre históricos; 1–2 días tras aprobación |
| D02 · P1 para alcance FINAL completo | Retiro/descalificación, 3–0 y DP-03: `CampeonatoEquipoController::update`, `MarcadorService`, `ResultadoOficialService`, `AvanceEliminacionService` | Solo activo/retirado; marcador deriva de eventos y avance exige deportivo oficial. Falta marcador administrativo, motivo, autoridad, bloqueo explícito de doble descalificación y propagación | D-14/D-25 aprobados; procedimiento DP-03 aún sin cerrar. Resolver especificación primero. Riesgo alto; 4–7 días después de la decisión |
| D03 · P1 para operación real | Mínimo configurable de jugadores (DP-04 / D-26 / P-05) | No hay campo de modalidad/mínimo ni guarda correspondiente al iniciar. Siete jugadores o un número fijo no pueden suponerse | Aprobar modalidad, valores, suplentes y plantillas vacías; depende de F02. Riesgo medio/alto; 2–4 días |
| F03 · P1 | Lectura pública de grupos y eliminación: `PublicoCampeonatoController`, `AppRoutes.jsx`, `DetallePublicoPage.jsx` | No hay endpoints públicos de fases/cuadros/tabla por grupo. Detalle público enlaza siempre a Tabla y el servicio acepta solo todos contra todos: para los otros formatos produce 409. Calendario plano no sustituye clasificación/cuadro | Reutilizar presentación, pero crear contrato público con lista de campos, nunca abrir la API privada. Riesgo medio de privacidad; 2–4 días |
| D04 · P1 condicionado al reglamento de entrega | Desempate de liga: `TablaPosicionesService.php:98–115` | Agotados PTS/DG/GF ordena por nombre y equipo_id y asigna puestos sucesivos. No hay directo, minitabla ni expediente de liga. El algoritmo nuevo de grupos no se aplica aquí | Ratificar alcance de RF-27 en liga antes de afirmar campeón/desempate oficial. No modificar compatibilidad silenciosamente. Riesgo medio/alto; 2–4 días si se exige cierre deportivo, o aclaración de alcance si solo es orden visual |
| D05 · P1 si se promete información pública exclusivamente oficial | `PublicoCampeonatoController::estadisticas`, `EstadisticaService` y datosPartido | Estadísticas incluyen en_curso/suspendido/finalizado; respuestas de partidos no entregan la etiqueta oficial equivalente al panel privado. Política pública actual es explícita por publicado, no oficialización | Mantener lo acordado hasta aprobar ampliación. No es fuga de liga privada. Definir provisional/oficial al completar F03; riesgo medio; 1–2 días |
| D06 · P1 si la entrega exige operar históricos | Asignación humana de propietarios y plan de migración de datos | READ ONLY: 2/3 campeonatos, 8/8 equipos y 3/3 jugadores sin dueño. Policies los bloquean y el catálogo público los excluye. No son datos borrados | Responsable debe acreditar dueño; nunca inferirlo del primer login ni de relaciones. Para demo ficticia no bloquea. Riesgo alto; 1–2 días técnicos más validación humana, ensayo y autorización puntual |
| D07 · P2; P1 si obligatorio en reglamento | Corrección de tanda y revisión extraordinaria de eliminatorias | `registrarPenales` impide otra tanda; expediente actual solo corrige eventos. Descendientes iniciados bloquean el lote por diseño, conservando autorización | Aprobar autoridad, evidencia y alcance. El bloqueo actual es seguro y aprobado. Riesgo alto; 2–4 días si se decide implementar |
| Q01 · P2 | Pint general y alcance del comando | Falla `backend/config/services.php` por indentación/espacios; otros cuatro fallos son herramientas ignoradas. Los tres PHP de 5.7 pasan | Corregir formato versionado en cambio separado y definir si Pint debe excluir privados; no publicar estos para “hacer pasar” el comando. Riesgo bajo; 0,25–0,5 días |
| Q02 · P2 | Recorrido final de catálogo/publicación con datos y accesibilidad móvil | Evidencia pública temprana principalmente vacía; 5.7 se concentra en núcleo privado. No existe evaluación WCAG completa ni teléfono físico | Hacerlo después de F01/F03 para no repetirlo. Matriz corta teclado, foco, zoom, móvil y permisos. Riesgo bajo; 1–2 días |
| DOC01 · P1 de entrega | README, Manual, MER y matriz de aceptación actual | README afirma login demostrativo y ausencia de Git; tres enlaces a docs inexistentes. Maestros marcan pendiente mucho ya implementado | Actualizar después de estabilizar alcance; conservar informes históricos de cada sprint. Riesgo bajo, impacto alto en sustentación; 1–2 días |
| OPS01 · P2 local; puerta P0 de despliegue público | Guía de instalación/arranque/restauración y configuración de producción | README solo local; ejemplo usa APP_DEBUG=true, cookies no Secure y SQLite. Proxy Vite es de desarrollo. `composer setup` incluye migrate --force | No usar setup como revisión ni copiar ejemplo a producción sin ajustar. No se verificó hosting real. 1–2 días de runbook/ensayo; seguridad del despliegue se acepta por separado |
| Q03 · P2 | Automatización reproducible y herramientas duplicadas | No hay workflow `.github` versionado; `backend/package.json` conserva tooling adicional sin lockfile npm propio | Definir cuál build se entrega; no instalar el tooling antiguo para auditarlo. CI con SQLite/Node y operaciones DB fuera del pipeline de entrega por defecto. Riesgo bajo/medio; 0,5–1 día |
| OPT01 · P3 | Recuperación de acceso/correo, historial visual de nombres, optimización de imágenes y detalle por lanzamiento | No se encontró obligación cerrada para estos extras; snapshot de nombres/escudos sigue como P-07 del manual. Acta de tanda detallada fue excluida expresamente | Decidir valor antes de ampliar modelo o servicios. No alterar logotipo. Riesgo variable; estimar cada opción si se aprueba |

### Reproducción focal de F01

Se ejecutó exclusivamente el validador Laravel, en proceso configurado con
SQLite `:memory:`, sin peticiones HTTP, sin invocar Cloudinary y sin consultas
a la base. Observaciones obtenidas:

```text
actualizar_url_foto_rechazada = true
crear_archivo_foto_rechazado_por_string = true
editar_escudo_archivo_rechazado_por_string = true
crear_foto_url_admitida = true
consultas_db = 0
```

Para reproducir, contrastar las reglas exactas de F01 con
`Validator::make(['foto' => 'https://example.test/foto-ficticia.png'], reglas)`
y un `UploadedFile::fake()->create('ficticia.png', 1, 'image/png')` para las
reglas `string` de alta/escudo. Esto acredita la incompatibilidad de tipo,
no una subida real exitosa ni el funcionamiento de la cuenta Cloudinary.
La pérdida de referencia de escudo se comprobó siguiendo formulario → servicio
→ campos validados → update; no se modificó un equipo real para demostrarla.

### Alcance exacto del riesgo concurrente

Los servicios de asignación arbitral, actas, calendario, grupos, clasificación,
correcciones y eliminación ya usan transacciones/bloqueos y varias restricciones
únicas. Las cuatro carreras de 5.4 acreditan las operaciones concretas que
ensayaron, no toda la aplicación.

`PlantillaController` no comparte ese bloqueo y su tabla carece de unicidad
activa física. Además, `CampeonatoEquipoController::update` cambia activo/retirado
sin la transacción/bloqueo de campeonato usado durante generación/avance. Debe
ensayarse alta o cambio de dorsal concurrente, baja frente a evento y retiro
frente a generación/avance. **No se afirma haber observado una carrera fallida
en MariaDB durante 6.0.** No es necesario repetir las carreras de 5.4 sin cambio
en su código; estas serían pruebas nuevas enfocadas en la brecha detectada.

## 6. Decisiones deportivas y precedencia documental

| Tema | Estado real y prioridad |
| --- | --- |
| DP-01 / DP-05, empates múltiples | Cerrados para grupos por autorización posterior de 5.3: minitabla PTS/DG/GF y reevaluación del subconjunto, después liguilla confirmada. No volver a proponerlos como pendientes del motor de grupos |
| DP-02, siembra/byes | Resuelto por reglas 4.1 para directa y 5.3 para transición: prioridad por puesto y sorteo entre equivalentes, sin comparar puntos de grupos distintos |
| DP-06, publicación | `publicado` opt-in, incluso en planificación según régimen vigente; lectura pública separada y ocultamiento. La extensión a solo oficiales y a formatos nuevos aún requiere F03/D05 |
| DP-07 | Pendiente residual importante, no ausencia total de control temporal. Conservar el criterio actual hasta decisión escrita; D01 |
| DP-03, doble descalificación | Se bloquea el avance de participantes no activos; falta flujo administrativo, actor, estado y resolución. D02 requiere decisión previa |
| 3–0 y retiro/descalificación simple | Intención aprobada en MER/D-14, implementación pendiente; afecta marcador de equipo y tabla, nunca goles/asistencias individuales. No resolverlo usando eventos falsos |
| DP-04 / mínimo configurable | Requisito D-26 pendiente de valores por modalidad; no asumir 5, 7 u 11. D03 |
| Penales y prórroga | Núcleo aprobado implementado: totales distintos 0–255, motivo arbitral, partido oficial empatado y método de ronda. Prórroga usa eventos en el mismo partido; no existe una entidad de tiempo extra ni verificación automática del tiempo jugado |
| Detalle/corrección de tanda | Detalle por lanzamiento excluido de 4.1; no elevarlo a P1 sin aprobación. Corrección y excepciones necesitan procedimiento; D07 |
| Plantillas cerradas y archivo | D-12 requiere cierre; faltan transición global y excepciones posteriores al archivo. No confundir cierre individual de una inscripción con cierre del campeonato; F02 |
| Históricos sin propietario | Existen y están protegidos. Asignación manual documentada, ensayo y autorización independiente; D06 |

Los DOCX son referencia de diseño con corte anterior a varios sprints. Las
decisiones posteriores documentadas en 4.1 y 5.3 resuelven puntos de esos
documentos; un “pendiente” antiguo no anula una implementación aprobada.
Las funciones excluidas temporalmente de un sprint tampoco desaparecen del
alcance FINAL: se debe aprobar su aplazamiento si la entrega será recortada.

## 7. Seguridad y dependencias

### Controles comprobados en código y evidencias

- Rutas privadas con `auth:sanctum`; Policies verifican dueño, campeonato y
  árbitro vigente. El propietario no puede autoarbitrarse. `User` oculta
  contraseña y remember_token y aplica hash a la contraseña.
- Login/registro con sesión web y CSRF incluso sin Origin; límite 6/min;
  regeneración al autenticar e invalidación/regeneración de token al salir.
- Axios usa `withCredentials` y `withXSRFToken`. Cookie HttpOnly por defecto y
  SameSite lax; Secure depende del entorno. Configuración local HTTP no acredita
  una cookie de producción sobre HTTPS.
- Público: publicación explícita, comprobación de pertenencia, lista de campos,
  exclusión de nacimiento/email/dueños y `Cache-Control: no-store`.
- Correcciones: auditoría privada con antes/después, aprobación y consumo único;
  rollback en conflictos y conservación de IDs. La referencia polimórfica de
  auditoría no es una FK física universal, como ya advierte el MER.
- `.gitignore` excluye entornos, SQL, bases, sesiones, logs, temporales y
  evidencias privadas. No se hallaron patrones típicos de claves privadas,
  tokens GitHub o claves AWS en archivos de texto versionados inspeccionados.
- Manejo 401/403/404/409/419/422 en servicios y componentes; firmas invalidadas
  y datos retirados tras revocación en grupos. No extender esa evidencia a todos
  los formularios: la incompatibilidad F01 produce errores legítimos de API.

### Consultas actuales de avisos

Se ejecutaron **sin instalación ni actualización**:

```text
frontend: npm audit --json                          salida 0, 0 vulnerabilidades
backend:  composer audit --locked --format=json     salida 1, 9 avisos / 6 paquetes
```

El lockfile frontend auditado reúne 155 dependencias según npm. Cero avisos
conocidos no significa que el código propio o todas las dependencias sean
inmunes. El tooling npm adicional de `backend/package.json` no tiene lockfile
propio y no se instaló para forzar un audit; no está cubierto por ese resultado.

| Paquete fijado | Avisos del audit / referencia primaria | Aplicabilidad observada |
| --- | --- | --- |
| `laravel/framework 12.70.1` | CVE-2026-128083, [GHSA-63hm-g96r-2m2q](https://github.com/laravel/framework/security/advisories/GHSA-63hm-g96r-2m2q), parche 12.71.0 en rama 12 | Inyección de salida en Concurrency Process. No se encontró uso de Concurrency en `app/`. No es la concurrencia transaccional InnoDB del proyecto |
| `symfony/http-foundation 7.4.21` | CVE-2026-121619, [proxies privados](https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q); CVE-2026-118514, [HEAD y matchers](https://symfony.com/cve-2026-118514); [GHSA-3h27-hjv7-fqjx](https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx); [GHSA-5mx8-mwrp-5693](https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693). Rama 7.4 corregida en 7.4.23 | Cuatro avisos. No se encontró `private_ranges`, SecurityBundle o reglas propias con RequestMatcher en la app; deben revisar también host y servidor de entrega. No se demostró evasión de Policies Laravel |
| `symfony/http-kernel 7.4.21` | CVE-2026-123339, [GHSA-wg5q-m2ww-f3c9](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9); rama corregida 7.4.23 | Fragmentos inline/cabeceras forwarded; no se encontró ese uso en la app |
| `symfony/mime 7.4.19` | CVE-2026-45070, [inyección de cabeceras](https://symfony.com/cve-2026-45070); rama corregida 7.4.22 | Requiere nombres de parámetros MIME controlados por entrada. No se encontró construcción equivalente en el código propio |
| `symfony/routing 7.4.21` | CVE-2026-118516, [host y rutas compiladas](https://symfony.com/cve-2026-118516); rama corregida 7.4.23 | No se encontraron rutas de dominio dinámico en la API revisada; comprobar despliegue, no declarar explotación demostrada |
| `symfony/yaml 7.4.21` | CVE-2026-123792, [GHSA-hwfg-qwvh-2wfx](https://github.com/symfony/symfony/security/advisories/GHSA-hwfg-qwvh-2wfx); rama corregida 7.4.23 | Está en `packages-dev`; no se encontró parser YAML de entrada de usuario en la app. No confundirlo con dependencia productiva directa |

El desglose confirmado es de nueve avisos en seis paquetes: ocho avisos en
paquetes de ejecución y uno en YAML, de desarrollo. Las severidades del feed
incluyen valores sin clasificación; no se inventa una gravedad para ellos.
No se debe ejecutar `composer update` global ni cambiar mayores sin aprobación;
la corrección propuesta debe resolver el conjunto compatible que indique el solver
y volver a comprobar el lockfile y las regresiones afectadas.

El entorno local usa PHP **8.2.12** y MariaDB **10.4.32**, comprobados en CLI y
lectura de identidad. La serie MariaDB 10.4 terminó mantenimiento comunitario
en junio de 2024 según su [documentación oficial](https://mariadb.com/docs/release-notes/community-server/old-releases/10.4).
La [política PHP](https://www.php.net/supported-versions.php) distingue soporte
de la rama y revisión instalada: no dar por actualizado un 8.2.12 por pertenecer
a una rama con soporte. No se evaluaron todos los CVE de XAMPP/Windows ni se
modificó la instalación que sirvió de referencia a los ensayos anteriores.

### Pint

Se reprodujo `php vendor/bin/pint --test` sin modificar archivos. Fallos:

- `backend/config/services.php`: indentación del bloque Cloudinary y espacios
  en línea vacía; archivo versionado idéntico a HEAD.
- Cuatro scripts anteriores ignorados: uno de aplicación puntual 4.3 y tres
  herramientas privadas 5.5/5.6. Sus nombres/rutas privadas no deben añadirse a
  Git para corregir el indicador de Pint.

Los tres PHP nuevos de 5.7 habían pasado Pint. Proponer una corrección de formato
de `services.php` y una definición explícita del alcance de Pint en Q01; no
declarar el formato general aprobado mientras persista este resultado.

## 8. Experiencia de usuario y diseño

La navegación privada se adapta al formato; reutiliza `CuadroEliminacion`,
`PenalesForm`, actas, confirmaciones y auditoría. Los grupos no implementan un
motor deportivo en React. El branding y logotipo existentes permanecen intactos.

Los componentes revisados incluyen etiquetas de formularios, botones con nombre,
saltos al contenido, regiones desplazables, diálogo nativo, bloqueo durante
envío y restauración de foco. Sidebar móvil contempla Escape, recorrido de foco
e `inert` en el contenido. Son bases de accesibilidad, no una certificación WCAG.

**Defectos funcionales observados en código:** F01 (guardado de imágenes) y F03
(el enlace Tabla de un campeonato público de grupos/directa conduce a un
contrato incompatible). Además `DetallePublicoPage` muestra nombres internos
`grupos_eliminacion`/`eliminacion_directa` en lugar de etiquetas legibles: P2,
esfuerzo aproximado menor de medio día, dentro de F03/Q02.

**Límites de la verificación visual existente:** móvil de grupos se ensayó en
iframe, sin teléfono físico; el catálogo público temprano se revisó sobre todo
vacío. La evidencia 5.7 no mostró desbordamiento del documento móvil ni botones
inaccesibles en sus secciones; tablas/navegación/cuadro desplazan dentro de su
región. No se inventa un defecto de contraste o de logotipo que no se haya medido.

**Mejoras opcionales:** homogeneizar progresivamente CSS heredado/componentes y
optimizar ilustraciones grandes sin alterar el logotipo oficial. No son razón
para reescribir pantallas que ya funcionan. La lectura con zoom, teclado,
lector de pantalla y navegador móvil físico debe planificarse en Q02.

## 9. Documentación que debe ponerse al día

| Documento | Divergencia comprobada | Acción futura |
| --- | --- | --- |
| `README.md` | Afirma que login/registro son demostrativos y que aún no existe repositorio Git; contradice controladores y HEAD | Reescribir estado actual, arranque, cuentas de demo y enlaces |
| Enlaces del README | `docs/fase-2-resultados.md`, `docs/estado-proyecto.md` y `docs/REVISION_FINAL_2026-11-01.md` no existen en este checkout | Sustituir por documentación presente o recuperar con revisión, sin enlaces rotos |
| Manual Técnico consolidado | Presenta Sanctum, Policies, rutas públicas, grupos y eliminación como diseño pendiente; tabla de entidades/estado y versiones no reflejan 28 tablas | Actualizar matriz implementado/probado/pendiente, rutas, esquema físico, instalación y límites |
| MER consolidado | Propuesta pendiente de verificación; no refleja la evolución física posterior, incluidos expedientes de desempate y decisiones 4.1/5.3 | Mantener diseño y añadir correspondencia con migraciones reales; no reescribir históricos |
| `backend/tests/README.md` | Describe peticiones del kernel como HTTP real y lista solo pruebas iniciales | Separar kernel, red, navegador, simulación y concurrencia; enlazar laboratorios actuales |
| `frontend/tests/README.md` | Explica la base inicial de Vitest, pero necesita índice actual de suites/recorridos | Mantener que SSR/servicios no son interacción DOM y referenciar total y alcance actuales |
| Informes 1.x–5.6 | Contienen pendientes y conteos válidos en su fecha, algunos ya superados | Conservar como evidencia histórica y agregar un índice de estado actual |
| `GRUPOS_5_5.md` y evidencia 5.5 | Preparación previa a autorización de 000200, no estado actual | Conservar expresamente como preparación; estado aplicado consta en 5.7 y contexto autorizado |
| Documentos de entrega | Falta guía vigente de roles, recorrido SENA, restauración y criterios de aceptación | Producir después de cerrar requisitos; nunca incluir claves, cookies o SQL en el repo |

No se editaron estos documentos maestros durante la auditoría. El presente
informe es un mapa actual de diferencias, no una autorización para migrar.

## 10. Ruta de sprints propuesta

Cada sprint necesita alcance autorizado y criterios de salida. Los cambios de
datos principales y la actualización de dependencias requieren sus propios
ensayos y aprobación; el plan no los ejecuta.

| Sprint sugerido | Alcance acotado | Dependencias / criterio de salida | Esfuerzo orientativo |
| --- | --- | --- | --- |
| 6.1 · Estabilización de seguridad y contratos | Triaje y mantenimiento compatible S01; F01; formato versionado Q01. Acordar objetivo local o público | Cero avisos aplicables sin tratamiento, o excepciones documentadas; imágenes coherentes, regresiones focales y build. Resolver compromiso de runtime antes de hosting | 3–5 días; runtime aparte si procede |
| 6.2 · Integridad de plantillas y ciclo de campeonato | I01 y F02; cerrar DP-07 antes de tocar precisión; acordar mínimos D03 | Dos altas concurrentes no duplican jugador/dorsal; cierre impide ediciones ordinarias; bajas/eventos y retiro/generación coherentes. Carreras nuevas focales, sin repetir todas las anteriores | 5–8 días después de decisiones |
| 6.3 · Resoluciones deportivas administrativas | D02, mínimo pendiente de 6.2 y D07 solo si se aprueba obligatorio | Autoridad de doble descalificación escrita; 3–0 sin eventos falsos; anteriores intactos; grupos y cuadro no se corrompen. Ensayo MariaDB nuevo solo para este cambio | 5–8 días; 2–4 extra si se incluye corregir tandas |
| 6.4 · Consulta pública y cierre deportivo del alcance | F03/D05, etiquetas; resolver alcance de desempate absoluto en liga D04 | Público de los tres formatos, sin privados; 404 tras ocultar; provisional/oficial claro. Un campeón no se determina por nombre o ID | 3–6 días, según decisión sobre liga |
| 6.5 · Entrega y sustentación | DOC01, Q02, OPS01 y automatización mínima útil Q03; históricos D06 en carril separado | Maestros coherentes, guion con datos ficticios, restauración ensayada, revisión móvil/teclado y lista de aceptación firmada; autorización de commits/publicación por separado | 3–5 días, más trámite humano de históricos |

Los rangos dependen de decisiones aún abiertas; no garantizan una fecha de
entrega. Si el objetivo inmediato es **solo una demostración del alcance 5.7**,
puede aprobarse una entrega parcial con 6.1 y un guion/documentación explícitos,
dejando 6.2–6.4 como backlog aceptado. Eso no habilita afirmar cumplimiento de
todos los requisitos del Manual ni operación pública irrestricta.

La asignación de dueños históricos no debe bloquear la preparación de una demo
ficticia ni mezclarse con una migración estructural. Se tramita cuando exista
mapa de propietarios acreditado; sin él, conservar NULL y sus restricciones.

## 11. Criterios para declarar listo

1. Alcance firmado: requisitos completos frente a aplazamientos explícitos;
   decisiones DP-03/DP-04/DP-07 y excepciones documentadas donde correspondan.
2. Defectos F01 corregidos y público acorde a los formatos que se ofrecen.
3. Integridad de plantillas/cierre ensayada y carreras nuevas relevantes aprobadas.
4. Dependencias y runtime adecuados al destino; ninguna excepción de seguridad
   sin responsable, justificación y restricción de exposición.
5. Regresión backend/frontend, Pint sobre el alcance versionado, lint y build
   aprobados después de los cambios; selección de HTTP y navegador proporcional
   al código modificado, sin repetir pruebas costosas por rutina.
6. Principal respaldada, recuperación ensayada y ningún histórico apropiado o
   reescrito sin aprobación. SQL y credenciales siempre fuera del repositorio.
7. README/MER/Manual/rutas coincidentes; guion de administrador, árbitro y
   espectador con datos ficticios, y capturas sin información privada.
8. Revisión humana del equipo para SENA: cada regla, permiso, transacción,
   limitación y evidencia debe poder explicarse sin atribuir simulaciones a red real.

## 12. Estado final de esta auditoría

Los siete archivos de 5.7 permanecen sin cambios y respaldados. Se añade solo
`AUDITORIA_CIERRE_6_0.md`: ocho archivos nuevos pendientes en total y ningún
archivo productivo modificado. Índice sin preparar; HEAD permanece en
`adda47717a205663aa546a47e8b79a013a07d4cd`. Sin commit ni push.

La consulta principal de 6.0 utilizó una transacción PDO **READ ONLY** y devolvió
únicamente conteos, sin identidades: 3 campeonatos (2 sin dueño), 8 equipos
(8 sin dueño), 3 jugadores (3 sin dueño), 19 migraciones. La comparación íntegra
de 28 tablas/51 filas corresponde a 5.7; no se repitió ni se presenta como una
nueva instantánea completa de 6.0. No se ejecutó ninguna migración, actualización
o eliminación en `beesocc_v2`.
