# Cierre de 6.2 y preparación de 000300

16 de noviembre de 2026. Rama `develop`. Este informe complementa, sin reescribir, la evidencia histórica de `INTEGRIDAD_6_2.md`.

## Protección y Git

Se compararon exactamente las 38 rutas pendientes con el inventario y cada copia SHA-256 del respaldo externo de 6.2. Coincidieron rutas, HEAD y contenidos; índice inicialmente vacío, sin duplicados, archivos SQL, bases locales, sesiones, cookies ni credenciales. Se revisaron las diferencias y patrones sensibles. Se utilizó staging explícito desde el inventario y `git diff --cached --check`.

Único commit creado: `f9c2fd451ffd955a7aaaf721e8a1f4e18176a176` — `feat: proteger plantillas y ciclo de campeonato`, 38 archivos. Conserva como padres `5580f9e`, `d82800f` y `10c4990`. No se hizo push, amend ni cambio de `main`.

El ajuste de interfaz descubierto después del commit, su regresión y las herramientas/documentación de este cierre quedan **sin commit** para revisión posterior.

## Respaldo y ensayo sobre copia de la principal

Se exportó SQL privado nuevo, fuera del repositorio, mediante `mysqldump --single-transaction --quick --skip-lock-tables`, comprobando que todas las tablas fueran InnoDB y que no hubiera triggers, rutinas o eventos adicionales sin cubrir. Contraseña del cliente en archivo temporal privado, sin argumento de contraseña ni salida pública. El directorio tiene ACL limitada al usuario local y SYSTEM. Los punteros ignorados permiten localizarlo; no se publica su ruta absoluta.

| Elemento | Verificación |
| --- | --- |
| Origen | `127.0.0.1:3306`, MariaDB 10.4.32, `beesocc_v2`; solo lectura/exportación |
| Referencia | 28 tablas, 54 filas incluidas sesiones, 19 migraciones aplicadas |
| Pendientes en principal | Tokens Sanctum y únicamente `2026_11_15_000300_add_ciclo_y_minimos_campeonato` |
| SQL | 39.254 bytes; SHA-256 `12d88eeb05269024c5b64a4254f26b59283ee20db97095684872dbe4fe3ccff7` |
| Migración | SHA-256 `0b15dc76701a53c53a0e6890e2b79d91fe49115cf794dd337a854b2603a71f69` |
| Copia | Instancia nueva independiente en 3317; cuenta limitada, cuatro rutas físicas comprobadas, acceso a principal rechazado |
| Restauración | Huellas, filas y estructuras iguales a la referencia; las sesiones del dump también coincidieron en este ensayo |
| Aplicación | Exclusivamente 000300 mediante `--path`; 19 → 20 entradas en la copia, conservando las 19 anteriores |

Se conservaron los tres campeonatos y las tres plantillas históricas, y las huellas de todos los campos anteriores de las demás tablas. Se compararon las columnas, índices y FK previos; no desapareció ni cambió ninguno. No se rellenaron automáticamente los nuevos campos históricos.

Se verificaron las cinco columnas nuevas, su tipo y nulabilidad: `campeonatos.iniciado_at/finalizado_at` TIMESTAMP; `configuracion_campeonato.modalidad` VARCHAR(50) y `minimo_jugadores` TINYINT UNSIGNED; `plantillas.dorsal_vigente` TINYINT UNSIGNED virtual. El índice único contiene, en ese orden, inscripción y dorsal vigente. No hay nuevas FK previstas en 000300.

Una sonda DML ficticia exclusivamente en la copia comprobó rechazo 1062 del dorsal activo duplicado y reutilización después de cerrar la inscripción. Toda la sonda se revirtió y se comprobaron de nuevo los datos originales. Ese rollback pertenece a datos de prueba; no se hizo rollback de la migración. La sonda puede consumir números AUTO_INCREMENT de la copia, sin cambiar filas históricas ni afectar la principal.

La primera sonda tenía un nombre de tabla incorrecto (`campeonato_equipo`, en lugar de `campeonato_equipos`). Se detuvo y conservó el diagnóstico privado; 000300 ya había pasado las verificaciones estructurales e históricas. Se corrigió la herramienta y se reanudó exclusivamente la sonda y su validación, sin restaurar otra vez ni volver a migrar. El ejecutor reproducible de copia conserva la corrección.

## Integración real en otro laboratorio ficticio

Se creó otra instancia física independiente, sin restaurar datos reales. Laravel sirvió en 8013, React/Vite en 5175 con proxy a ese Laravel. `entorno.php` verificó base, cuenta y rutas físicas y eliminó conexiones alternativas. Se usaron sesiones, cookies y CSRF Sanctum reales; no se instalaron adaptadores ni dependencias.

| Ejecutado desde navegador | Preparado o comprobado mediante HTTP real |
| --- | --- |
| Login de administrador ficticio | Usuarios, catálogo de equipos y jugadores ficticios del seeder |
| Crear liga todos contra todos | Crear campeonatos breves de eliminación directa y grupos; inscribir equipos y plantillas de los tres |
| Guardar modalidad y mínimo de la liga | Requisitos de los otros dos formatos |
| Inicio rechazado sin mínimo; inicio rechazado sin árbitro en los tres formatos | Completar asignación arbitral, calendario de liga, cuadro directo y distribución/calendario de grupos |
| Inicio correcto y cierre de plantillas en los tres formatos | Verificar altas, dorsales y retiros bloqueados después del inicio en los tres |
| Cambio de dorsal de la liga rechazado con mensaje visible | Iniciar partidos, registrar goles y oficializar las cinco actas necesarias |
| Finalización prematura de liga rechazada | Clasificación oficial de grupos, generación de su final y avance de finales oficiales |
| Finalizar correctamente los tres campeonatos y observar estado finalizado, sin botones de transición | Verificar no reinicio, doble cierre, edición de requisitos/dorsales ni eventos posteriores; auditoría sin cambios parciales |

Resultado: **108 comprobaciones HTTP nuevas aprobadas**, además de las observaciones del navegador descritas. No son las suites 5.4/5.7 repetidas. Las actas y las estructuras secundarias se prepararon por API, no se presentan como acciones manuales de navegador. La captura final contiene solo datos ficticios y se conserva fuera de Git.

### Error de interfaz encontrado y corregido

Al recibir 409, `CicloCampeonatoPanel` recargaba la liga con `liga=null` y estado de carga global. Esto desmontaba el panel y cerraba la confirmación antes de mostrar el motivo de Laravel. El backend rechazaba correctamente la operación.

`useLigaStore.cargarLiga` admite ahora `{ conservar: true }`: conserva la vista mientras refresca la liga y mantiene la protección contra respuestas tardías. El ciclo usa esa opción al recuperar un 409. El mensaje permanece si el estado no cambió; si otra operación sí cambió el estado, se refleja el estado autoritativo del servidor. Una recarga fallida no sustituye el error original del modal.

Se agregó una regresión específica que comprueba conservación de la liga durante la petición y rechazo de una respuesta antigua después de cambiar de campeonato. Se verificó desde React el mensaje de mínimo, árbitro y cierre prematuro tras la corrección.

Calidad: **205 Vitest + 47 Node = 252 pruebas frontend aprobadas**, lint y build aprobados; Pint y revisión de diferencias aprobados. Backend productivo y migraciones no cambiaron durante este cierre: se conservan como antecedente las **201 pruebas/4.811 aserciones** ya aprobadas, sin repetir la suite completa. Las ocho carreras MariaDB anteriores **no se repitieron**.

## DP-07 y sesiones

DP-07 conserva exactamente la elegibilidad `DATE` inclusiva. No se cambió a DATETIME, no se recalcularon resultados ni vigencias históricas. La propuesta futura `[ingreso, salida)` con instantes y zona `America/Bogota` continúa pendiente de decisión escrita, inventario y ensayo específico; no queda aprobada por este cierre.

La comparación de solo lectura contra la referencia anterior de 6.2 confirma igualdad de estructuras y datos fuera de `sessions`. Las sesiones pasaron de **2 a 3**; el agregado actual es una anónima y dos autenticadas, sin referencias a usuarios inexistentes. No se consultaron ni divulgaron IP o payloads para el diagnóstico, no se eliminaron sesiones ni se atribuyó un origen. Una tabla de sesiones puede variar por su ciclo normal; esta observación por sí sola no identifica la causa concreta. La referencia del nuevo SQL se guarda separada de la antigua, sin reemplazarla.

Se revalidó el respaldo después del ensayo: conserva SHA, historial, estructuras y datos deportivos de la principal. 000300 sigue pendiente y no existen sus cinco columnas allí. Ambos laboratorios, sus configuraciones privadas y datos se conservaron; se detuvieron únicamente los servidores propios de prueba.

## Procedimiento propuesto para principal — NO EJECUTADO

La aplicación requiere **autorización explícita posterior a este ensayo**. MariaDB no ofrece rollback transaccional de estos DDL: ante cualquier fallo se debe detener, conservar evidencia y no eliminar columnas, tablas o históricos automáticamente.

1. Confirmar `develop`, historial local, SHA de 000300 y ausencia de cambios en migraciones. Coordinar una ventana sin escrituras deportivas para evitar variaciones entre preflight y DDL.
2. En un proceso nuevo sin configuración del laboratorio, comprobar nuevamente servidor 127.0.0.1:3306, versión 10.4.32, base, 28 tablas, 19 entradas previas, tokens y 000300 pendientes. Detener ante cualquier columna parcial o dorsal activo duplicado.
3. Localizar el respaldo mediante el puntero privado `backend/tests/Integral/privadas-locales/sprint62-cierre-respaldo-ruta.txt`; verificar ACL, SHA y correspondencia actual. Ejecutar `sprint62-cierre-principal.php comparar` con `BEESOCC_BACKUP_DIR` apuntando al directorio externo. La herramienta solo consulta/exporta; no invoca Artisan. Las variaciones de sesiones se separan, pero su estructura debe permanecer igual. Si cambian datos deportivos o cualquier otra estructura, detener y preparar un respaldo/ensayo actualizado.
4. Solo después de autorización y preflight correcto, desde `backend`, ejecutar exactamente:

```powershell
& C:\xampp\php\php.exe artisan migrate --database=mysql --path=database/migrations/2026_11_15_000300_add_ciclo_y_minimos_campeonato.php --force
```

5. Verificar cinco columnas y nulabilidad, columna virtual e índice único, ausencia de rellenos históricos, mismos campos/filas anteriores y 28 tablas. Comprobar las tres plantillas y los tres campeonatos contra las huellas privadas. Solo debe añadirse la entrada de 000300: 19 → 20. Tokens debe continuar pendiente.
6. Verificar las restricciones mediante inspección estructural y el ensayo ya aprobado; no insertar datos ficticios ni sondas en la principal. Comparar sesiones por separado. Ante cualquier diferencia imprevista, detener sin rollback o reparaciones destructivas automáticas.

Para reproducir el ensayo nuevo, usar la inicialización aislada existente y `preparar.php`; definir `BEESOCC_INTEGRAL_CONFIG` con la cuenta limitada y `BEESOCC_COPIA_MANIFIESTO` con el manifiesto SQL privado. `sprint62-cierre-copia.php` exige base vacía, comprueba permisos/rutas y SHA, restaura, migra únicamente 000300 y verifica históricos. No se debe volver a ejecutar sobre la copia poblada.

Para la integración ficticia, `sprint62-cierre-http.mjs` separa `preparar`, `completar`, `resultados` y `bloqueos`; las transiciones del campeonato se ejecutan entre esos pasos desde React, tal como se documentó. Cada modo conserva evidencia privada y evita repetir un modo ya aprobado.

Evidencia pública sanitizada: `backend/tests/Evidencias/sprint62-cierre-evidencia.json`. Respaldo y diagnósticos completos permanecen privados. No hay autorización para migrar la principal, hacer push, crear otro commit o iniciar 6.3.
