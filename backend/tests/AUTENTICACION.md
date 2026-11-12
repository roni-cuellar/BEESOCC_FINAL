# Verificación de autenticación SPA

Implementación basada en https://laravel.com/docs/12.x/sanctum#spa-authentication.

Sanctum usa el guard `web`, sesiones y cookies; no se emiten tokens personales.
El modelo User conserva su estructura, su cast `hashed` y sus campos ocultos.
Las rutas de autenticación usan el grupo `web` para exigir CSRF incluso sin
Origin. Se excluye de ellas el middleware stateful para no duplicar la sesión.
Las demás rutas API usan `statefulApi()` sin añadir autorización deportiva.

## Entorno local

- Iniciar MySQL y ejecutar `php artisan serve --host=127.0.0.1 --port=8000` desde backend.
- Abrir la SPA en `http://127.0.0.1:5173`. Usar el mismo host para ambos servicios.
- Revisar las variables documentadas en `.env.example`: SESSION_DRIVER=database,
  SESSION_DOMAIN=null, SESSION_SECURE_COOKIE=false, SESSION_HTTP_ONLY=true,
  SESSION_SAME_SITE=lax, SANCTUM_STATEFUL_DOMAINS y CORS_ALLOWED_ORIGINS.
  En producción usar HTTPS y SESSION_SECURE_COOKIE=true; ajustar dominios.
- No copiar todo `.env.example` sobre `.env`: conservar las credenciales locales.
- El proxy actual de Vite no incluye `/sanctum`; en esta comprobación se llama
  directamente al backend. React todavía no integra autenticación real.

## Comprobación manual

Desde la consola del navegador en la SPA, definir este ayudante:

```js
const base = 'http://127.0.0.1:8000';
async function solicitar(ruta, datos) {
  const token = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.slice(11);
  const respuesta = await fetch(base + ruta, {
    method: datos ? 'POST' : 'GET',
    credentials: 'include',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json',
      ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}) },
    ...(datos ? { body: JSON.stringify(datos) } : {}),
  });
  console.log(respuesta.status, respuesta.status === 204 ? null : await respuesta.json());
}
```

1. `await solicitar('/api/usuario')`: debe devolver 401 sin sesión.
2. `await solicitar('/sanctum/csrf-cookie')`: 204 y cookies de sesión y XSRF-TOKEN.
3. `await solicitar('/api/registro', {name: 'Prueba SENA', email: 'manual@example.test', password: 'ClavePrueba123', password_confirmation: 'ClavePrueba123'})`: 201 y sesión iniciada. Esto crea un usuario de prueba real.
4. `await solicitar('/api/usuario')`: 200, sin contraseña ni remember_token.
5. `await solicitar('/api/logout', {})`: 200. Repetir el paso 1: 401.
6. `await solicitar('/api/login', {email: 'manual@example.test', password: 'ClavePrueba123'})`: 200. Una clave incorrecta devuelve 422.
7. Registro incompleto, correo duplicado o confirmación distinta: 422. Petición POST sin token CSRF: 419. Más de seis intentos por minuto por ruta e IP: 429.

Para Axios, la integración futura debe activar `withCredentials` y `withXSRFToken`
y pedir primero `/sanctum/csrf-cookie` en la raíz del backend.

## Pruebas automatizadas

`php vendor/bin/phpunit` ejecuta la suite usando SQLite en memoria según phpunit.xml.
AuthTest verifica el destino antes de crear únicamente el esquema de usuarios en memoria.
Incluye registro, validaciones, hash, login, renovación de sesión, usuario protegido,
logout, renovación CSRF y rechazo de POST sin CSRF fuera del modo de pruebas.
Las pruebas deportivas existentes aplican su esquema solo en la base aislada.

La migración nueva de personal_access_tokens queda pendiente en MySQL. Solo crea
esa tabla, no altera tablas deportivas. No es necesaria para sesiones SPA y no se
debe ejecutar sobre MySQL sin respaldo previo y revisión.
