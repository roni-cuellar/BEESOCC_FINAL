# Verificaciones antes de aplicar en la principal

1. Obtener autorización expresa y una ventana sin escrituras; este sprint no aplica la migración principal.
2. Crear y probar la restauración de un respaldo **actual**. El ensayo 3.2 usa el respaldo del 12/11, no el estado actual de producción.
3. Comprobar host, puerto, `SELECT DATABASE()` = `beesocc_v2`, cuenta, permisos, `DB_URL`, caché y alias de conexión antes de ejecutar comandos.
4. Revisar el estado pendiente y SHA-256 de `2026_11_14_235900_add_oficializacion_and_auditoria.php` contra `sprint32-evidencia.json`; comprobar InnoDB y tipos de claves existentes.
5. Tras autorización, aplicar únicamente esa ruta de migración con conexión explícita; no usar `migrate:fresh`, semillas, scripts de laboratorio ni migraciones ajenas.
6. Comparar filas/columnas antiguas y verificar las dos columnas, dos tablas, cinco FK RESTRICT e índices; históricos con `oficializado_at` nulo y un solo registro nuevo en `migrations`.
7. Repetir pruebas funcionales y concurrentes sobre una copia reciente; no usar partidos históricos de producción como fixtures.
8. Considerar los commits implícitos del DDL de MariaDB: no confiar en una transacción para revertir el esquema ni ejecutar `down()` destruyendo auditoría.
