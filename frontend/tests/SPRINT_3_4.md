# Sprint 3.4 — Interfaz de resultados oficiales

Se consultaron el MER, Manual Técnico, Policies, servicios y contratos del Sprint 3.1.
Se conservan DP-07, los históricos y el régimen público acordado. Sin cambios backend ni migraciones.

- Tarjetas privadas y detalle distinguen oficial, provisional e histórico sin oficialización.
- Finalizar confirma la oficialización; las escrituras ordinarias permanecen bloqueadas tras finalizar.
- Administrador: detalle de expedientes, aprobación/rechazo y auditoría de solo lectura.
- Árbitro: motivo de solicitud y lote ordenado de registro, corrección de minuto y eliminación.
- Solo el solicitante de un expediente aprobado para eventos puede preparar su aplicación.
- Confirmaciones, bloqueo de doble envío, manejo de 401/403/404/409/422 y recarga desde Laravel.
- Las estadísticas existentes se consultan nuevamente al abrir o actualizar su pantalla; React no recalcula resultados.

Endpoints existentes: GET `/partidos/{id}`, `/marcador`, `/eventos`, `/plantillas-arbitrales`,
`/correcciones`, `/auditoria`; POST `/partidos/{id}/finalizar`, `/partidos/{id}/correcciones`;
PATCH `/correcciones/{id}/autorizar`; POST `/correcciones/{id}/aplicar`.
Se reutilizan Sanctum, cookies/CSRF y autorización contextual, sin ampliar la API.

Automatizadas: backend SQLite en memoria, **125 pruebas y 2.404 aserciones**;
frontend **152 pruebas** (47 servicios y 105 unitarias, 31 nuevas); lint y build aprobados.
Archivos: componentes ResultadoOficial, PanelCorrecciones, OperacionesCorreccion e HistorialAuditoria;
servicio correccionService, utilidades correcciones; ajustes de PartidoDetallePage, PartidoCard,
EventoForm, Confirmacion, partidoService, CSS y escenarios/pruebas.

Navegador con respuestas **simuladas**, sin Laravel/MySQL: solicitud y aprobación; sustituto bloqueado;
lote de tres operaciones, marcador 1–0 a 0–0, consumo único; auditoría antes/después; rechazo;
422 conservando el motivo, 409 recargando; revocación y doble clic con un solo envío.
Marco móvil de 390 px: contenido útil de 375 px, sin desbordamiento; formulario y diálogo comprobados.
Se completó el adaptador del escenario arbitral anterior para las nuevas consultas y finalización.

Para reproducir: `npm run dev` y abrir `/tests/browser/resultados-oficiales.html` o
`/tests/browser/resultados-oficiales-movil.html`. Usar los botones de cambio de usuario y errores simulados.
Pendiente: flujo React → Sanctum → MariaDB con cuentas ficticias en laboratorio aislado,
incluida la consulta real de estadísticas después de una corrección. No se atribuye esta integración a las pruebas simuladas.
Sin cambios en datos históricos, commits ni push.
