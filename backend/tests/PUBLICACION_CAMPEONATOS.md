# Sprint 1.5 — publicación y acceso de espectadores

## Aplicación verificada en MySQL el 12 de noviembre de 2026

Rama develop; cambios del Sprint presentes, sin commits ni push. Respaldo actualizado:
C:/Users/admin/Documents/BACKUPS_BEESOCC/beesocc_sprint_1_5_2026-11-12_193917_f74072.sql,
24.922 bytes; mysqldump terminó con código 0, marcador final y 18 definiciones de
tablas verificados. No se mostraron credenciales ni contenido privado. No se acredita
restauración del respaldo.

Se aplicó únicamente 2026_11_12_235200_add_publicado_to_campeonatos_table mediante
--path específico. migrate:status confirmó Ran, lote 7. personal_access_tokens sigue
Pending. MySQL informa publicado como tinyint(1), NOT NULL, DEFAULT 0, compatible
con el booleano definido en Laravel. El control del propietario corresponde a la
Policy; el booleano no implementa por sí mismo esa autorización.

Comparación de todas las filas anteriores de las tablas existentes, omitiendo
solo la nueva columna y el registro técnico de migraciones: datos intactos.
Los 2 campeonatos siguen sin dueño y privados. No hay campeonatos con propietario;
no se creó, publicó, ocultó ni reasignó ninguno para probar MySQL.

GET reales del kernel Laravel sobre la conexión MySQL: catálogo público 200 con
lista vacía; detalle público de cada campeonato privado 404. La publicación de
un campeonato propio, el rechazo ajeno y el filtrado de campos se probaron en
SQLite, no mediante escrituras en MySQL.

## Decisión funcional

Se revisaron DP-06 del MER consolidado y P-06 del Manual Técnico de docs/.
Ambos dejaban pendiente el criterio de publicación. Las reglas del usuario en
Sprint 1.5 cierran ese punto: privado por defecto, publicar/ocultar por propietario,
lectura deportiva sin datos personales y ningún permiso de escritura al visitante.

Se utiliza publicado booleano, en vez del visibilidad publico/privado propuesto
como alcance pendiente en el MER. No se duplican ambos campos. La publicación
es independiente del estado deportivo: no se deduce de planificacion/en_curso/
finalizado y no oficializa resultados. Los cálculos de tabla/estadísticas conservan
la semántica 2.0 (la tabla usa finalizados; algunas estadísticas incluyen partidos
en curso/suspendidos). No se implementó arbitraje ni otras reglas deportivas.

## Archivos y comunicación

| Archivo en backend/ | Responsabilidad |
| --- | --- |
| database/migrations/2026_11_12_235200_add_publicado_to_campeonatos_table.php | Agrega publicado con default false; no actualiza campos anteriores ni borra datos |
| app/Models/Campeonato.php | Convierte publicado a booleano, sin incluirlo en fillable |
| app/Policies/CampeonatoPolicy.php | Autoriza publicar únicamente al dueño no nulo |
| app/Http/Controllers/Api/CampeonatoController.php | Endpoint de publicación validado; impide publicar por el CRUD genérico |
| routes/api.php | Separa rutas administrativas con Sanctum y públicas GET bajo /api/publico |
| app/Http/Controllers/Api/EquipoController.php | Lista solo catálogo propio y autoriza detalle por ID |
| app/Http/Controllers/Api/JugadorController.php | Lista solo catálogo propio y autoriza detalle por ID |
| app/Http/Controllers/Api/CampeonatoEquipoController.php | Autoriza lectura del campeonato o inscripción específica |
| app/Http/Controllers/Api/PlantillaController.php | Autoriza lectura de inscripción y plantilla específica |
| app/Http/Controllers/Api/PartidoController.php | Autoriza listado, detalle y marcador administrativos |
| app/Http/Controllers/Api/EventoEstadisticoController.php | Autoriza consultas de partido y evento específico |
| app/Http/Controllers/Api/ConfiguracionCampeonatoController.php | Restringe la configuración al propietario |
| app/Http/Controllers/Api/TablaPosicionesController.php | Autoriza consulta privada y delega cálculo al servicio |
| app/Http/Controllers/Api/EstadisticaController.php | Autoriza consulta privada y delega las seis estadísticas |
| app/Services/TablaPosicionesService.php | Cálculo anterior trasladado sin cambiar reglas; usado por ambas vistas |
| app/Services/EstadisticaService.php | Cálculo anterior trasladado sin cambiar reglas; usado por ambas vistas |
| app/Http/Controllers/Api/PublicoCampeonatoController.php | Busca solo campeonatos publicados/con dueño y devuelve listas explícitas de campos; acota IDs secundarios al campeonato |
| tests/Feature/PublicacionCampeonatoTest.php | Publicación, ocultación, campos, IDs cruzados, GET privado, visitantes y conservación de legado |
| tests/Feature/CampeonatoPropiedadTest.php | Actualiza la expectativa heredada de consulta sin sesión a 401 |
| tests/Feature/AutorizacionDeportivaTest.php | Actualiza la expectativa heredada de consulta sin sesión a 401 |
| tests/PUBLICACION_CAMPEONATOS.md | Este registro de decisiones, endpoints, pruebas y límites |

Los controladores privados usan Sanctum y Gate antes de devolver recursos. El
controlador público consulta la bandera directamente; no necesita sesión ni
impersonar al propietario. Los servicios de cálculo no son endpoints ni tienen
rutas propias. El público vuelve a seleccionar solo los campos deportivos.

No se modificaron archivos React ni dependencias: los administradores siguen
usando las mismas URLs con cookies Sanctum y reciben el mismo contrato deportivo.
Las listas de equipos/jugadores ahora solo incluyen registros de su cuenta.
Este Sprint entrega API pública; no añade un panel nuevo de espectadores ni un
botón de publicación al diseño existente.

## Endpoints privados

Todas las consultas deportivas GET anteriores (catálogos, inscripciones, plantillas,
partidos, marcador, eventos, configuración, tabla y estadísticas) ahora requieren
auth:sanctum y propiedad. Un campeonato publicado tampoco abre sus rutas privadas
a otros usuarios. Los listados globales de equipos/jugadores pasan a filtrar dueño.
Las escrituras deportivas conservan las Policies del Sprint 1.4.

PATCH /api/campeonatos/{campeonato}/publicacion recibe {"publicado": true} o false.
Solo propietario: 200; invitado: 401; ajeno o sin dueño: 403; datos inválidos: 422.
No permite alterar nombre/administrador_id ni otros campos por ese endpoint.
Crear o actualizar por el CRUD normal no permite activar publicado.

## Endpoints públicos solo GET

Prefijo: /api/publico/campeonatos

| Sufijo | Contenido |
| --- | --- |
| / | Catálogo publicado |
| /{campeonato} | Datos públicos del campeonato |
| /{campeonato}/equipos | Equipos inscritos |
| /{campeonato}/equipos/{inscripcion}/plantilla | Plantilla activa de esa inscripción; el ID es de inscripción, no del catálogo equipo |
| /{campeonato}/partidos | Calendario y marcadores |
| /{campeonato}/partidos/{partido} | Partido y marcador |
| /{campeonato}/partidos/{partido}/eventos | Eventos deportivos y jugadores |
| /{campeonato}/tabla | Clasificación |
| /{campeonato}/estadisticas/{tipo} | goleadores, asistencias, amarillas, rojas, faltas o equipos |

Privado, oculto, inexistente o sin propietario: 404 uniforme. Un ID de partido o
inscripción de otra liga: 404, incluso cuando ambos registros existen. Los tipos
de estadística están en una lista fija y los parámetros de ID solo aceptan números.
No hay endpoints públicos globales de jugadores ni de configuración.

Los GET públicos devuelven Cache-Control: no-store para no reutilizar en el cliente
datos guardados después de ocultar. Ocultar impide futuras consultas; no puede
retirar copias de información que alguien ya descargó mientras estaba publicada.

## Campos permitidos

- Campeonato: id, nombre, descripcion, temporada, formato, estado.
- Equipo: id, nombre, escudo; estado e identificador de inscripción en el listado.
- Plantilla: jugador (id, nombre, apellido), dorsal, posicion.
- Partido: id, jornada, fecha_hora, lugar, estado; participantes con rol, equipo y goles.
- Evento: tipo, minuto, rol y los campos deportivos del jugador/plantilla anteriores.
- Tabla: equipo_id, nombre, PJ, PG, PE, PP, GF, GC, DG, PTS, posicion.
- Estadística: equipo_id/nombre y contadores, o jugador_id/nombre/apellido/total.

Se excluyen fecha_nacimiento, fotos de jugador, fechas de inscripción/salida,
datos de cuenta, administrador_id, timestamps y configuración interna. Se rechazan
relaciones incoherentes que podrían incluir un catálogo de otro propietario.
La descripción del campeonato se considera texto público al publicarlo; no usarla
para notas internas. No se exponen relaciones Eloquent completas en la API pública.

## Migración y límites

La migración nueva ya se ejecutó en esta instancia bajo la autorización recibida
y con respaldo previo, como se registra arriba. Para otras instalaciones se requiere
su propio respaldo y autorización. No activar estos endpoints sobre el esquema
anterior: las consultas públicas requieren la columna publicado.
Los registros anteriores quedaron privados por default false, sin cambiar su
identidad, propietario, relaciones ni valores deportivos.
La migración de tokens Sanctum sigue pendiente y no es necesaria para sesiones SPA.

## Pruebas ejecutadas

PHPUnit: 77 pruebas, 1.495 aserciones, en SQLite en memoria; incluye las pruebas
de negocio anteriores y las 11 nuevas de publicación. Servicios frontend: 47/47;
Vitest: 20/20; lint y build correctos. Pint y git diff --check correctos.

Pendientes manuales: recorrido real de dos cuentas y espectador en navegador, publicación/ocultación
y mantenimiento del diseño responsive. No se acreditan pruebas de creación o
publicación sobre MySQL ni nueva interfaz de espectador.
Sin commits, push, librerías nuevas ni modificaciones de registros históricos.

## Guía manual posterior desde navegador

1. Iniciar Laravel en http://127.0.0.1:8000 y Vite en http://127.0.0.1:5173.
   Usar 127.0.0.1 en ambos, sin alternar localhost. Registrar o iniciar sesión con
   la cuenta A. Como no hay campeonatos con dueño, pulsar Crear liga en Mis ligas
   y guardar una nueva: este paso sí crea un registro real y lo realizará el usuario.
   No intentar reclamar los dos campeonatos históricos.
2. Obtener el ID de la nueva liga desde su URL /ligas/ID/dashboard. Confirmar que
   GET /api/campeonatos/ID devuelve publicado=false para su propietario.
3. En la consola del navegador, dentro de la SPA autenticada, definir:

```js
async function cambiarPublicacion(id, publicado) {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
  const cookie = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
  if (!cookie) throw new Error('No se recibió la cookie CSRF.');
  const respuesta = await fetch(`/api/campeonatos/${id}/publicacion`, {
    method: 'PATCH', credentials: 'include',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json',
      'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) },
    body: JSON.stringify({ publicado }),
  });
  console.log(respuesta.status, await respuesta.json());
}
```

4. Ejecutar `await cambiarPublicacion(ID, true)` sustituyendo ID por el número:
   debe responder 200 y publicado=true. No se añadió un botón al diseño React.
5. En una ventana sin sesión, consultar http://127.0.0.1:5173/api/publico/campeonatos/ID
   y /equipos, /partidos, /tabla o /estadisticas/goleadores bajo esa misma liga:
   deben responder 200. Inspeccionar JSON: sin fecha_nacimiento, email, password,
   administrador_id ni timestamps internos. Una liga nueva sin jugadores permite
   comprobar la cabecera pública, pero para comprobar plantillas se requiere un
   conjunto deportivo propio creado por el usuario, nunca modificar el legado.
6. Con la cuenta A, ejecutar `await cambiarPublicacion(ID, false)`; repetir GET
   público en la ventana sin sesión: 404 y ausencia en el catálogo. La cuenta A
   mantiene GET privado 200.
7. Iniciar sesión con la cuenta B en otra sesión/perfil del navegador, definir el
   mismo ayudante e intentar true y false sobre ID: ambos 403. Sin cuenta, PATCH
   administrativo no permite escribir (401 o 419 si falta primero el CSRF).

## Alternativa Postman

Usar el mismo host http://127.0.0.1:8000, cookie jar activo, Accept: application/json
y Origin: http://127.0.0.1:5173 en todas las peticiones de sesión. Primero GET
/sanctum/csrf-cookie. En registro/login y PATCH, enviar X-XSRF-TOKEN con el valor
decodificado de la cookie XSRF-TOKEN, sin publicarlo ni guardarlo en el repositorio.
Iniciar sesión con POST /api/login y el correo/contraseña de la cuenta de prueba
propia. PATCH /api/campeonatos/ID/publicacion lleva JSON {"publicado":true} o false.
Para espectador usar un jar sin sesión y los endpoints /api/publico/; para usuario
B usar otro jar y repetir la inicialización CSRF/login. Se esperan los mismos
200/403/404 indicados arriba. Nunca colocar credenciales reales en documentación.
