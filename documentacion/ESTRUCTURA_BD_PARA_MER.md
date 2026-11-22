# BEESOCC FINAL — Estructura de la base de datos para el MER

## 1. Fuente, alcance e inventario

Revisión del 18 de noviembre de 2026 sobre la rama `develop`, commit `ef79f62`. La configuración local apunta a `BEESOCC` en `127.0.0.1:3306`. Se intentó ejecutar únicamente un `SELECT` de identidad. MariaDB rechazó la conexión (error 2002, Windows 10061). Quedaron sin verificar la identidad del servidor en vivo, el contenido de `INFORMATION_SCHEMA` y los registros de `BEESOCC.migrations`.

Este documento describe el **esquema esperado según las migraciones versionadas** y las relaciones de los modelos actuales. Los conteos derivados son **29 tablas, 228 columnas, 44 claves foráneas declaradas, 19 restricciones UNIQUE y 9 índices explícitos no únicos**, además de las claves primarias e índices auxiliares de FK. La tabla técnica `migrations` la define el repositorio de migraciones de Laravel. Los conteos, índices, tipos y restricciones de este documento requieren contraste con MariaDB en vivo.

Fuentes revisadas. [Migraciones](../backend/database/migrations) · [Modelos Eloquent](../backend/app/Models) · [Repositorio de migraciones de Laravel](../backend/vendor/laravel/framework/src/Illuminate/Database/Migrations/DatabaseMigrationRepository.php).

| Módulo | Tablas esperadas | Cantidad |
| --- | --- | --- |
| Usuarios y autenticación | `users`, `password_reset_tokens`, `sessions`, `personal_access_tokens` | 4 |
| Campeonatos y configuración | `campeonatos`, `configuracion_campeonato` | 2 |
| Equipos, inscripciones y jugadores | `equipos`, `jugadores`, `campeonato_equipos`, `plantillas` | 4 |
| Árbitros y autorizaciones | `asignaciones_arbitro`, `autorizaciones_correccion` | 2 |
| Partidos, eventos y actas | `partidos`, `equipo_partido`, `eventos_estadisticos` | 3 |
| Grupos, clasificación y eliminación | `fases`, `grupos`, `fase_equipos`, `rondas`, `llaves`, `llave_plazas`, `grupo_desempates` | 7 |
| Resoluciones administrativas y auditoría | `auditoria_cambios` | 1 |
| Otras tablas técnicas | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` | 6 |

### Migraciones versionadas

Los siguientes **22 archivos** definen el esquema esperado, en orden de ejecución. El estado aplicado y el lote de cada archivo en la principal quedaron pendientes de comprobación.

1. [0001_01_01_000000_create_users_table.php](../backend/database/migrations/0001_01_01_000000_create_users_table.php)
1. [0001_01_01_000001_create_cache_table.php](../backend/database/migrations/0001_01_01_000001_create_cache_table.php)
1. [0001_01_01_000002_create_jobs_table.php](../backend/database/migrations/0001_01_01_000002_create_jobs_table.php)
1. [2026_10_25_000000_create_campeonatos_table.php](../backend/database/migrations/2026_10_25_000000_create_campeonatos_table.php)
1. [2026_10_25_000001_create_equipos_table.php](../backend/database/migrations/2026_10_25_000001_create_equipos_table.php)
1. [2026_10_25_000002_create_jugadores_table.php](../backend/database/migrations/2026_10_25_000002_create_jugadores_table.php)
1. [2026_10_25_000003_create_configuracion_campeonato_table.php](../backend/database/migrations/2026_10_25_000003_create_configuracion_campeonato_table.php)
1. [2026_10_25_000004_create_campeonato_equipos_table.php](../backend/database/migrations/2026_10_25_000004_create_campeonato_equipos_table.php)
1. [2026_10_25_000005_create_plantillas_table.php](../backend/database/migrations/2026_10_25_000005_create_plantillas_table.php)
1. [2026_10_25_000006_create_partidos_table.php](../backend/database/migrations/2026_10_25_000006_create_partidos_table.php)
1. [2026_10_25_000007_create_equipo_partido_table.php](../backend/database/migrations/2026_10_25_000007_create_equipo_partido_table.php)
1. [2026_10_25_000008_create_eventos_estadisticos_table.php](../backend/database/migrations/2026_10_25_000008_create_eventos_estadisticos_table.php)
1. [2026_11_12_224943_create_personal_access_tokens_table.php](../backend/database/migrations/2026_11_12_224943_create_personal_access_tokens_table.php)
1. [2026_11_12_235000_add_administrador_id_to_campeonatos_table.php](../backend/database/migrations/2026_11_12_235000_add_administrador_id_to_campeonatos_table.php)
1. [2026_11_12_235100_add_administrador_id_to_equipos_and_jugadores.php](../backend/database/migrations/2026_11_12_235100_add_administrador_id_to_equipos_and_jugadores.php)
1. [2026_11_12_235200_add_publicado_to_campeonatos_table.php](../backend/database/migrations/2026_11_12_235200_add_publicado_to_campeonatos_table.php)
1. [2026_11_12_235300_create_asignaciones_arbitro_table.php](../backend/database/migrations/2026_11_12_235300_create_asignaciones_arbitro_table.php)
1. [2026_11_14_235900_add_oficializacion_and_auditoria.php](../backend/database/migrations/2026_11_14_235900_add_oficializacion_and_auditoria.php)
1. [2026_11_15_000100_create_estructura_eliminacion.php](../backend/database/migrations/2026_11_15_000100_create_estructura_eliminacion.php)
1. [2026_11_15_000200_create_desempates_grupos.php](../backend/database/migrations/2026_11_15_000200_create_desempates_grupos.php)
1. [2026_11_15_000300_add_ciclo_y_minimos_campeonato.php](../backend/database/migrations/2026_11_15_000300_add_ciclo_y_minimos_campeonato.php)
1. [2026_11_16_000400_add_resoluciones_administrativas.php](../backend/database/migrations/2026_11_16_000400_add_resoluciones_administrativas.php)

## 2. Diccionario de datos completo

`—` en «Predeterminado» indica que la declaración no fija un valor. `NULL` indica el valor implícito esperado para una columna declarada nullable sin otro predeterminado. Los nombres y el orden de columnas se derivan del código. Las columnas de auditoría que guardan fechas tienen sus tipos propios y se incluyen incluso en tablas sin `created_at` ni `updated_at`.

¹ La declaración Laravel `json()` se representa como `JSON` en este diccionario. En MariaDB `JSON` es un alias de `LONGTEXT`. El tipo reportado, colación y restricciones JSON concretas quedan pendientes de inspección. `BOOLEAN` se traduce a `TINYINT(1)` y el ancho de visualización de otros enteros queda fuera del diccionario.

Las marcas PK, FK, UNIQUE e INDEX corresponden a declaraciones esperadas. **Cada PK es de una columna.** Las claves compuestas de este esquema son restricciones UNIQUE o índices. Los índices se detallan por tabla con sus columnas en orden, y sus nombres son los explícitos o convencionales de Laravel.

### Usuarios y autenticación

#### `users`

Origen: [0001_01_01_000000_create_users_table](../backend/database/migrations/0001_01_01_000000_create_users_table.php). Modelo: [`User`](../backend/app/Models/User.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `name` | `VARCHAR(255)` | No | — | — |
| `email` | `VARCHAR(255)` | No | — | UNIQUE |
| `email_verified_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `password` | `VARCHAR(255)` | No | — | — |
| `remember_token` | `VARCHAR(100)` | Sí | `NULL` | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |

Índices declarados: PK (`id`) · UNIQUE `users_email_unique` (`email`).

#### `password_reset_tokens`

Origen: [0001_01_01_000000_create_users_table](../backend/database/migrations/0001_01_01_000000_create_users_table.php). Sin modelo propio en `app/Models`.

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `email` | `VARCHAR(255)` | No | — | PK |
| `token` | `VARCHAR(255)` | No | — | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |

Índices declarados: PK (`email`).

#### `sessions`

Origen: [0001_01_01_000000_create_users_table](../backend/database/migrations/0001_01_01_000000_create_users_table.php). Sin modelo propio en `app/Models`.

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `VARCHAR(255)` | No | — | PK |
| `user_id` | `BIGINT UNSIGNED` | Sí | `NULL` | INDEX |
| `ip_address` | `VARCHAR(45)` | Sí | `NULL` | — |
| `user_agent` | `TEXT` | Sí | `NULL` | — |
| `payload` | `LONGTEXT` | No | — | — |
| `last_activity` | `INT` | No | — | INDEX |

Índices declarados: PK (`id`) · INDEX `sessions_user_id_index` (`user_id`) · INDEX `sessions_last_activity_index` (`last_activity`).

#### `personal_access_tokens`

Origen: [2026_11_12_224943_create_personal_access_tokens_table](../backend/database/migrations/2026_11_12_224943_create_personal_access_tokens_table.php). Sin modelo propio en `app/Models`.

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `tokenable_type` | `VARCHAR(255)` | No | — | — |
| `tokenable_id` | `BIGINT UNSIGNED` | No | — | — |
| `name` | `TEXT` | No | — | — |
| `token` | `VARCHAR(64)` | No | — | UNIQUE |
| `abilities` | `TEXT` | Sí | `NULL` | — |
| `last_used_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `expires_at` | `TIMESTAMP` | Sí | `NULL` | INDEX |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |

Índices declarados: PK (`id`) · INDEX `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`) · UNIQUE `personal_access_tokens_token_unique` (`token`) · INDEX `personal_access_tokens_expires_at_index` (`expires_at`).

### Campeonatos y configuración

#### `campeonatos`

Origen: [2026_10_25_000000_create_campeonatos_table](../backend/database/migrations/2026_10_25_000000_create_campeonatos_table.php), [2026_11_12_235000_add_administrador_id_to_campeonatos_table](../backend/database/migrations/2026_11_12_235000_add_administrador_id_to_campeonatos_table.php), [2026_11_12_235200_add_publicado_to_campeonatos_table](../backend/database/migrations/2026_11_12_235200_add_publicado_to_campeonatos_table.php), [2026_11_15_000300_add_ciclo_y_minimos_campeonato](../backend/database/migrations/2026_11_15_000300_add_ciclo_y_minimos_campeonato.php). Modelo: [`Campeonato`](../backend/app/Models/Campeonato.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `nombre` | `VARCHAR(150)` | No | — | — |
| `descripcion` | `TEXT` | Sí | `NULL` | — |
| `temporada` | `VARCHAR(50)` | No | — | — |
| `formato` | `VARCHAR(30)` | No | — | — |
| `estado` | `VARCHAR(20)` | No | `'planificacion'` | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `administrador_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `publicado` | `TINYINT(1)` | No | `0` | — |
| `iniciado_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `finalizado_at` | `TIMESTAMP` | Sí | `NULL` | — |

Índices declarados: PK (`id`).

#### `configuracion_campeonato`

Origen: [2026_10_25_000003_create_configuracion_campeonato_table](../backend/database/migrations/2026_10_25_000003_create_configuracion_campeonato_table.php), [2026_11_15_000300_add_ciclo_y_minimos_campeonato](../backend/database/migrations/2026_11_15_000300_add_ciclo_y_minimos_campeonato.php). Modelo: [`ConfiguracionCampeonato`](../backend/app/Models/ConfiguracionCampeonato.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `campeonato_id` | `BIGINT UNSIGNED` | No | — | UNIQUE, FK |
| `numero_vueltas` | `TINYINT UNSIGNED` | No | `1` | — |
| `orden_desempate` | `VARCHAR(100)` | No | `'puntos,diferencia_goles,goles_favor'` | — |
| `inicio_inscripciones` | `DATE` | Sí | `NULL` | — |
| `fin_inscripciones` | `DATE` | Sí | `NULL` | — |
| `inicio_transferencias` | `DATE` | Sí | `NULL` | — |
| `fin_transferencias` | `DATE` | Sí | `NULL` | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `modalidad` | `VARCHAR(50)` | Sí | `NULL` | — |
| `minimo_jugadores` | `TINYINT UNSIGNED` | Sí | `NULL` | — |

Índices declarados: PK (`id`) · UNIQUE `configuracion_campeonato_campeonato_id_unique` (`campeonato_id`).

### Equipos, inscripciones y jugadores

#### `equipos`

Origen: [2026_10_25_000001_create_equipos_table](../backend/database/migrations/2026_10_25_000001_create_equipos_table.php), [2026_11_12_235100_add_administrador_id_to_equipos_and_jugadores](../backend/database/migrations/2026_11_12_235100_add_administrador_id_to_equipos_and_jugadores.php). Modelo: [`Equipo`](../backend/app/Models/Equipo.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `nombre` | `VARCHAR(150)` | No | — | — |
| `ciudad` | `VARCHAR(100)` | No | — | — |
| `escudo` | `VARCHAR(255)` | Sí | `NULL` | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `administrador_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |

Índices declarados: PK (`id`).

#### `jugadores`

Origen: [2026_10_25_000002_create_jugadores_table](../backend/database/migrations/2026_10_25_000002_create_jugadores_table.php), [2026_11_12_235100_add_administrador_id_to_equipos_and_jugadores](../backend/database/migrations/2026_11_12_235100_add_administrador_id_to_equipos_and_jugadores.php). Modelo: [`Jugador`](../backend/app/Models/Jugador.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `nombre` | `VARCHAR(100)` | No | — | — |
| `apellido` | `VARCHAR(100)` | No | — | — |
| `fecha_nacimiento` | `DATE` | No | — | — |
| `foto` | `VARCHAR(255)` | Sí | `NULL` | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `administrador_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |

Índices declarados: PK (`id`).

#### `campeonato_equipos`

Origen: [2026_10_25_000004_create_campeonato_equipos_table](../backend/database/migrations/2026_10_25_000004_create_campeonato_equipos_table.php), [2026_11_16_000400_add_resoluciones_administrativas](../backend/database/migrations/2026_11_16_000400_add_resoluciones_administrativas.php). Modelo: [`CampeonatoEquipo`](../backend/app/Models/CampeonatoEquipo.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `campeonato_id` | `BIGINT UNSIGNED` | No | — | FK |
| `equipo_id` | `BIGINT UNSIGNED` | No | — | FK |
| `estado` | `VARCHAR(20)` | No | `'activo'` | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `motivo_inactividad` | `TEXT` | Sí | `NULL` | — |
| `inactivado_at` | `DATETIME` | Sí | `NULL` | — |

Índices declarados: PK (`id`) · UNIQUE `campeonato_equipos_campeonato_id_equipo_id_unique` (`campeonato_id`, `equipo_id`).

#### `plantillas`

Origen: [2026_10_25_000005_create_plantillas_table](../backend/database/migrations/2026_10_25_000005_create_plantillas_table.php), [2026_11_15_000300_add_ciclo_y_minimos_campeonato](../backend/database/migrations/2026_11_15_000300_add_ciclo_y_minimos_campeonato.php). Modelo: [`Plantilla`](../backend/app/Models/Plantilla.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `campeonato_equipo_id` | `BIGINT UNSIGNED` | No | — | FK |
| `jugador_id` | `BIGINT UNSIGNED` | No | — | FK |
| `dorsal` | `TINYINT UNSIGNED` | No | — | — |
| `posicion` | `VARCHAR(50)` | No | — | — |
| `fecha_ingreso` | `DATE` | No | — | — |
| `fecha_salida` | `DATE` | Sí | `NULL` | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `dorsal_vigente` | `TINYINT UNSIGNED` | Sí | — | VIRTUAL `CASE WHEN fecha_salida IS NULL THEN dorsal ELSE NULL END` |

Índices declarados: PK (`id`) · UNIQUE `plantillas_dorsal_vigente_unique` (`campeonato_equipo_id`, `dorsal_vigente`).

### Árbitros y autorizaciones

#### `asignaciones_arbitro`

Origen: [2026_11_12_235300_create_asignaciones_arbitro_table](../backend/database/migrations/2026_11_12_235300_create_asignaciones_arbitro_table.php). Modelo: [`AsignacionArbitro`](../backend/app/Models/AsignacionArbitro.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `campeonato_id` | `BIGINT UNSIGNED` | No | — | FK |
| `usuario_id` | `BIGINT UNSIGNED` | No | — | FK |
| `autorizado_por` | `BIGINT UNSIGNED` | No | — | FK |
| `fecha_inicio` | `DATETIME` | No | — | — |
| `fecha_fin` | `DATETIME` | Sí | `NULL` | — |
| `motivo_revocacion` | `TEXT` | Sí | `NULL` | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `campeonato_activo_id` | `BIGINT UNSIGNED` | Sí | — | UNIQUE, STORED `CASE WHEN fecha_fin IS NULL THEN campeonato_id ELSE NULL END` |

Índices declarados: PK (`id`) · UNIQUE `asignaciones_arbitro_activa_unique` (`campeonato_activo_id`) · INDEX `asignaciones_arbitro_campeonato_id_fecha_fin_index` (`campeonato_id`, `fecha_fin`).

#### `autorizaciones_correccion`

Origen: [2026_11_14_235900_add_oficializacion_and_auditoria](../backend/database/migrations/2026_11_14_235900_add_oficializacion_and_auditoria.php). Modelo: [`AutorizacionCorreccion`](../backend/app/Models/AutorizacionCorreccion.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `partido_id` | `BIGINT UNSIGNED` | No | — | FK |
| `solicitado_por_id` | `BIGINT UNSIGNED` | No | — | FK |
| `autorizado_por_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `motivo` | `TEXT` | No | — | — |
| `estado` | `ENUM('solicitada', 'aprobada', 'rechazada', 'consumida')` | No | — | — |
| `alcance` | `ENUM('eventos', 'resultado')` | No | — | — |
| `fecha_resolucion` | `DATETIME` | Sí | `NULL` | — |
| `fecha_consumo` | `DATETIME` | Sí | `NULL` | — |

Índices declarados: PK (`id`).

### Partidos, eventos y actas

#### `partidos`

Origen: [2026_10_25_000006_create_partidos_table](../backend/database/migrations/2026_10_25_000006_create_partidos_table.php), [2026_11_14_235900_add_oficializacion_and_auditoria](../backend/database/migrations/2026_11_14_235900_add_oficializacion_and_auditoria.php), [2026_11_15_000100_create_estructura_eliminacion](../backend/database/migrations/2026_11_15_000100_create_estructura_eliminacion.php), [2026_11_15_000200_create_desempates_grupos](../backend/database/migrations/2026_11_15_000200_create_desempates_grupos.php), [2026_11_16_000400_add_resoluciones_administrativas](../backend/database/migrations/2026_11_16_000400_add_resoluciones_administrativas.php). Modelo: [`Partido`](../backend/app/Models/Partido.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `campeonato_id` | `BIGINT UNSIGNED` | No | — | FK |
| `fecha_hora` | `DATETIME` | Sí | `NULL` | — |
| `jornada` | `INT UNSIGNED` | No | — | — |
| `lugar` | `VARCHAR(150)` | Sí | `NULL` | — |
| `estado` | `VARCHAR(20)` | No | `'programado'` | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `tipo_resultado` | `ENUM('deportivo', 'administrativo')` | No | `'deportivo'` | — |
| `oficializado_at` | `DATETIME` | Sí | `NULL` | — |
| `fase_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `grupo_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `llave_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `numero_serie` | `TINYINT UNSIGNED` | Sí | `NULL` | — |
| `tipo_partido` | `VARCHAR(20)` | No | `'regular'` | — |
| `grupo_desempate_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `administrativo_beneficiado_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `administrativo_sancionado_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `administrativo_por_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `motivo_administrativo` | `TEXT` | Sí | `NULL` | — |

Índices declarados: PK (`id`) · UNIQUE `partidos_llave_id_numero_serie_unique` (`llave_id`, `numero_serie`).

#### `equipo_partido`

Origen: [2026_10_25_000007_create_equipo_partido_table](../backend/database/migrations/2026_10_25_000007_create_equipo_partido_table.php). Modelo: [`EquipoPartido`](../backend/app/Models/EquipoPartido.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `partido_id` | `BIGINT UNSIGNED` | No | — | FK |
| `campeonato_equipo_id` | `BIGINT UNSIGNED` | No | — | FK |
| `rol` | `VARCHAR(20)` | No | — | — |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |

Índices declarados: PK (`id`) · UNIQUE `equipo_partido_partido_id_rol_unique` (`partido_id`, `rol`) · UNIQUE `equipo_partido_partido_id_campeonato_equipo_id_unique` (`partido_id`, `campeonato_equipo_id`).

#### `eventos_estadisticos`

Origen: [2026_10_25_000008_create_eventos_estadisticos_table](../backend/database/migrations/2026_10_25_000008_create_eventos_estadisticos_table.php). Modelo: [`EventoEstadistico`](../backend/app/Models/EventoEstadistico.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `equipo_partido_id` | `BIGINT UNSIGNED` | No | — | FK |
| `plantilla_id` | `BIGINT UNSIGNED` | No | — | FK |
| `tipo` | `VARCHAR(30)` | No | — | — |
| `minuto` | `SMALLINT UNSIGNED` | No | — | — |
| `gol_relacionado_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `created_at` | `TIMESTAMP` | Sí | `NULL` | — |
| `updated_at` | `TIMESTAMP` | Sí | `NULL` | — |

Índices declarados: PK (`id`).

### Grupos, clasificación y eliminación

#### `fases`

Origen: [2026_11_15_000100_create_estructura_eliminacion](../backend/database/migrations/2026_11_15_000100_create_estructura_eliminacion.php), [2026_11_15_000200_create_desempates_grupos](../backend/database/migrations/2026_11_15_000200_create_desempates_grupos.php). Modelo: [`Fase`](../backend/app/Models/Fase.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `campeonato_id` | `BIGINT UNSIGNED` | No | — | FK |
| `orden` | `SMALLINT UNSIGNED` | No | — | — |
| `nombre` | `VARCHAR(80)` | No | — | — |
| `tipo` | `VARCHAR(20)` | No | — | — |
| `estado` | `VARCHAR(20)` | No | `'planificacion'` | — |
| `clasificados_por_grupo` | `TINYINT UNSIGNED` | Sí | `NULL` | — |
| `fase_origen_id` | `BIGINT UNSIGNED` | Sí | `NULL` | UNIQUE, FK |
| `clasificacion_huella` | `VARCHAR(64)` | Sí | `NULL` | — |

Índices declarados: PK (`id`) · UNIQUE `fases_campeonato_id_orden_unique` (`campeonato_id`, `orden`) · UNIQUE `fases_fase_origen_id_unique` (`fase_origen_id`).

#### `grupos`

Origen: [2026_11_15_000100_create_estructura_eliminacion](../backend/database/migrations/2026_11_15_000100_create_estructura_eliminacion.php). Modelo: [`Grupo`](../backend/app/Models/Grupo.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `fase_id` | `BIGINT UNSIGNED` | No | — | FK |
| `codigo` | `VARCHAR(12)` | No | — | — |
| `orden` | `SMALLINT UNSIGNED` | No | — | — |

Índices declarados: PK (`id`) · UNIQUE `grupos_fase_id_codigo_unique` (`fase_id`, `codigo`).

#### `fase_equipos`

Origen: [2026_11_15_000100_create_estructura_eliminacion](../backend/database/migrations/2026_11_15_000100_create_estructura_eliminacion.php). Modelo: [`FaseEquipo`](../backend/app/Models/FaseEquipo.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `fase_id` | `BIGINT UNSIGNED` | No | — | FK |
| `campeonato_equipo_id` | `BIGINT UNSIGNED` | No | — | FK |
| `grupo_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `siembra` | `SMALLINT UNSIGNED` | Sí | `NULL` | — |
| `estado` | `VARCHAR(20)` | No | `'activo'` | — |

Índices declarados: PK (`id`) · UNIQUE `fase_equipos_fase_id_campeonato_equipo_id_unique` (`fase_id`, `campeonato_equipo_id`).

#### `rondas`

Origen: [2026_11_15_000100_create_estructura_eliminacion](../backend/database/migrations/2026_11_15_000100_create_estructura_eliminacion.php). Modelo: [`Ronda`](../backend/app/Models/Ronda.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `fase_id` | `BIGINT UNSIGNED` | No | — | FK |
| `numero` | `SMALLINT UNSIGNED` | No | — | — |
| `nombre` | `VARCHAR(60)` | No | — | — |
| `modalidad` | `ENUM('unico', 'ida_vuelta')` | No | — | — |
| `desempate` | `ENUM('penales', 'prorroga_penales')` | No | — | — |
| `estado` | `VARCHAR(20)` | No | `'planificacion'` | — |

Índices declarados: PK (`id`) · UNIQUE `rondas_fase_id_numero_unique` (`fase_id`, `numero`).

#### `llaves`

Origen: [2026_11_15_000100_create_estructura_eliminacion](../backend/database/migrations/2026_11_15_000100_create_estructura_eliminacion.php). Modelo: [`Llave`](../backend/app/Models/Llave.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `ronda_id` | `BIGINT UNSIGNED` | No | — | FK |
| `numero` | `SMALLINT UNSIGNED` | No | — | — |
| `estado` | `VARCHAR(20)` | No | `'pendiente'` | — |
| `ganador_fase_equipo_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `metodo_resolucion` | `VARCHAR(25)` | Sí | `NULL` | — |
| `penales_plaza_1` | `TINYINT UNSIGNED` | Sí | `NULL` | — |
| `penales_plaza_2` | `TINYINT UNSIGNED` | Sí | `NULL` | — |

Índices declarados: PK (`id`) · UNIQUE `llaves_ronda_id_numero_unique` (`ronda_id`, `numero`).

#### `llave_plazas`

Origen: [2026_11_15_000100_create_estructura_eliminacion](../backend/database/migrations/2026_11_15_000100_create_estructura_eliminacion.php). Modelo: [`LlavePlaza`](../backend/app/Models/LlavePlaza.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `llave_id` | `BIGINT UNSIGNED` | No | — | FK |
| `posicion` | `TINYINT UNSIGNED` | No | — | — |
| `fase_equipo_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `origen_tipo` | `ENUM('directo', 'grupo_puesto', 'ganador_llave', 'bye')` | No | — | — |
| `origen_grupo_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |
| `origen_puesto` | `TINYINT UNSIGNED` | Sí | `NULL` | — |
| `origen_llave_id` | `BIGINT UNSIGNED` | Sí | `NULL` | FK |

Índices declarados: PK (`id`) · UNIQUE `llave_plazas_llave_id_posicion_unique` (`llave_id`, `posicion`). CHECK `llave_plazas_posicion_check`: `posicion IN (1,2)`, condicionado al driver `mysql`.

#### `grupo_desempates`

Origen: [2026_11_15_000200_create_desempates_grupos](../backend/database/migrations/2026_11_15_000200_create_desempates_grupos.php). Modelo: [`GrupoDesempate`](../backend/app/Models/GrupoDesempate.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `grupo_id` | `BIGINT UNSIGNED` | No | — | FK |
| `clave` | `VARCHAR(64)` | No | — | — |
| `inscripciones` | `JSON¹` | No | — | — |
| `posicion_inicio` | `SMALLINT UNSIGNED` | No | — | — |
| `llave_id` | `BIGINT UNSIGNED` | Sí | `NULL` | UNIQUE, FK |

Índices declarados: PK (`id`) · UNIQUE `grupo_desempates_llave_id_unique` (`llave_id`) · UNIQUE `grupo_desempates_grupo_id_clave_unique` (`grupo_id`, `clave`).

### Resoluciones administrativas y auditoría

#### `auditoria_cambios`

Origen: [2026_11_14_235900_add_oficializacion_and_auditoria](../backend/database/migrations/2026_11_14_235900_add_oficializacion_and_auditoria.php). Modelo: [`AuditoriaCambio`](../backend/app/Models/AuditoriaCambio.php).

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `campeonato_id` | `BIGINT UNSIGNED` | No | — | FK |
| `usuario_id` | `BIGINT UNSIGNED` | No | — | FK |
| `entidad_tipo` | `VARCHAR(40)` | No | — | — |
| `entidad_id` | `BIGINT UNSIGNED` | No | — | — |
| `accion` | `VARCHAR(40)` | No | — | — |
| `motivo` | `TEXT` | No | — | — |
| `anterior_json` | `JSON¹` | Sí | `NULL` | — |
| `posterior_json` | `JSON¹` | Sí | `NULL` | — |
| `fecha` | `DATETIME` | No | — | — |

Índices declarados: PK (`id`) · INDEX `auditoria_cambios_campeonato_id_entidad_tipo_entidad_id_index` (`campeonato_id`, `entidad_tipo`, `entidad_id`).

### Otras tablas técnicas

#### `cache`

Origen: [0001_01_01_000001_create_cache_table](../backend/database/migrations/0001_01_01_000001_create_cache_table.php). Sin modelo propio en `app/Models`.

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `key` | `VARCHAR(255)` | No | — | PK |
| `value` | `MEDIUMTEXT` | No | — | — |
| `expiration` | `INT` | No | — | INDEX |

Índices declarados: PK (`key`) · INDEX `cache_expiration_index` (`expiration`).

#### `cache_locks`

Origen: [0001_01_01_000001_create_cache_table](../backend/database/migrations/0001_01_01_000001_create_cache_table.php). Sin modelo propio en `app/Models`.

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `key` | `VARCHAR(255)` | No | — | PK |
| `owner` | `VARCHAR(255)` | No | — | — |
| `expiration` | `INT` | No | — | INDEX |

Índices declarados: PK (`key`) · INDEX `cache_locks_expiration_index` (`expiration`).

#### `jobs`

Origen: [0001_01_01_000002_create_jobs_table](../backend/database/migrations/0001_01_01_000002_create_jobs_table.php). Sin modelo propio en `app/Models`.

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `queue` | `VARCHAR(255)` | No | — | INDEX |
| `payload` | `LONGTEXT` | No | — | — |
| `attempts` | `TINYINT UNSIGNED` | No | — | — |
| `reserved_at` | `INT UNSIGNED` | Sí | `NULL` | — |
| `available_at` | `INT UNSIGNED` | No | — | — |
| `created_at` | `INT UNSIGNED` | No | — | — |

Índices declarados: PK (`id`) · INDEX `jobs_queue_index` (`queue`).

#### `job_batches`

Origen: [0001_01_01_000002_create_jobs_table](../backend/database/migrations/0001_01_01_000002_create_jobs_table.php). Sin modelo propio en `app/Models`.

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `VARCHAR(255)` | No | — | PK |
| `name` | `VARCHAR(255)` | No | — | — |
| `total_jobs` | `INT` | No | — | — |
| `pending_jobs` | `INT` | No | — | — |
| `failed_jobs` | `INT` | No | — | — |
| `failed_job_ids` | `LONGTEXT` | No | — | — |
| `options` | `MEDIUMTEXT` | Sí | `NULL` | — |
| `cancelled_at` | `INT` | Sí | `NULL` | — |
| `created_at` | `INT` | No | — | — |
| `finished_at` | `INT` | Sí | `NULL` | — |

Índices declarados: PK (`id`).

#### `failed_jobs`

Origen: [0001_01_01_000002_create_jobs_table](../backend/database/migrations/0001_01_01_000002_create_jobs_table.php). Sin modelo propio en `app/Models`.

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `uuid` | `VARCHAR(255)` | No | — | UNIQUE |
| `connection` | `TEXT` | No | — | — |
| `queue` | `TEXT` | No | — | — |
| `payload` | `LONGTEXT` | No | — | — |
| `exception` | `LONGTEXT` | No | — | — |
| `failed_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | — |

Índices declarados: PK (`id`) · UNIQUE `failed_jobs_uuid_unique` (`uuid`).

#### `migrations`

Origen: repositorio de migraciones de Laravel. Sin modelo propio en `app/Models`.

| Columna | Tipo SQL esperado | Nulo | Predeterminado | Clave o detalle |
| --- | --- | --- | --- | --- |
| `id` | `INT UNSIGNED` | No | — | PK, AUTO_INCREMENT |
| `migration` | `VARCHAR(255)` | No | — | — |
| `batch` | `INT` | No | — | — |

Índices declarados: PK (`id`).

## 3. Relaciones y cardinalidades

Cada fila va de **origen dependiente (FK)** a **destino referenciado (PK)**. «Destino por origen» describe cuántas filas padre admite cada fila dependiente. «Orígenes por destino» da el máximo impuesto por FK y UNIQUE. Una fila padre puede existir con cero hijas en todas las relaciones de esta tabla. Las **44 FK** son declaraciones para el esquema físico esperado. Las acciones `ON DELETE` están declaradas en las migraciones. Las migraciones no fijan `ON UPDATE`, por lo que corresponde la acción restrictiva predeterminada de MariaDB.

| Origen (FK) | Destino (PK) | Cardinalidad (destino a origen) | Destino por origen | Orígenes por destino | ON DELETE | ON UPDATE | Contraparte Eloquent |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `campeonatos.administrador_id` | `users.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Campeonato::administrador (belongsTo)`<br>`User::campeonatos (hasMany)` |
| `configuracion_campeonato.campeonato_id` | `campeonatos.id` | 1:1 | 1 (obligatoria) | 0..1 | `CASCADE` | `RESTRICT (predet.)` | `Campeonato::configuracion (hasOne)`<br>`ConfiguracionCampeonato::campeonato (belongsTo)` |
| `equipos.administrador_id` | `users.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Equipo::administrador (belongsTo)`<br>`User::equipos (hasMany)` |
| `jugadores.administrador_id` | `users.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Jugador::administrador (belongsTo)`<br>`User::jugadores (hasMany)` |
| `campeonato_equipos.campeonato_id` | `campeonatos.id` | 1:N | 1 (obligatoria) | 0..N | `CASCADE` | `RESTRICT (predet.)` | `Campeonato::inscripcionesEquipos (hasMany)`<br>`CampeonatoEquipo::campeonato (belongsTo)` |
| `campeonato_equipos.equipo_id` | `equipos.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `CampeonatoEquipo::equipo (belongsTo)`<br>`Equipo::inscripcionesCampeonatos (hasMany)` |
| `plantillas.campeonato_equipo_id` | `campeonato_equipos.id` | 1:N | 1 (obligatoria) | 0..N | `CASCADE` | `RESTRICT (predet.)` | `CampeonatoEquipo::plantillas (hasMany)`<br>`Plantilla::campeonatoEquipo (belongsTo)` |
| `plantillas.jugador_id` | `jugadores.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Jugador::plantillas (hasMany)`<br>`Plantilla::jugador (belongsTo)` |
| `asignaciones_arbitro.campeonato_id` | `campeonatos.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `AsignacionArbitro::campeonato (belongsTo)`<br>`Campeonato::asignacionesArbitro (hasMany)` |
| `asignaciones_arbitro.usuario_id` | `users.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `AsignacionArbitro::usuario (belongsTo)`<br>`User::asignacionesArbitro (hasMany)` |
| `asignaciones_arbitro.autorizado_por` | `users.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `AsignacionArbitro::autorizador (belongsTo)`<br>`User::autorizacionesArbitro (hasMany)` |
| `autorizaciones_correccion.partido_id` | `partidos.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `AutorizacionCorreccion::partido (belongsTo)`<br>`Partido::correcciones (hasMany)` |
| `autorizaciones_correccion.solicitado_por_id` | `users.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `AutorizacionCorreccion::solicitante (belongsTo)` |
| `autorizaciones_correccion.autorizado_por_id` | `users.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `AutorizacionCorreccion::autorizador (belongsTo)` |
| `partidos.campeonato_id` | `campeonatos.id` | 1:N | 1 (obligatoria) | 0..N | `CASCADE` | `RESTRICT (predet.)` | `Campeonato::partidos (hasMany)`<br>`Partido::campeonato (belongsTo)` |
| `partidos.fase_id` | `fases.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Fase::partidos (hasMany)`<br>`Partido::fase (belongsTo)` |
| `partidos.grupo_id` | `grupos.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Grupo::partidos (hasMany)`<br>`Partido::grupo (belongsTo)` |
| `partidos.llave_id` | `llaves.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Llave::partidos (hasMany)`<br>`Partido::llave (belongsTo)` |
| `partidos.grupo_desempate_id` | `grupo_desempates.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `GrupoDesempate::partidos (hasMany)`<br>`Partido::desempateGrupo (belongsTo)` |
| `partidos.administrativo_beneficiado_id` | `campeonato_equipos.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | Sin método dedicado |
| `partidos.administrativo_sancionado_id` | `campeonato_equipos.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | Sin método dedicado |
| `partidos.administrativo_por_id` | `users.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | Sin método dedicado |
| `equipo_partido.partido_id` | `partidos.id` | 1:N | 1 (obligatoria) | 0..N | `CASCADE` | `RESTRICT (predet.)` | `EquipoPartido::partido (belongsTo)`<br>`Partido::participantes (hasMany)` |
| `equipo_partido.campeonato_equipo_id` | `campeonato_equipos.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `CampeonatoEquipo::participacionesPartidos (hasMany)`<br>`EquipoPartido::campeonatoEquipo (belongsTo)` |
| `eventos_estadisticos.equipo_partido_id` | `equipo_partido.id` | 1:N | 1 (obligatoria) | 0..N | `CASCADE` | `RESTRICT (predet.)` | `EquipoPartido::eventos (hasMany)`<br>`EventoEstadistico::equipoPartido (belongsTo)` |
| `eventos_estadisticos.plantilla_id` | `plantillas.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `EventoEstadistico::plantilla (belongsTo)`<br>`Plantilla::eventos (hasMany)` |
| `eventos_estadisticos.gol_relacionado_id` | `eventos_estadisticos.id` | 1:N | 0..1 (opcional) | 0..N | `SET NULL` | `RESTRICT (predet.)` | `EventoEstadistico::golRelacionado (belongsTo)` |
| `fases.campeonato_id` | `campeonatos.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Campeonato::fases (hasMany)`<br>`Fase::campeonato (belongsTo)` |
| `fases.fase_origen_id` | `fases.id` | 1:1 | 0..1 (opcional) | 0..1 | `RESTRICT` | `RESTRICT (predet.)` | Sin método dedicado |
| `grupos.fase_id` | `fases.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Fase::grupos (hasMany)`<br>`Grupo::fase (belongsTo)` |
| `fase_equipos.fase_id` | `fases.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Fase::equipos (hasMany)`<br>`FaseEquipo::fase (belongsTo)` |
| `fase_equipos.campeonato_equipo_id` | `campeonato_equipos.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `FaseEquipo::inscripcion (belongsTo)` |
| `fase_equipos.grupo_id` | `grupos.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `FaseEquipo::grupo (belongsTo)`<br>`Grupo::equipos (hasMany)` |
| `rondas.fase_id` | `fases.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Fase::rondas (hasMany)`<br>`Ronda::fase (belongsTo)` |
| `llaves.ronda_id` | `rondas.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Llave::ronda (belongsTo)`<br>`Ronda::llaves (hasMany)` |
| `llaves.ganador_fase_equipo_id` | `fase_equipos.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Llave::ganador (belongsTo)` |
| `llave_plazas.llave_id` | `llaves.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `Llave::plazas (hasMany)`<br>`LlavePlaza::llave (belongsTo)` |
| `llave_plazas.fase_equipo_id` | `fase_equipos.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `LlavePlaza::participante (belongsTo)` |
| `llave_plazas.origen_grupo_id` | `grupos.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `LlavePlaza::origenGrupo (belongsTo)` |
| `llave_plazas.origen_llave_id` | `llaves.id` | 1:N | 0..1 (opcional) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `LlavePlaza::origen (belongsTo)` |
| `grupo_desempates.grupo_id` | `grupos.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `GrupoDesempate::grupo (belongsTo)` |
| `grupo_desempates.llave_id` | `llaves.id` | 1:1 | 0..1 (opcional) | 0..1 | `RESTRICT` | `RESTRICT (predet.)` | `GrupoDesempate::llave (belongsTo)` |
| `auditoria_cambios.campeonato_id` | `campeonatos.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `AuditoriaCambio::campeonato (belongsTo)` |
| `auditoria_cambios.usuario_id` | `users.id` | 1:N | 1 (obligatoria) | 0..N | `RESTRICT` | `RESTRICT (predet.)` | `AuditoriaCambio::usuario (belongsTo)` |

### Entidades asociativas y rutas N:M

Cada fila asociativa exige un registro de ambos extremos. Cada extremo admite cero o muchas filas asociativas. Son relaciones derivadas de dos FK y **no se suman al conteo de 44 FK**. Los modelos usan asociaciones explícitas mediante `belongsTo` y `hasMany`.

| Extremos N:M | Entidad asociativa y FK | Restricción del par |
| --- | --- | --- |
| Campeonato ↔ Equipo | `campeonato_equipos (campeonato_id, equipo_id)` | UNIQUE del par. La inscripción tiene estado e historial administrativo. |
| Inscripción de equipo ↔ Jugador | `plantillas (campeonato_equipo_id, jugador_id)` | El par no es UNIQUE. Cada fila conserva dorsal y fechas de vigencia. |
| Partido ↔ Inscripción de equipo | `equipo_partido (partido_id, campeonato_equipo_id)` | UNIQUE del par y de (partido_id, rol). El modelo de dominio distingue local y visitante. |
| Fase ↔ Inscripción de equipo | `fase_equipos (fase_id, campeonato_equipo_id)` | UNIQUE del par. La pertenencia a un grupo es opcional. |
| Campeonato ↔ Usuario en rol de árbitro | `asignaciones_arbitro (campeonato_id, usuario_id)` | N:M histórico. La columna generada restringe a una fila sin fecha de fin por campeonato. `autorizado_por` es otra FK a `users`. |
| Llave ↔ Participante de fase | `llave_plazas (llave_id, fase_equipo_id)` | Cada plaza exige llave y admite cero o un participante. UNIQUE de (llave_id, posicion), sin UNIQUE del par llave/participante. `origen_llave_id` y `origen_grupo_id` indican procedencias separadas. |

### Referencias sin FK física declarada

| Referencia | Cardinalidad o participación | Alcance |
| --- | --- | --- |
| `sessions.user_id` → `users.id` | 1:N lógica, con usuario opcional por sesión. | La migración usa `foreignId()->nullable()->index()` sin `constrained()`. Sin relación dedicada en `User`. |
| `password_reset_tokens.email` ↔ `users.email` | Correlación por correo, como máximo 1:1 por coincidencia de valores. | El correo es PK en tokens y UNIQUE en usuarios. El esquema permite un correo de token sin usuario correspondiente. |
| `personal_access_tokens.tokenable_type` + `tokenable_id` | 1:N polimórfica por tipo. Cada token declara un par no nulo. | `morphs('tokenable')` crea columnas e índice compuesto. El esquema carece de FK y de una tabla destino fija. `App\\Models\\User` carece de `HasApiTokens`. |
| `auditoria_cambios.entidad_tipo` + `entidad_id` | Referencia lógica de auditoría con destino variable. | Ambas columnas son obligatorias. `AuditoriaCambio` solo expone las relaciones de usuario y campeonato. No hay `morphTo` para la entidad auditada. |
| `grupo_desempates.inscripciones` | Colección JSON, sin cardinalidad relacional SQL. | Su presencia es obligatoria y el modelo la convierte a arreglo. Sus elementos carecen de FK individual. |

La tabla de FK cubre los **68 métodos de relación declarados en los 20 modelos** de `app/Models`. Son **40 FK** con al menos un método Eloquent asociado y **4 FK** declaradas solo en migraciones: `fases.fase_origen_id` y las tres referencias del resultado administrativo de `partidos`. No se encontró un `belongsToMany` directo, un `MorphTo` de aplicación ni una relación ordinaria Eloquent sin FK declarada entre los métodos inventariados.

## 4. Restricciones e índices importantes

- **Configuración del campeonato.** `UNIQUE (campeonato_id)` fija un máximo de una configuración. La FK es obligatoria en la configuración y elimina en cascada al borrar su campeonato.
- **Árbitro activo.** `campeonato_activo_id` es una columna generada STORED que toma el ID de campeonato si `fecha_fin IS NULL`. `asignaciones_arbitro_activa_unique` permite una sola fila de ese tipo por campeonato. El índice `(campeonato_id, fecha_fin)` apoya la consulta de vigencia. El método `User::esArbitroDe` añade el filtro de inicio, exige propietario y excluye que el mismo propietario actúe como árbitro.
- **Dorsal vigente.** `dorsal_vigente` es una columna generada VIRTUAL. `plantillas_dorsal_vigente_unique` cubre `(campeonato_equipo_id, dorsal_vigente)` y permite varias filas históricas con el valor generado NULL. La unicidad opera por ausencia de fecha de salida. `fecha_ingreso` y `fecha_salida` siguen siendo DATE. DP-07 queda fuera del esquema verificado.
- **Calendario y llaves.** `equipo_partido` impide repetir rol e inscripción dentro del mismo partido. `partidos (llave_id, numero_serie)` es UNIQUE con ambas columnas anulables. `llave_plazas (llave_id, posicion)` y el CHECK del driver `mysql` permiten como máximo dos plazas con posición 1 o 2. La existencia de dos plazas o dos participantes válidos depende del flujo deportivo.
- **Clasificación y procedencia.** `fases (campeonato_id, orden)`, `grupos (fase_id, codigo)`, `fase_equipos (fase_id, campeonato_equipo_id)`, `rondas (fase_id, numero)`, `llaves (ronda_id, numero)` y `grupo_desempates (grupo_id, clave)` son UNIQUE. Las FK anulables y únicas `fases.fase_origen_id` y `grupo_desempates.llave_id` modelan vínculos opcionales 1:1.
- **Actas y decisiones administrativas.** `partidos` guarda el tipo y la fecha de oficialización. Las referencias a inscripción beneficiada, inscripción sancionada y usuario decisor son anulables para admitir resultados deportivos. `Partido::esOficial` evalúa también el estado y, para administrativos, exige dos inscripciones distintas, autor y motivo. No hay tabla `actas` ni tabla separada de resoluciones administrativas.
- **Auditoría.** El índice `(campeonato_id, entidad_tipo, entidad_id)` sirve para localizar el historial. Las FK a campeonato y usuario usan RESTRICT. Los estados anterior y posterior se guardan en JSON. El modelo `AuditoriaCambio` protege contra actualización o eliminación mediante eventos Eloquent. Eso no equivale a un trigger o privilegio SQL.
- **Integridad entre campeonatos.** Hay FK individuales para partido, fase, grupo, llave, inscripciones y ganador. No hay FK compuestas que comprueben que todos esos IDs pertenecen al mismo campeonato. Las restricciones de coherencia entre relaciones deben verificarse también en el backend.
- **Índices implícitos.** InnoDB requiere índices de soporte para las FK y puede reutilizar una PK, UNIQUE o índice con prefijo compatible. El diccionario enumera los índices declarados, sin atribuir nombres a índices creados automáticamente por MariaDB.

## 5. Contraste y verificación pendiente en MariaDB

1. **No se pudo detectar divergencia real en la principal.** La consulta de identidad falló antes de consultar metadatos. No se confirmó la lista de migraciones aplicadas, lotes, columnas físicas, nulabilidad efectiva de columnas generadas, índices auxiliares, CHECK, motor o colación.
2. **Una relación PHP o una columna con sufijo `_id` puede carecer de FK.** Esto afecta las referencias lógicas de sesiones, tokens y auditoría. También hay cuatro FK declaradas sin método Eloquent dedicado, detalladas arriba.
3. **Las reglas de una aplicación pueden ser más estrictas que una FK.** El máximo de una configuración está en SQL. La elegibilidad deportiva, el instante de salida (DP-07), el tratamiento de plaza vacante (DP-03) y el campeón ante empate absoluto de liga requieren reglas del dominio.
4. **Formato del campeonato.** La migración define `campeonatos.formato` como `VARCHAR(30)` sin `CHECK` declarado. `Campeonato::setFormatoAttribute` acepta únicamente `todos_contra_todos`, `eliminacion_directa` o `grupos_eliminacion` en Eloquent.
5. **Las posiciones, estadísticas y actas no agregan tablas al MER.** Las migraciones contienen eventos, oficialización, fases, inscripciones, desempates y auditoría. No declaran entidades físicas adicionales para esos resultados derivados.

Cuando el servidor esté disponible, la verificación de solo lectura debe comenzar por `SELECT DATABASE(), @@hostname, @@port, VERSION()`. Con la identidad confirmada, se deben contrastar `INFORMATION_SCHEMA.TABLES`, `COLUMNS`, `TABLE_CONSTRAINTS`, `KEY_COLUMN_USAGE`, `REFERENTIAL_CONSTRAINTS`, `CHECK_CONSTRAINTS` y `STATISTICS` para `TABLE_SCHEMA = DATABASE()`. Los nombres y lotes aplicados se cotejan mediante `SELECT migration, batch FROM migrations ORDER BY id`. Esas consultas quedaron pendientes.

## 6. Agrupación visual recomendada

- **Centro:** `campeonatos` como entidad principal y `configuracion_campeonato` a su lado con la relación 1:1 opcional del lado del campeonato.
- **Identidad y permisos:** `users` con los roles de propietario, árbitro, autorizador y decisor nombrados por separado en sus relaciones. Mantener sesiones y tokens en el área técnica de autenticación.
- **Participación:** `equipos`, `campeonato_equipos`, `jugadores` y `plantillas`. Ubicar `equipo_partido` como puente hacia el área de competición.
- **Competición y cuadros:** `partidos` y `eventos_estadisticos` junto a la cadena `fases → rondas → llaves → llave_plazas`. Colocar `grupos`, `fase_equipos` y `grupo_desempates` junto a `fases`. Etiquetar autorreferencias y FK de procedencia.
- **Gobernanza:** `autorizaciones_correccion` y `auditoria_cambios` cerca de `partidos` y `users`. Mostrar la decisión administrativa como atributos de partido e inscripción.
- **Infraestructura:** aislar caché, colas y migraciones en un panel técnico. Diferenciar mediante una leyenda las FK previstas, las referencias lógicas y los resultados derivados. Conservar el estado de verificación pendiente hasta cotejar el esquema en vivo.
