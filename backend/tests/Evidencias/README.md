# Evidencias revisadas para Git

Estos documentos conservan los resultados históricos de las verificaciones.
Sus rutas, nombres de bases y cuentas se sustituyeron por referencias genéricas;
se retiraron referencias a respaldos privados y el detalle de huellas deportivas
históricas. No son datos nuevos ni verificaciones ejecutadas otra vez.

No usar estos resúmenes como configuración, fixture, respaldo SQL o manifiesto
de autorización para migrar. Las herramientas operativas siguen leyendo los
archivos locales generados en `tests/Integral/` o en el directorio temporal.
No reemplazar controles de identidad por los marcadores de estas evidencias.

Se conservan seis evidencias agregadas en su ubicación original; dieciocho se
publican depuradas y cinco originales quedan excluidos con un resumen aquí.
Las copias privadas anteriores de las dos evidencias rastreadas depuradas se
conservan localmente en `tests/Integral/privadas-locales/`, ignorado por Git.
Las dos retiradas del índice se mantienen físicamente en su ruta original.

## Reproducir los laboratorios

- Generar una clave temporal nueva de al menos 16 caracteres y suministrarla
  mediante `BEESOCC_LAB_PASSWORD` a las semillas PHP y a los clientes Node.
  No guardarla en código, evidencias, capturas, archivos versionados o historial
  de comandos. PHP y Node deben recibir el mismo valor en ese laboratorio.
- Configurar `BEESOCC_PHP_BINARY` y `BEESOCC_MARIADB_INSTALL_BINARY` con rutas
  absolutas de ejecutables locales al iniciar una instancia nueva.
- Para una exportación expresamente autorizada, configurar `BEESOCC_BACKUP_DIR`
  fuera del proyecto y `BEESOCC_MYSQLDUMP_BINARY`. El respaldo SQL nunca se versiona.
- Mantener `BEESOCC_MARIADB_CONFIG`, `BEESOCC_INTEGRAL_CONFIG` y
  `BEESOCC_INTEGRAL_DIR` apuntando al laboratorio nuevo. Sus archivos privados
  se generan localmente y no se sustituyen por estos resúmenes.
- Conservar las comprobaciones existentes de puerto 3317, directorios físicos
  separados, cuenta limitada, permisos y denegación de acceso a la principal.
- Regenerar las fixtures y ejecutar las etapas en el orden documentado en cada
  sprint; no repetir etapas con autorizaciones ya consumidas.

Las comprobaciones históricas que requieren un SQL privado necesitan ese insumo
externo y autorización independiente. Esta preparación de Git no autoriza
ejecutar herramientas contra MariaDB ni migraciones en la principal.
