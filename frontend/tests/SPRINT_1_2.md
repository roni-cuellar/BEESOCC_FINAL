# Sprint 1.2 — verificación

## Integración

Axios envía cookies y XSRF. authService pide CSRF antes de registro, login y logout.
useAuthStore conserva solo usuario, carga y errores en memoria. Al abrir una ruta
privada consulta /api/usuario antes de permitir acceso o redirigir. Los errores de
red permiten reintentar; 401 elimina el usuario local; 419 explica que hay que
reintentar (el siguiente envío de autenticación renueva CSRF); 422 muestra validaciones.
No hay tokens Bearer, persistencia de credenciales ni nuevas dependencias.

Usar http://127.0.0.1:5173 y Laravel en http://127.0.0.1:8000. VITE_API_URL=/api
conserva el mismo host del navegador mediante proxy /api, /sanctum y /storage.
El destino del proxy puede ser 127.0.0.1 aunque el navegador use localhost: las
cookies sin Domain pertenecen al host que ve el navegador. No alternar hosts
en las URLs del navegador ni configurar una API absoluta en otro host local.
Si se usa una API absoluta, configurar CORS y los dominios stateful del backend;
en producción publicar los tres prefijos o ajustar VITE_API_URL y HTTPS.

No se cambiaron CSS, branding, tamaños ni puntos de ruptura. Los enlaces Salir
son botones con las mismas clases y ejecutan logout real. Los formularios conservan
sus campos, con validación nativa y mensajes de error del servidor.

## Automatizado

- Contratos de servicios: 47 pruebas pasaron. Se corrigió el adaptador de la prueba
  antigua de creación de equipos para aceptar FormData, sin cambiar el servicio.
- Vitest: 20 pruebas pasaron, incluidas 8 nuevas de autenticación (contratos HTTP,
  CSRF, carga, recuperación, errores, logout y espera del guard).
- oxlint y vite build: correctos.
- PHPUnit: 41 pruebas, 724 aserciones; SQLite en memoria, sin tocar MySQL.
- Estas pruebas no equivalen a un recorrido real del navegador con cookies.

## Pendiente de navegador

El navegador integrado agotó el tiempo de conexión a http://127.0.0.1:5173/login.
No se pudo validar registro/login real desde React ni cookies persistentes o responsive visual.

Con ambos servidores iniciados:

1. Abrir /mis-ligas sin sesión: esperar consulta de usuario y redirección a /login.
2. Ir a /registro y crear una cuenta de prueba; verificar llegada a /mis-ligas.
3. Recargar y abrir una ruta /ligas/:id/dashboard: debe conservar la sesión.
4. Pulsar Salir desde la cabecera y desde el menú móvil. Volver a una ruta privada
   con Atrás o URL directa: debe pedir login.
5. Probar contraseña incorrecta y correo duplicado: mensajes 422 y botón habilitado
   para corregir. Enviar dos veces rápidamente: una sola solicitud de formulario.
6. Expirar la sesión: 401 debe sacar de vistas privadas. Simular 419 desde el servidor
   o quitar X-XSRF-TOKEN en una solicitud: mensaje legible y reintento de autenticación.
7. Revisar login, registro, errores y menú a 375 px y 1280 px, sin desbordamientos.

## Composer audit (sin actualizar paquetes)

Resultado: 8 avisos en 5 paquetes. Una gravedad media y siete sin gravedad informada
por Composer; no se deben interpretar como de gravedad baja. Sin paquetes abandonados.

| Paquete | Instalada | Avisos | Gravedad en audit | Corrección de la rama 7.4 |
| --- | --- | --- | --- | --- |
| symfony/http-foundation | 7.4.21 | 4 | Sin especificar | 7.4.23 |
| symfony/http-kernel | 7.4.21 | 1 | Sin especificar | 7.4.23 |
| symfony/mime | 7.4.19 | 1 | Media | 7.4.22 |
| symfony/routing | 7.4.21 | 1 | Sin especificar | 7.4.23 |
| symfony/yaml (desarrollo) | 7.4.21 | 1 | Sin especificar | 7.4.23 |

Las versiones corregidas se identifican a partir de los rangos afectados devueltos
por audit; no se comprobó una actualización resolviendo el árbol de dependencias.

Avisos: http-foundation PKSA-24ph-xkrh-y79j, PKSA-pr6q-2w5s-kzzf,
PKSA-nxjs-s6hn-vb6m, PKSA-b2gg-vk1g-6d5q; http-kernel PKSA-3gxv-7xhq-mgmz;
mime PKSA-wtxr-p26d-nn42; routing PKSA-y8c5-1b7f-r1gj; yaml PKSA-nktx-f1n1-b4yd.

Antes del despliegue resolver los avisos y repetir las pruebas: afectan controles
basados en métodos/rutas, confianza en proxies, generación de URLs, cabeceras de
correo y denegación de servicio al procesar YAML. La exposición concreta depende
de la configuración y funciones utilizadas. No se actualizó composer.json/lock
durante este Sprint 1.2 ni se ejecutaron migraciones, commits o push.
