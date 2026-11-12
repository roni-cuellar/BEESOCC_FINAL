# BEESOCC 2.0

Aplicación local para administrar campeonatos de fútbol. Frontend React/Vite,
Axios, Zustand y React Router; API Laravel 12/PHP 8.2; MySQL `beesocc_v2`.

## Desarrollo local

1. Iniciar MySQL de XAMPP restringido a `127.0.0.1`.
2. Conservar el archivo privado `backend/.env` con la conexión MySQL existente.
   En una instalación nueva, copiar `.env.example` y configurar `DB_CONNECTION=mysql`,
   `DB_HOST=127.0.0.1` y `DB_DATABASE=beesocc_v2`, con las credenciales locales.
   El ejemplo original de Laravel utiliza SQLite: no confundirlo con la base principal.
3. Desde `backend`: `php artisan serve --host=127.0.0.1 --port=8000`.
4. Desde `frontend`: `npm run dev -- --host 127.0.0.1`.
5. Abrir la dirección que indique Vite. Su proxy conecta `/api` y `/storage` con Laravel.

No ejecutar el script Composer `setup` sobre una instalación existente como paso
de revisión: incluye instalación y migraciones. No se necesitan migraciones para
arrancar una base que ya está preparada.

El acceso y registro son formularios visuales: no crean cuentas ni autentican.
No introducir contraseñas reales. La aplicación y su API deben permanecer locales
o en una red privada. CORS autoriza localhost y 127.0.0.1; otra URL privada debe
declararse explícitamente mediante `CORS_ALLOWED_ORIGINS` (separadas por comas).
CORS no sustituye el aislamiento de red.

## Verificación

La [Fase 2 de diseño y experiencia de usuario](docs/fase-2-resultados.md) incluye
las pantallas refinadas, pruebas, capturas y pendientes de migración de CSS.

En `frontend`: `npm test`, `npm run test:unit`, `npm run lint`, `npm run build`.

La [auditoría de Fase 1](docs/estado-proyecto.md) documenta la base Tailwind,
los componentes reutilizables y la preparación de Vitest, sin avanzar a Fase 2.

Para las pruebas del backend, seguir [las instrucciones de aislamiento](backend/tests/README.md).
Nunca utilizar `beesocc_v2` para las pruebas con escrituras. No ejecutar comandos
de limpieza de tablas ni modificar las migraciones existentes.

La [revisión final del 1 de noviembre de 2026](docs/REVISION_FINAL_2026-11-01.md)
documenta los cambios, resultados, archivos y límites comprobados.

## Preparación de GitHub

Esta carpeta todavía no es un repositorio Git. Antes de publicar, revisar el
contenido que se añadirá y comprobar que se excluyen `.env`, credenciales, bases
SQLite, volcados MySQL, logs, dependencias y archivos temporales. Conservar los
archivos de bloqueo de dependencias, código, pruebas y branding oficial.
Publicar el código no implica publicar la API ni los datos. No se ha ejecutado
`git init`, ningún commit ni ninguna subida.
