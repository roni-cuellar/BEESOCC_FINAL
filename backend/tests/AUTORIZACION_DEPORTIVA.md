# Sprint 1.4 — autorización de recursos deportivos

## Aplicación verificada en MySQL el 12 de noviembre de 2026

Rama develop. Se creó fuera del repositorio el respaldo
C:/Users/admin/Documents/BACKUPS_BEESOCC/beesocc_sprint_1_4_2026-11-12_191634_fb3db1.sql
de 24.338 bytes. mysqldump terminó con código 0; se verificaron el marcador final
y las definiciones de las 18 tablas. No se mostró contenido privado ni credenciales;
no se acredita restauración del respaldo.

Se aplicó únicamente la migración de catálogos mediante --path específico.
migrate:status confirmó Ran, lote 6; personal_access_tokens permanece Pending.
Ambas FK administrador_id son nullable y apuntan a users.id con DELETE RESTRICT.
Se compararon en memoria todas las filas de las tablas existentes, exceptuando
solo el registro técnico de migraciones y las columnas recién agregadas: datos
y relaciones anteriores idénticos. Hay 8 equipos y 3 jugadores, todos con dueño
NULL, y siguen los dos campeonatos sin propietario. No hubo asignación automática
al legado, altas de prueba ni otras modificaciones de datos históricos.

La asignación de dueño en altas y la reutilización entre ligas del mismo propietario
se verificaron en SQLite en memoria, NO creando usuarios/campeonatos en MySQL.
La prueba nueva también rechaza reutilización de esos catálogos por otra cuenta.

## Alcance y decisiones

Referencias: docs/BEESOCC_FINAL_03_Modelo_Entidad_Relacion_Consolidado.docx,
R01–R03, I02, I05, I06, I08, I10 y etapa M1; y
docs/BEESOCC_FINAL_Manual_Tecnico_Consolidado.docx, RF-04, D-10, tabla 24 y P-06.

El usuario autorizó dos decisiones explícitas durante este Sprint:

1. Preparar las FK nullable administrador_id en equipos y jugadores, sin aplicarlas
   en MySQL; bloquear el legado sin dueño. No se deduce propiedad de una inscripción
   ni se asigna al primer usuario registrado. Estas columnas son necesarias para
   proteger catálogos sin vínculos, tal como requiere el MER.
2. Permitir temporalmente eventos y estados solo al propietario para conservar los
   flujos 2.0. Es una excepción autorizada al MER I05/I06 y a la tabla 24 del Manual,
   que reservan esas acciones al árbitro. No se implementaron roles ni asignaciones.

PartidoPolicy::gestionarActa concentra el permiso temporal para eventos y cambios
de estado. PlantillaPolicy::participarEnActa comprueba coherencia para un partido
sin exigir permiso de editar la plantilla. Al incorporar arbitraje se sustituye la
regla del actor en gestionarActa y se conservan las comprobaciones de pertenencia.
No se añadió una prohibición permanente de registrar eventos a otros perfiles.

## Relaciones y autorizaciones

| Recurso | Propiedad y coherencia |
| --- | --- |
| Equipo / Jugador | administrador_id asignado mediante la relación del usuario autenticado; no está en fillable |
| Inscripción | CampeonatoPolicy + propiedad del equipo, incluido acceso por inscripción ID |
| Plantilla | Propiedad de la inscripción, campeonato y jugador, incluido acceso por plantilla ID |
| Partido | CampeonatoPolicy, participantes del mismo campeonato y equipos del propietario |
| Evento | Partido del participante, permiso de acta y plantilla participante en ese mismo campeonato |
| Configuración / Calendario | CampeonatoPolicy; calendario verifica también las inscripciones activas |

Para actualizar catálogos, sus vínculos históricos tampoco pueden apuntar a un
campeonato ajeno o sin dueño: cambiar el nombre de un catálogo afecta sus referencias.
Los recursos nuevos sin vínculos siguen siendo administrables por su dueño.

Equipo y jugador son catálogos reutilizables en distintas ligas DEL MISMO PROPIETARIO
(MER R02/R03 y D-10). No se los convierte en recursos exclusivos de un campeonato.
Lo que no puede cruzarse son inscripciones/plantillas/participantes entre ligas, ni
catálogos entre propietarios. No se crea una FK jugador → equipo.

Los controladores conservan las validaciones 2.0: rivales activos y distintos,
dorsales, inscripción activa única del jugador, estados, autogoles, asistencias,
restricciones de eliminación y transacciones. Las comprobaciones de acceso se
ejecutan antes de validar o modificar los recursos destino.

## Escrituras protegidas

Todas estas rutas requieren auth:sanctum; los controladores aplican las Policies:

| Controlador | Métodos y rutas |
| --- | --- |
| EquipoController | POST /api/equipos; PUT/PATCH/DELETE /api/equipos/{equipo} |
| JugadorController | POST /api/jugadores; PUT/PATCH/DELETE /api/jugadores/{jugador} |
| CampeonatoEquipoController | POST /api/campeonatos/{campeonato}/inscripciones-equipos; PATCH/DELETE /api/inscripciones-equipos/{campeonatoEquipo} |
| PlantillaController | POST /api/inscripciones-equipos/{campeonatoEquipo}/plantillas; PATCH/DELETE /api/plantillas/{plantilla} |
| PartidoController | POST /api/campeonatos/{campeonato}/partidos; PUT/PATCH/DELETE /api/partidos/{partido}; POST /api/campeonatos/{campeonato}/generar-calendario |
| EventoEstadisticoController | POST /api/partidos/{partido}/eventos; PATCH/DELETE /api/eventos/{evento} |
| ConfiguracionCampeonatoController | POST/PATCH /api/campeonatos/{campeonato}/configuracion |

El CRUD privado de campeonatos conserva su protección del Sprint 1.3.
La respuesta para invitados es 401 y para recursos ajenos/sin dueño es 403.
El cliente no puede elegir o transferir el dueño enviando administrador_id o user_id.

## Consultas heredadas y riesgo pendiente

No se cambiaron las rutas GET, sus filtros ni su accesibilidad. Continúan abiertas:

| GET heredado | Información expuesta / riesgo |
| --- | --- |
| /api/equipos y /api/equipos/{equipo} | Catálogo global, incluso equipos no publicados; nombres, ciudad y escudo |
| /api/jugadores y /api/jugadores/{jugador} | Catálogo global, nombres, apellidos, fecha de nacimiento y foto |
| /api/campeonatos/{campeonato}/inscripciones-equipos y /api/inscripciones-equipos/{campeonatoEquipo} | Inscripciones e información de campeonato/equipo |
| /api/inscripciones-equipos/{campeonatoEquipo}/plantillas y /api/plantillas/{plantilla} | Identidades de jugadores, dorsales y vigencias |
| /api/campeonatos/{campeonato}/partidos; /api/partidos/{partido}; /api/partidos/{partido}/marcador | Programación, sedes y resultados sin filtro de publicación |
| /api/partidos/{partido}/eventos y /api/eventos/{evento} | Actas, jugadores y eventos provisionales |
| /api/campeonatos/{campeonato}/configuracion | Parámetros y periodos de gestión administrativa |
| /api/campeonatos/{campeonato}/tabla-posiciones y /api/campeonatos/{campeonato}/estadisticas/{tipo} | Clasificación y estadísticas sin regla de oficialización/publicación |

Las respuestas Eloquent también incluyen los IDs y metadatos de sus modelos.
No equivalen a las vistas públicas FINAL: DP-06/P-06 sigue sin definir publicación,
privacidad en planificación o ligas privadas. No se añadió /api/publico/*, ni se
asumió que en_curso/finalizado signifique publicado. Resolver lectura/privacidad
antes de exponer la API fuera del entorno controlado. Las escrituras quedan
protegidas aunque estas consultas sigan abiertas por el alcance acordado.

## Migración y compatibilidad de despliegue

Nueva: 2026_11_12_235100_add_administrador_id_to_equipos_and_jugadores.php.
up() solo agrega ambas columnas nullable, índices y FK a users.id con DELETE RESTRICT.
No actualiza, elimina ni reasigna registros. No se modificaron migraciones antiguas.
La migración se aplicó en MySQL según la verificación anterior. Las pruebas de
esquema aisladas invocan up() únicamente en SQLite en memoria.

Las nuevas altas de catálogos necesitan administrador_id, ya presente en esta
instancia. Para otras instalaciones, coordinar código/esquema, obtener respaldo y
autorización y verificar la migración específica. Los registros históricos quedan
con NULL y bloqueados para administrar
o inscribir hasta confirmar dueño humano, sin apropiación por vínculos existentes.
La asignación de equipos/jugadores históricos es un trabajo separado autorizado.
Los dos campeonatos antiguos siguen sin dueño. Solo se modificó el esquema y el
registro de esta migración en MySQL, sin escrituras en registros de negocio.

## Verificación

Resultados ejecutados: PHPUnit 66/66 y 1.109 aserciones en SQLite en memoria;
servicios frontend 47/47, Vitest 20/20, lint, build, Pint y git diff --check correctos.
AutorizacionDeportivaTest usa SQLite :memory: con el guard de seguridad existente.
Comprueba cada ruta de escritura con invitado, usuario ajeno y campeonato sin dueño,
IDs directos/anidados, inmutabilidad tras denegación, dueño de catálogos nuevos,
falsificación de dueño, huérfanos, relaciones incoherentes, referencias entre ligas,
conservación de catálogos anteriores y presencia de Sanctum en todas las escrituras.
Las pruebas deportivas anteriores conservan su ejecución por HTTP con autorización.

Pendiente manual: verificar con dos cuentas
desde React cada creación/edición/eliminación, inscripción y evento. Comprobar 401,
403 y 422 en la interfaz y bloqueo de registros sin propietario. Las pruebas de
concurrencia MySQL, carga de imágenes externa, publicación y arbitraje no se acreditan.
Sin cambios de diseño React, dependencias, commits ni push.
