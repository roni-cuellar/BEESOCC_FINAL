# Sprint 4.2 — Validación real en MariaDB

Rama `develop`. Reglas 4.1 confirmadas expresamente por el usuario: el nombre
`REGLAS_PROPUESTAS.md` se conserva, pero su contenido ya indica «Reglas aprobadas».
El MER detallado usa `ganador_fase_equipo_id`; el Manual contiene nombres orientativos distintos.
La siembra de descansos y los totales de tanda completan decisiones abiertas con aprobación posterior.
Brechas aún diferidas frente al MER: retiro/descalificación con avance administrativo y 3–0,
estado específico de doble descalificación y acta detallada/corrección de tanda.
No se implementaron esos flujos, grupos ni una nueva interpretación de DP-07.

## Aislamiento, respaldo y migración

MariaDB **10.4.32**, puerto 3317, base nueva `BASE_LABORATORIO`;
cuenta limitada a esa base, sin lectura de `beesocc_v2`. Directorio físico nuevo:
`[DIRECTORIO_LOCAL]`.
Se verificaron base/cuenta, SHOW GRANTS y las cuatro rutas de InnoDB/Aria antes de restaurar;
el servidor HTTP repitió las comprobaciones de aislamiento en cada petición.
Respaldo fuera del repositorio: `[DIRECTORIO_LOCAL]`.
Restauración exacta de **21 tablas y 49 registros**, comprobando estructuras y huellas.
Solo se aplicó `2026_11_15_000100_create_estructura_eliminacion.php` en la copia;
las 49 referencias conservaron sus valores previos. No se ejecutó la migración pendiente de tokens.
SHA256 de la migración: `1be7544f67bdb47f4ba22511b0085d138f404e35c83799928d20ff9baf20ae96`.

## Resultados comprobados

- **116 verificaciones** de columnas, tipos, nulabilidad, longitudes, FK, índices, CHECK y unicidad reales.
- **108 comprobaciones HTTP reales**, con cookies y CSRF de Sanctum, sin adaptadores simulados.
- Torneo de seis equipos completo: dos descansos, siete partidos reales, ida/vuelta sin gol visitante,
  resolución por penales/prórroga certificada y final. Siete goles individuales; la tanda no agrega eventos.
- Liga ficticia de tres equipos: calendario, partido oficial, ganador con tres puntos y un gol;
  estadísticas correctas, sin fases añadidas. La tabla de soporte `grupos` permanece vacía.
- **Tres carreras reales**: avance duplicado, consumo duplicado que cambia el clasificado e inicio
  de final frente a corrección anterior. Dos procesos PHP independientes en LOCK WAIT por caso;
  resultados **200/409**, sin duplicados, con ID de final conservado y conflicto sin cambios parciales.
  Estas carreras invocan servicios/controlador Laravel con conexiones InnoDB reales; no son navegador.
- Regresión final **SQLite en memoria: 141 pruebas y 2.824 aserciones**; frontend **152 pruebas**.
  Lint, build, Pint y `git diff --check` aprobados. No se realizaron pruebas nuevas de navegador.

## Errores del laboratorio y correcciones

La comparación estricta de arrays con objetos Carbon detectaba falsamente diferencias de fecha;
se compara su valor serializado. La fixture nueva usaba `autorizado_por_id`, corregido al campo real
`autorizado_por`. Los verificadores de carrera ahora devuelven error explícito para detener la cadena.
Solo se repitieron las carreras necesarias con otro escenario ficticio; no el torneo HTTP completo.
Se añadió `CACHE_PREFIX` propio a `preparar.php`, con prueba de regresión sobre la caché copiada.
Después del tráfico se verifican las **46 referencias persistentes** originales de la copia;
las dos filas de caché y una sesión copiadas son transitorias y se distinguen explícitamente.
No se restauraron ni maquillaron esas filas para hacer pasar la comparación.
La principal completa sí conserva **las 21 tablas, 49 registros, estructuras y huellas de configuración**.
No fue necesario modificar código productivo ni la migración 4.1.

Nuevos archivos: herramientas/evidencias `tests/Integral/sprint42-*` y esta nota;
único ajuste previo reutilizable: prefijo de caché en `tests/Integral/preparar.php`.
Instancia y servidor aislados detenidos, copia y evidencias conservadas. Sin eliminar bases anteriores.
Pendiente: preparar aplicación principal con respaldo y autorización explícita; después conectar React.
Sin commits ni push; se conserva el trabajo previo sin confirmar de Sprint 4.1.
