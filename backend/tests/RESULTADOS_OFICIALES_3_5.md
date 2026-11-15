# Sprint 3.5 — Integración real

Rama `develop`, sin commits ni push. No se modificó código productivo ni se encontraron errores funcionales.
Se reutilizaron `preparar.php`, `entorno.php`, `sembrar.php`, `servidor.php` y las herramientas de instancia separada.

Laboratorio: MariaDB 10.4.32 en puerto 3317, base nueva
`beesocc_23c_test_20261115_023111_8d58dafe`, cuenta limitada y archivos de motor separados.
Laravel atendió en 8013 y React en 5175 mediante proxy temporal. No se modificaron archivos `.env`.
La cuenta no puede acceder a la principal; se comprobó antes de migrar y sembrar.
Solo se migró la base nueva. Datos y credenciales ficticios del escenario 2.3C reutilizados.

Navegador real, con cookies, sesiones y CSRF de Sanctum: asignación, inicio, gol, asistencia,
autogol, oficialización 2–0 y cierre ordinario; solicitud y aprobación; fallo atómico 409;
lote válido, consumo único y marcador 1–0; auditoría con tres eventos antes y uno después,
árbitro #2 y administrador #1; solicitud rechazada; estadísticas sin gol/asistencia eliminados;
revocación y usuario ajeno sin acta privada. El doble clic no duplicó gol ni aplicación.
Móvil: aplicación real en marco de 390 px, cuerpo de 375 px sin desbordamiento;
consulta, preparación y cancelación del lote, y bloqueo tras revocar.

HTTP real adicional: **39 comprobaciones** (34 activo y 5 revocado), con errores
401/403/404/409/422, rechazo CSRF 419 y dos fallos tardíos que no alteraron eventos,
auditoría ni la aprobación. Evidencias: `Integral/sprint35-http-*.json` y `sprint35-evidencia.json`.
Las 259 peticiones capturadas incluyen recargas y son tráfico, no 259 pruebas independientes.

Regresión final: **125 pruebas backend, 2.404 aserciones en SQLite en memoria**;
**152 frontend** (47 servicios y 105 unitarias con contratos simulados/representación estática).
Lint, build, Pint y `git diff --check` aprobados. Las simuladas no se cuentan como integración.

Principal intacta: mismas estructuras y huellas de datos en **21 tablas y 49 registros**,
incluida la fila de migración agregada en 3.3; configuración y migración sin cambios.
Nuevos archivos: herramientas `Integral/sprint35-*`, esta nota y
`frontend/tests/browser/integral-resultados-movil.html`. No hubo correcciones productivas.
Servicios del laboratorio detenidos; base y archivos temporales conservados, sin eliminar bases anteriores.
Sin bloqueos nuevos. DP-07, régimen de históricos y publicación de solo oficiales continúan fuera de alcance.
