# Sprint 4.4 — Interfaz privada de eliminación directa

Implementado sobre `develop`, sin commits ni push. Se consultaron el MER y el
Manual Técnico consolidados de `docs/`, el análisis, informe y reglas aprobadas
del Sprint 4.1 y el contrato actual de FaseController, Policies y servicios.
Las reglas aprobadas y el código Laravel prevalecen sobre las propuestas antiguas
que los documentos identifican expresamente como pendientes.

## Funcionalidades y archivos

- `src/pages/EliminacionPage.jsx`: fase, configuración, confirmaciones, acciones y consulta privada.
- `src/components/ConfiguracionEliminacion.jsx`: todas las siembras explícitas, modalidades y desempates por ronda; vista previa recibida de Laravel.
- `src/components/CuadroEliminacion.jsx`: rondas conectadas, procedencias, descansos sin partidos ficticios, resultados individuales, oficialización y clasificados recibidos del servidor.
- `src/components/PenalesForm.jsx`: totales por plaza, motivo y confirmación arbitral. No permite editar tandas existentes.
- `src/stores/useEliminacionStore.js`: lectura aislada por pantalla, prevención de respuestas antiguas/doble envío, invalidación de vista previa y recarga tras cambios o conflictos.
- `src/services/faseService.js`, `src/utils/eliminacion.js` y `src/styles/eliminacion.css`: contratos Axios existentes, presentación y diseño responsive con colores y logotipo oficiales conservados.
- Navegación integrada en rutas privadas, Sidebar, dashboard, partidos, calendario y arbitrajes. La tabla privada por puntos redirige al cuadro en eliminación directa.
- `HistorialAuditoria.jsx`: responsables, fecha, motivo, siembra, plazas, tandas y antes/después. Reutiliza el historial del acta para las correcciones.
- `PartidoForm.jsx` y detalle del acta: los partidos del cuadro conservan jornada/equipos; solo se ofrece editar fecha/lugar y no se ofrece eliminarlos.

## Contrato y permisos

Se conserva el cliente Axios compartido con `withCredentials` y `withXSRFToken`.
No se cambian sesiones Sanctum, cookies, CSRF, credenciales ni reglas Laravel.

| Método | Ruta bajo `/api` | Uso |
| --- | --- | --- |
| GET / POST | `/campeonatos/{id}/fases` | Listar / crear fase con nombre y tipo eliminacion |
| GET | `/fases/{id}` | Cuadro y relaciones |
| GET | `/fases/{id}/auditoria` | Auditoría privada |
| POST | `/fases/{id}/previsualizar-llaves` | Siembra y todas las rondas |
| POST | `/fases/{id}/generar-llaves` | Mismo envío previsualizado y confirmacion HMAC del servidor |
| POST | `/llaves/{id}/avanzar` | Sin ganador ni estado enviados desde React |
| POST | `/llaves/{id}/penales` | Totales 1/2 distintos, enteros 0–255 y motivo |
| GET | `/partidos/{id}/marcador` | Resultados calculados por Laravel |

El propietario configura, genera y solicita avance; el árbitro entra mediante
`/mis-arbitrajes/:ligaId/cuadro`, sin consultar endpoints administrativos de liga
o inscripciones. Laravel revalida siempre los permisos. React solo filtra la
presentación de acciones: no decide empates, ganadores, globales ni gol visitante.
La cantidad de rondas del formulario se obtiene del número de inscritos; cruces,
descansos, clasificación y creación de partidos proceden del backend.

Los errores 401/403/404 retiran los datos privados. Los 409 recargan el cuadro,
descartan la vista previa y cierran la confirmación obsoleta. Los 422 muestran
la validación del servidor sin fingir éxito. Un marcador no disponible nunca
se representa como cero. Los 401 mantienen el cierre de sesión existente.
Actualizar o recuperar el foco vuelve a consultar cuadro, marcadores y auditoría,
incluidas las correcciones aplicadas desde el acta. No hay caché pública del cuadro.

## Verificación ejecutada

- Backend: PHPUnit, **141 pruebas y 2.824 aserciones**, todas aprobadas.
  Se ejecutó con PHP de XAMPP fuera del sandbox debido al bloqueo de lectura de
  `vendor/autoload.php`. Las guardas existentes exigen `testing`, sin caché y
  SQLite `:memory:` antes de cualquier escritura. No se conectó a MariaDB.
- Frontend: **195 pruebas**: 148 Vitest y 47 del runner Node; todas aprobadas.
  Incluyen 43 pruebas nuevas de contratos, configuración, previsualización,
  generación/token, descansos, resultados y oficialización, penales, avance,
  auditoría, permisos, errores, recarga y respuestas fuera de orden.
- `npm run lint`, `npm run build` y revisión de diferencias aprobados.
- Interacción real de React en navegador, **con API simulada** mediante
  `tests/browser/eliminacion.html`: crear fase, seleccionar siembra, configurar
  ida/vuelta, previsualizar descanso, confirmar generación, consultar cuadro,
  rechazo 409 de avance con empate sin tanda, cambiar al recorrido arbitral,
  certificar 5–4 con motivo, volver al propietario, avanzar y ver la plaza
  siguiente actualizada. Revocación simulada 403 elimina cuadro e historial.
- Responsive comprobado a **390 × 844**: filtro por ronda y desplazamiento
  horizontal dentro del cuadro, sin desbordamiento horizontal del documento.

Las pruebas de frontend usan adaptadores simulados y renderizado HTML; no son
integración HTTP con Laravel. El recorrido de navegador también usa respuestas
ficticias y no valida por sí mismo las reglas deportivas ni Sanctum real.
Las 108 comprobaciones HTTP/MariaDB del Sprint 4.2 son evidencia previa y no
se contabilizan como ejecutadas en este sprint.

## Alcance y pendientes

No se cambiaron migraciones, tablas ni datos históricos; tampoco backend, grupos,
DP-07, reglas aprobadas, logotipo, dependencias o credenciales. Las correcciones
de eventos siguen el expediente existente y se reflejan al recargar desde Laravel.
No se añaden procedimientos de retiro/descalificación o edición de tandas.

Queda pendiente un recorrido de las nuevas pantallas contra Laravel/MariaDB con
sesiones reales en un laboratorio aislado. No se deben usar campeonatos históricos
de `beesocc_v2` para generar cuadros o probar escrituras.

Para repetir la revisión visual: iniciar Vite en localhost y abrir
`/tests/browser/eliminacion.html`. El adaptador del escenario rechaza rutas no
implementadas y nunca envía peticiones a Laravel. No se incluye en el build de producción.
