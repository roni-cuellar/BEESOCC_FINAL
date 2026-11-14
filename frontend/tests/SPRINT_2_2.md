# Sprint 2.2 — Interfaz de gestión de árbitros

## Alcance y documentación

Referencias revisadas: MER consolidado (R04–R06, I01/I03/I04, C01–C04),
Manual Técnico (6.5, 6.9, 7.2 y 8.4) y backend/tests/ASIGNACIONES_ARBITRO.md.
Los roles son contextuales: la interfaz no concede permisos por elegir un perfil.
No se detectó contradicción para este alcance. El manual presenta rutas futuras;
las rutas operativas comprobadas son las de backend/routes/api.php.

## Archivos de este sprint

| Archivo | Función y comunicación |
| --- | --- |
| frontend/src/services/arbitroService.js (nuevo) | Usa el Axios común con cookies/CSRF; centraliza consultas y operaciones arbitrales |
| frontend/src/pages/ArbitrosPage.jsx (nuevo) | Consulta vigente e historial; busca candidato; asigna; confirma reemplazo/revocación con motivo; muestra carga, éxito y errores |
| frontend/src/pages/MisArbitrajesPage.jsx (nuevo) | Lista solo las autorizaciones vigentes de la sesión, sin enlaces administrativos |
| frontend/src/routes/AppRoutes.jsx | Agrega /ligas/:ligaId/arbitros y /mis-arbitrajes dentro de RutaPrivada |
| frontend/src/components/Sidebar.jsx | Enlaces Árbitros y Mis arbitrajes, reutilizando navegación móvil |
| frontend/src/components/AplicacionLayout.jsx | Enlace Mis arbitrajes con navegación adaptable |
| frontend/src/pages/DashboardPage.jsx | Acceso directo a Árbitros desde el resumen |
| backend/app/Http/Controllers/Api/AsignacionArbitroController.php | Añade búsqueda privada exacta y listado propio; conserva escrituras del servicio existente |
| backend/routes/api.php | Declara dos consultas nuevas bajo Sanctum |
| backend/tests/Feature/ConsultaArbitrosTest.php (nuevo) | Prueba privacidad, autorización, búsqueda, límite de intentos y vigencia |
| frontend/tests/unit/arbitros.test.jsx (nuevo) | Prueba contrato HTTP, cookies, cuerpo DELETE, errores, campos mostrados y protección durante recuperación de sesión |
| frontend/tests/SPRINT_2_2.md (nuevo) | Evidencia y pasos manuales de esta entrega |

Se reutilizan Boton, Campo, Tarjeta, Aviso, EstadoVista y Confirmacion (incluido su
modal accesible). No se cambia logo, paleta, dependencias ni migraciones.
El estado de estas páginas es local; no se añade un store global innecesario.

## API

Se reutilizan GET/POST/PATCH/DELETE /api/campeonatos/{id}/arbitro y
GET /api/campeonatos/{id}/arbitro/historial. PATCH y DELETE requieren motivo.
El servicio conserva Policies, transacciones y restricción de activo único existentes.

Endpoints mínimos incorporados:

- POST /api/campeonatos/{id}/arbitro/buscar-usuario, cuerpo {email}.
  Solo propietario, Sanctum, 10 intentos/minuto. Coincidencia exacta, sin búsquedas
  parciales ni catálogo; excluye al solicitante. Devuelve usuario {id,name} o null.
  El correo se envía en el cuerpo, no en la URL. Cache-Control no-store.
  Aunque limitado y privado, permite confirmar la existencia del correo proporcionado
  a un propietario autenticado; no se ofrece enumeración por prefijo.
- GET /api/mis-arbitrajes, Sanctum. No acepta usuario_id para consultar a terceros.
  Devuelve id de asignación, fecha_inicio y campeonato {id,nombre,temporada,estado}.
  Excluye revocadas, futuras, sin dueño y asignaciones al propio propietario.
  No concede acceso a consultas administrativas ni a eventos/estados.

## Resultados automáticos

- PHPUnit: 96 aprobadas, 1.699 aserciones, SQLite :memory: con guardia de seguridad.
- npm test: 47 aprobadas (servicios existentes).
- npm run test:unit: 44 aprobadas, cinco archivos (incluye autenticación/publicación).
- npm run lint: sin avisos.
- npm run build: compilación de producción correcta.
- Pint y git diff --check: correctos.

Las suites verifican asignación/reemplazo/revocación e historial existentes;
los nuevos casos verifican consulta privada, 401/403/422/429, respuesta mínima,
ausencia de futuros/revocados/legado y ausencia de privilegios administrativos.
Las pruebas frontend usan adaptadores HTTP simulados y render estático. No son
pruebas de clics reales ni acreditan responsive visual o sesiones de navegador.
No se realizaron escrituras ni asignaciones de prueba en la base principal.

## Verificación manual pendiente

Usar preferentemente una base aislada con la aplicación conectada a ella. Si se
prueba en la principal, solicitar autorización específica antes de crear asignaciones.
Usar el mismo host para frontend/backend (sin mezclar localhost y 127.0.0.1).

1. Iniciar sesión como propietario; abrir Mis ligas → campeonato → Árbitros
   (desde resumen o menú). Comprobar carga y estado sin árbitro.
2. Introducir correo exacto de otra cuenta registrada; comprobar nombre e ID.
   Probar correo inexistente, inválido y propio; no debe habilitarse una asignación.
3. Asignar al candidato. Comprobar mensaje de éxito, vigente e historial.
4. En otra sesión, entrar como candidato en Mis arbitrajes; comprobar campeonato.
   No debe obtener acceso al dashboard administrativo mediante un ID directo.
5. Buscar otra cuenta y escribir motivo. Reemplazar, probar Cancelar y luego
   Confirmar. Debe conservarse la fila anterior revocada y aparecer la nueva.
6. Revocar con motivo y confirmación. Actualizar Mis arbitrajes de la cuenta
   revocada: debe desaparecer; historial administrativo debe conservarla.
7. Con usuario ajeno probar /ligas/{id}/arbitros y peticiones directas: 403.
   Sin sesión, las rutas privadas llevan al login y la API devuelve 401.
8. Verificar errores 409/422 (candidato igual al vigente y motivo vacío mediante
   petición directa), pérdida de sesión, errores de red y recuperación/reintento.
9. Revisar menú móvil, foco del modal, botones, textos largos e historial a 360,
   768 y 1280 píxeles. Verificar registro/login/logout y páginas deportivas.

## Sprint 2.3 pendiente

Permisos del árbitro vigente para eventos y transiciones de partidos, vistas
operativas correspondientes y pruebas completas de revocación sobre esas acciones.
Se mantiene gestionarActa y no se modificaron módulos deportivos ni sus permisos.
Sin commits ni push. Los cambios previos de Sprint 2.1 permanecen sin confirmar.
