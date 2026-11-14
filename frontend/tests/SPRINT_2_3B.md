# Sprint 2.3B — Panel arbitral React

Implementado en `develop`, sobre los cambios previos presentes en el árbol de
trabajo. Se revisaron MER, Manual Técnico e informe 2.3A. No se cambiaron backend,
migraciones, dependencias ni datos de MySQL; no se realizaron commits ni push.

## Archivos del sprint

- `src/pages/MisArbitrajesPage.jsx`: enlace del campeonato a sus partidos.
- `src/pages/PartidosArbitrajePage.jsx` (nuevo): lista privada, estados, fechas,
  marcador y comprobación periódica de la asignación.
- `src/pages/PartidoDetallePage.jsx`: reutilizado para consulta administrativa
  y control arbitral; carga de plantillas privada, operaciones y gestión de errores.
- `src/routes/AppRoutes.jsx`: dos rutas privadas bajo `/mis-arbitrajes/:ligaId/partidos`.
- `src/services/arbitroService.js`: consultas privadas de partidos y plantillas.
- `src/services/api.js`: nombre comprensible para la validación de `plantilla_id`.
- `src/components/PartidoCard.jsx`: navegación hacia el contexto arbitral.
- `src/components/AccionesEstadoPartido.jsx` (nuevo) y `src/utils/partidos.js`:
  acciones visibles según contexto y transiciones actuales de Laravel.
- `src/components/EventoForm.jsx` y `src/utils/eventos.js` (nuevo): seis tipos
  de evento, selección de jugadores y goles relacionados, protección de envío.
- `src/components/Confirmacion.jsx`: bloqueo inmediato frente a doble clic.
- `tests/unit/arbitros.test.jsx`: actualización del enlace esperado.
- `tests/unit/control-arbitral.test.jsx` (nuevo): 27 casos adicionales.
- `tests/browser/control-arbitral.html` y `.jsx` (nuevos): escenario de navegador
  con adaptador Axios exclusivamente en memoria; no forma parte del build publicado.
- Este informe.

## Funcionamiento

Mis arbitrajes → Controlar partidos → Ver partido y eventos. El árbitro ve
equipos, fecha, lugar, estado, marcador e historial. Las acciones disponibles son:

| Estado | Árbitro | Administrador |
| --- | --- | --- |
| programado | Iniciar | Cancelar y organizar |
| en_curso | Suspender, finalizar y gestionar eventos | Cancelar y organizar |
| suspendido | Reanudar | Cancelar y reprogramar |
| finalizado/cancelado | Consulta | Consulta y eliminación si Laravel la permite |

Se confirma cada cambio de estado y eliminación. En curso se pueden registrar
goles, autogoles, asistencias, amarillas, rojas y faltas, corregir minutos y
eliminar eventos. Cada operación exitosa recarga detalle, marcador, eventos
y plantillas desde la API. No hay marcador ni estadísticas calculados en React.

El autogol selecciona el equipo beneficiado y los jugadores del rival. La
asistencia solo ofrece goles normales del participante, sin otra asistencia y
de otro jugador. Los jugadores se filtran por el intervalo que ya valida Laravel;
DP-07 continúa pendiente: no se cambió la aceptación del día exacto de salida.

El administrador conserva calendario, programación y participantes con sus
restricciones anteriores. Su detalle permite consultar eventos, pero ya no
muestra registro, corrección, eliminación ni estados arbitrales. `gestionarActa`
permanece en Laravel como alias restringido; React no utiliza ese nombre.

Laravel comprueba sesión y asignación en cada consulta y escritura. En la UI,
401 conduce al flujo de sesión existente; 403/404 retiran detalle, plantillas,
eventos y diálogos; 409 muestra el conflicto y recarga el estado; 422 conserva
la validación en el formulario. No se reintentan automáticamente las escrituras.
La asignación se comprueba cada 30 segundos y al recuperar el foco. Si se revoca
entre comprobaciones, la siguiente escritura igualmente recibe 403 del servidor.
Un bloqueo inmediato mediante referencia y botones deshabilitados evita dobles envíos.

## Endpoints utilizados

- GET `/api/mis-arbitrajes`.
- GET `/api/mis-arbitrajes/{campeonato}/partidos`.
- GET `/api/partidos/{partido}`, `/marcador`, `/eventos` y `/plantillas-arbitrales`.
- PATCH `/api/partidos/{partido}` con solamente `estado` para arbitrar.
- POST `/api/partidos/{partido}/eventos`.
- PATCH `/api/eventos/{evento}` con solamente `minuto` y DELETE del mismo recurso.

No fue necesario añadir endpoints ni ampliar permisos de lectura del campeonato.

## Resultados comprobados — 14 de noviembre de 2026

| Comprobación | Resultado |
| --- | --- |
| `php vendor/bin/phpunit`, desde backend | 106 pruebas, 1.988 aserciones aprobadas, SQLite `:memory:` |
| `npm test`, desde frontend | 47 pruebas de servicios aprobadas |
| `npm run test:unit` | 71 pruebas aprobadas |
| `npm run lint` | Aprobado |
| `npm run build` | Aprobado |

Las pruebas frontend verifican contratos con adaptador simulado, presentación
estática, selección de jugadores/asistencias, rutas protegidas y acciones por
estado. Los permisos reales y cálculos deportivos los cubre la suite Laravel.

También se ejecutó una revisión asistida en Brave contra el escenario aislado:
navegación desde Mis arbitrajes, confirmación de inicio, registro de los seis
eventos, marcador 2–0 tras gol y autogol, un solo POST ante doble clic,
recuperación de 409, suspensión/reanudación/finalización y retirada del acta por
revocación simulada. Al suspender/finalizar desapareció el formulario.
En viewport de 390 × 844 px se navegó, inició y registró un gol; se comprobó
ausencia de desbordamiento horizontal y marcador 1–0. Esta revisión utiliza
respuestas simuladas y no constituye una prueba integral contra MySQL/Sanctum.

Para repetir la revisión aislada: iniciar `npm run dev` y abrir
`/tests/browser/control-arbitral.html`. Recargar reinicia los datos. Los botones
superiores permiten simular revocación y conflicto 409; usar las herramientas
responsive del navegador para comprobar el diseño móvil.

## Validación manual pendiente con Laravel y sesiones reales

Utilizar un entorno de pruebas autorizado y datos desechables. No aplicar estos
pasos sobre datos históricos o MySQL real sin autorización.

1. Con el organizador, preparar un campeonato, dos equipos, plantillas, partido
   y asignación vigente a otro usuario. Comprobar que conserva programación y
   cancelación y que no aparecen controles del acta en el detalle administrativo.
2. Iniciar sesión como árbitro: Mis arbitrajes → Controlar partidos → detalle.
   Confirmar equipos, fecha, lugar, estado y marcador 0–0.
3. Iniciar y registrar los seis eventos; verificar jugador rival en autogol,
   gol relacionado en asistencia y actualización del marcador sin recargar el navegador.
   Hacer doble clic y comprobar un único evento.
4. Corregir minuto y eliminar un evento. Para eliminar un gol, eliminar antes
   su asistencia. Suspender: el formulario debe desaparecer. Reanudar y finalizar:
   no deben quedar acciones de escritura.
5. En otra sesión, revocar o reemplazar al árbitro. Volver al panel o esperar
   hasta 30 segundos: debe retirarse el acta. Una operación anterior a ese
   refresco debe ser rechazada por Laravel. Probar también un campeonato ajeno.
6. Abrir URLs con partido/campeonato incorrectos; comprobar 403/404 y ausencia
   de controles. Expirar sesión y comprobar regreso al acceso.
7. Repetir en teléfono o emulación de 390 px: formularios, selectores,
   confirmaciones, teclado, desplazamiento y ausencia de scroll horizontal.

Quedan pendientes estos pasos con sesiones reales, la comprobación en teléfono
físico y la concurrencia real de InnoDB. No se implementan resultados oficiales,
formatos nuevos ni una resolución de DP-07.
