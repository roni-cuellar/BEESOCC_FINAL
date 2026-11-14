# Sprint 2.3A — Control arbitral

## Alcance y referencias

Se revisaron el MER consolidado y el Manual Técnico de `docs/`, las Policies,
modelos, rutas, controladores y pruebas existentes. Se completó la implementación
parcial encontrada en el árbol de trabajo. No se modificó React, migraciones,
datos históricos ni configuración de MySQL. No se realizaron commits ni push.

## Archivos intervenidos

- `app/Policies/PartidoPolicy.php`: permiso `arbitrar`, lectura compartida y alias.
- `app/Policies/EventoEstadisticoPolicy.php`: lectura administrativa independiente de escritura.
- `app/Policies/PlantillaPolicy.php`: utiliza el permiso arbitral.
- `app/Services/ControlPartidoService.php`: revalida `arbitrar` dentro de la transacción.
- `app/Http/Controllers/Api/PartidoController.php`: detalle y marcador con `view`; estados con `arbitrar`.
- `app/Http/Controllers/Api/EventoEstadisticoController.php`: autorización arbitral,
  lectura independiente, validación temporal y rechazo de IDs alternativos.
- `app/Http/Controllers/Api/ArbitrajeController.php`: consulta de plantillas con `arbitrar`.
- `tests/Feature/ControlArbitralTest.php`: diez casos nuevos de autorización y validación.
- `tests/Feature/BackendTestCase.php`: peticiones deportivas con un árbitro distinto,
  asignado mediante la API; restaura al organizador al terminar cada petición.
- `tests/Feature/ApiDeportivaTest.php`, `ApiResultadosTest.php`,
  `AutorizacionDeportivaTest.php`, `FlujoCompletoTest.php` y
  `AsignacionArbitroTest.php`: adaptación de las expectativas anteriores a la
  separación entre organización y acta. No se desactivaron pruebas ni middleware.
- Este documento.

## Autorización

Las rutas conservan `auth:sanctum`: invitado obtiene 401. La Policy utiliza
`User::esArbitroDe`: asignación del campeonato del partido, inicio no futuro,
`fecha_fin` nula y usuario distinto del propietario. Revocados, reemplazados y
árbitros de otra competición obtienen 403. No se confía en el perfil elegido en React.

Se exige una pareja local/visitante distinta y coherente con campeonato y
propietario. La plantilla y el jugador deben pertenecer a los recursos del
organizador y a uno de los equipos participantes.

Las escrituras bloquean primero el campeonato y luego el partido y vuelven a
autorizar. Asignación, reemplazo y revocación utilizan el mismo bloqueo del
campeonato: una escritura posterior a una revocación confirmada no conserva
la autorización de una comprobación anterior. SQLite no demuestra el bloqueo
concurrente de InnoDB; esa comprobación queda para un entorno MySQL de pruebas.

## Estados existentes

Se conserva `PATCH/PUT /api/partidos/{id}` para no cambiar el contrato de React.
Enviar `estado` activa el flujo arbitral; mezclarlo con campos organizativos da 422.

| Origen | Destino | Actor |
| --- | --- | --- |
| programado | en_curso | Árbitro vigente |
| en_curso | suspendido o finalizado | Árbitro vigente |
| suspendido | en_curso | Árbitro vigente |
| programado, en_curso o suspendido | cancelado | Administrador propietario |

Finalizado y cancelado no tienen salida. Una transición incompatible da 409;
valores de entrada no admitidos dan 422. No se inventaron estados ni se permitió
volver a programado. El administrador mantiene calendario, participantes,
fecha y lugar, con las restricciones existentes. La reprogramación de un
suspendido conserva el mismo partido y estado; el árbitro lo reanuda.

## Eventos y marcador

POST `/api/partidos/{id}/eventos` admite `gol`, `autogol`, `asistencia`,
`amarilla`, `roja` y `falta`, exclusivamente en curso. Se valida participante del
partido y plantilla del equipo. En autogol el participante es el beneficiado y
la plantilla pertenece al rival del mismo encuentro.

La asistencia necesita un gol normal del mismo equipo y partido; no permite
autoasistencia ni duplicación. Los minutos válidos se mantienen entre 0 y 150.
Se rechazan identificadores de partido, jugador o equipo alternativos al contrato.

PATCH `/api/eventos/{id}` solo corrige el minuto y DELETE elimina un evento,
ambos en curso y por árbitro vigente. Antes de borrar un gol debe eliminarse su
asistencia. No se permite editar ni eliminar tras finalizar o suspender.

El marcador sigue derivándose de goles y autogoles del participante beneficiado;
no se escriben contadores paralelos. Se conservan los servicios existentes de
tabla y estadísticas, incluidos traslados e historial.

La elegibilidad temporal se comprueba con `fecha_hora` del partido o la fecha
de registro si no está programada: ingreso posterior o salida anterior impiden
registrar. No se alteran eventos históricos por cambios posteriores de plantilla.

## Compatibilidad y decisión pendiente del MER

`gestionarActa` se conserva como alias de `arbitrar`. Ya no otorga al administrador
permiso temporal de escritura. El backend usa directamente `arbitrar`.
Conservar nombres y URLs evita romper contratos HTTP; el antiguo formulario
administrativo puede seguir visible, pero sus escrituras arbitrales reciben 403.
No existe un bypass administrativo condicionado por ausencia de árbitro.

El MER deja abierta DP-07: instante de cierre de `fecha_salida` con precisión
DATE durante el mismo día. No se resuelve esa decisión en este sprint: se
conserva la aceptación previa del día exacto de salida y solo se rechazan días
claramente anteriores/posteriores al intervalo. Debe confirmarse DP-07 antes
de exigir una nueva semántica de cierre. La fecha de registro como respaldo
mantiene el contrato existente que permite partidos sin `fecha_hora`.

Los resultados oficiales, autorizaciones de corrección, auditoría avanzada y
requisitos globales de inicio oficial del campeonato quedan fuera del alcance.

## React — Sprint 2.3B

1. Desde Mis arbitrajes, cargar GET `/api/mis-arbitrajes/{campeonato}/partidos`.
2. Cargar detalle, marcador y eventos mediante GET `/api/partidos/{id}`,
   `/marcador` y `/eventos`; cargar jugadores desde `/plantillas-arbitrales`.
3. Conectar iniciar/suspender/reanudar/finalizar al PATCH del partido.
4. Conectar eventos a POST, corrección de minuto a PATCH y eliminación a DELETE.
5. Retirar controles del acta del panel administrativo; conservar organización
   y cancelación. Seleccionar perfil nunca concede autorización.
6. Tratar 401 como sesión requerida, 403 como permiso ausente/revocado,
   409 como conflicto de estado y 422 como validación. Refrescar listas al cambiar
   asignación. Después de migrar las referencias, retirar el alias `gestionarActa`.

## Validación

Ejecutar desde `backend`: `php vendor/bin/phpunit`.
`BackendTestCase` verifica entorno testing, ausencia de caché y DB_URL, y conexión
SQLite exclusivamente `:memory:` antes de crear el esquema efímero. No se
ejecuta `migrate:fresh`, ni migraciones o limpieza sobre MySQL.

La suite cubre autorizado, revocado, reemplazado, asignación futura, otra liga,
administrador, invitado, IDs manipulados, estados inválidos, eventos incorrectos,
elegibilidad, marcador, asistencias y estadísticas. Las regresiones conservan
autenticación, propiedad, publicación, asignaciones, calendarios y flujo completo.

Resultado del 14 de noviembre de 2026: **106 pruebas y 1.988 aserciones correctas**.
PHPUnit 11.5.57, PHP 8.2.12. Se comprobó también el formato con Laravel Pint.
El aislamiento de ejecución de esta sesión hizo que PHP devolviera
`is_readable=false` para archivos que sí podía leer; la suite se ejecutó fuera
de ese aislamiento, conservando las comprobaciones de SQLite en memoria.
