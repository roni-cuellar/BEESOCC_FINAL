# BEESOCC FINAL

Estructura: backend/, frontend/, documentacion/ y Git.

## Inicio local

Iniciar Apache y MySQL desde XAMPP. MariaDB 10.4.32 utiliza
C:\xampp\mysql\data, puerto 3306. MySQL80 permanece detenido y en inicio manual.
phpMyAdmin: http://localhost/phpmyadmin/.

Abrir dos terminales desde la raíz:

    cd backend
    C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000

    cd frontend
    npm run dev -- --host localhost --port 5173 --strictPort

Aplicación: http://localhost:5173. Backend configurado en backend/.env para BEESOCC.
El esquema tiene 29 tablas y las
22 migraciones reales aplicadas sobre una base nueva, incluida 000400.
No se importaron datos históricos. No se necesitan migraciones para arrancar.

## Verificación y documentación

Desde frontend: npm test, npm run test:unit, npm run lint,
npm run format:check y npm run build.

[manuales existentes](documentacion/manuales_actuales/) e
[históricos](documentacion/historico_sprints/).
Los manuales e informes históricos reflejan sus versiones originales.
No incorporar secretos, SQL, archivos .env ni archivos privados a Git.
