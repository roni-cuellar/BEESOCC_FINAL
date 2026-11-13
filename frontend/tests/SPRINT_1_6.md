# Sprint 1.6 — publicación y consulta desde React

## Reglas y alcance

Se conserva la decisión DP-06/P-06 cerrada por Sprint 1.5 y documentada en
backend/tests/PUBLICACION_CAMPEONATOS.md, sobre el MER y Manual Técnico de docs/:
privado por defecto, publicación por propietario y lectura deportiva pública.
No se modificaron backend, migraciones, datos deportivos históricos ni dependencias.
La autorización y el filtrado de campos sensibles continúan siendo responsabilidad
del backend. React no concede propiedad ni intenta usar endpoints privados como
alternativa si una consulta pública devuelve 404.

## Archivos y responsabilidades

| Archivo en frontend/ | Funcionamiento |
| --- | --- |
| src/services/ligaService.js | PATCH privado con publicado booleano; devuelve el campeonato confirmado por Laravel |
| src/services/publicoService.js | Consultas GET bajo /publico/campeonatos mediante el Axios compartido; no solicita sesión/CSRF |
| src/services/api.js | Mensaje comprensible para 403; conserva manejo de 401, 404, 419 y 422 |
| src/stores/useLigaStore.js | Actualiza listado/detalle tras éxito y bloquea envíos duplicados con publicando; conserva datos si falla |
| src/pages/MisLigasPage.jsx | Reutiliza Confirmacion y Aviso para publicar/ocultar y notificar éxito/error |
| src/components/LigaCard.jsx | Muestra Publicado/Privado; acción del propietario y variante de consulta pública sin administración |
| src/components/Navbar.jsx | Enlace Campeonatos en el menú existente, también móvil |
| src/routes/AppRoutes.jsx | Agrega rutas públicas fuera de RutaPrivada; conserva protección de /mis-ligas y /ligas/* |
| src/pages/CampeonatosPublicosPage.jsx | Catálogo público con carga, vacío, reintento y tarjetas existentes |
| src/pages/DetallePublicoPage.jsx | Detalle público y resumen de equipos/calendario; limpia datos al consultar y trata 404 sin exponer información |
| src/components/PartidoCard.jsx | Variante pública con marcador incluido en la respuesta; no consulta la API administrativa ni enlaza a edición |
| src/pages/TablaPage.jsx | Reutiliza la misma tabla; el modo público elige publicoService y textos de espectador |
| src/pages/EstadisticasPage.jsx | Reutiliza los seis tipos y su presentación; el modo público elige publicoService |
| tests/unit/publicacion.test.jsx | Contratos HTTP, booleanos, estado, duplicados, errores, rutas y campos visibles |
| tests/SPRINT_1_6.md | Este informe y la guía de comprobación manual |

Se reutilizan Boton, Tarjeta, Confirmacion, Aviso, EstadoVista, EncabezadoPagina,
ImagenEntidad, LigaCard, PartidoCard y las pantallas de tabla/estadísticas. Se
conservan logos originales, paleta, tipografías, clases y puntos de ruptura.
La única incompatibilidad corregida de contrato deportivo fue PartidoCard: antes
siempre pedía el marcador privado y esperaba campeonato_equipo.equipo; el contrato
público entrega equipo y goles en cada participante.

## Navegación y comportamiento

- /mis-ligas: estado visible y botón Publicar/Ocultar con confirmación. Éxito cambia
  el estado usando la respuesta del servidor. Error no simula éxito ni altera la lista.
- /campeonatos: catálogo para espectadores sin cuenta, usando GET /api/publico/campeonatos.
- /campeonatos/:ligaId: resumen público, GET de detalle, equipos y partidos.
- /campeonatos/:ligaId/tabla: tabla pública.
- /campeonatos/:ligaId/estadisticas: estadísticas públicas.

No hay controles administrativos en vistas públicas. Se muestran únicamente
nombre/descripción/temporada/formato/estado, equipos/escudos, calendario/marcador,
tabla y estadísticas deportivas. No se vuelcan objetos JSON en la vista ni se
renderizan fecha_nacimiento, propietario, correo o metadatos internos.
Las peticiones públicas no requieren cookies ni token CSRF. El navegador puede
enviar cookies propias del mismo origen, pero el contrato público no depende de ellas.

La consulta se renueva al volver a enfocar la ventana, al navegar entre pestañas del
detalle y con Actualizar. Ocultar se comprueba en la siguiente consulta: no es un
sistema push ni puede borrar información que el espectador ya haya descargado.

## Verificación automatizada

- Servicios Node: 47/47.
- Vitest: 33/33 (13 nuevas de publicación, 20 anteriores incluidas autenticación).
- Lint y build: correctos, sin avisos pendientes de los archivos nuevos.
- git diff --check: correcto.
- Backend: 77/77, 1.495 aserciones en SQLite en memoria.

Las pruebas nuevas verifican payload booleano, publicación y ocultación en el
store, bloqueo de doble envío, conservación del estado ante 401/403/404/422,
mensajes comprensibles, tarjeta pública sin edición, ausencia de campos sensibles,
marcador público, contratos GET públicos y navegación pública sin usuario.
Son contratos con mocks y renderizado estático; no sustituyen un recorrido completo
de publicación en un navegador autenticado. La API real ya dispone de pruebas
negativas de propiedad y privacidad en SQLite.

## Comprobaciones reales en navegador

Se inició Laravel local al detectar que el puerto 8000 estaba cerrado. En Brave:

- Catálogo público accesible sin sesión y estado vacío correcto.
- Mensaje de error de conexión y reintento al recuperar el backend.
- Logo/branding y composición de escritorio revisados.
- Vista a 375 px y menú móvil comprobados; sin desbordamiento horizontal observado.
- /mis-ligas sin sesión espera la comprobación y redirige a /login.
- Enlace a registro y campos del formulario conservados.
- Detalle público no disponible muestra mensaje y opción de reintentar, sin datos.

No se registraron usuarios ni se crearon/publicaron/ocultaron campeonatos reales.
Falta la revisión visual con tarjetas llenas, el detalle con datos deportivos y el
recorrido autenticado completo; esas funciones se probaron automáticamente mediante
contratos de frontend y las pruebas de backend, no como E2E real.

## Pasos concretos para el usuario

1. Con MySQL y Laravel/Vite iniciados, usar siempre el mismo host en el navegador.
   Entrar a http://localhost:5173, iniciar sesión o registrar una cuenta propia.
2. En Mis ligas, crear una liga nueva desde Crear liga. Debe mostrar Privado.
   No reclamar ni modificar los campeonatos históricos sin dueño.
3. Pulsar Publicar y confirmar. Debe mostrar Publicado y aviso de éxito. En otra
   ventana sin sesión abrir /campeonatos: debe aparecer la liga.
4. Abrir Ver campeonato. Revisar resumen, calendario, tabla y las seis estadísticas.
   Una liga recién creada puede tener todas esas listas vacías; no es un error.
5. Volver a la cuenta propietaria, pulsar Ocultar y confirmar. Actualizar la ventana
   del espectador o volver a enfocarla: la liga desaparece y su detalle no se muestra.
6. Con otra cuenta, verificar que la liga ajena no aparece en Mis ligas. La llamada
   PATCH ajena sigue siendo rechazada por Laravel con 403; no basta alterar React.
7. Inspeccionar en Network las respuestas públicas: no contienen fecha de nacimiento,
   email ni administrador_id. Revisar a 375 px y en escritorio con datos deportivos.
8. Cerrar sesión e intentar abrir /mis-ligas o /ligas/ID/dashboard: debe pedir login.
   /campeonatos debe seguir accesible sin cuenta. Repetir login/registro y validar
   credenciales incorrectas para completar la regresión manual de autenticación.

Sin commits ni push. No se añadieron roles, arbitraje ni funcionalidades deportivas.
