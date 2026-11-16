# Sprint 5.6 — Interfaz privada de grupos y eliminación

- `GruposPage`, `crearGruposStore` y `grupoService`: configuración, calendario,
  posiciones oficiales/provisionales, expedientes y clasificación confirmada.
- `ConfiguracionGrupos`, `TablasGrupos`, `DesempatesGrupos` y `CrucesGrupos`
  reutilizan botones, confirmaciones, actas, auditoría, `PenalesForm` y
  `CuadroEliminacion`. Se conserva el branding y el logotipo original.
- Rutas privadas `/ligas/:ligaId/grupos` y `/mis-arbitrajes/:ligaId/grupos`;
  administración confirma y avanza; árbitro consulta y certifica penales.
- Los contratos de grupos usan `/fases/{id}/previsualizar-grupos`,
  `confirmar-grupos`, `generar-calendario-grupos`, `clasificacion-grupos`,
  `clasificar-grupos`, `previsualizar-eliminacion` y `generar-eliminacion`;
  `/grupos/{id}/tabla`, `desempates`, `previsualizar-desempate` y
  `generar-desempate`. Se reutilizan fases, actas y penales/avance de llaves.
- Las firmas se reenvían sin reinterpretarlas; editar, recargar, cambiar de
  contexto o recibir un conflicto invalida la confirmación. El store bloquea
  doble envío y descarta respuestas y errores antiguos. React no ordena
  posiciones deportivas ni resuelve ganadores, minitablas o clasificaciones.
- Se consultaron MER, Manual Técnico D-19 a D-24 y los informes 5.3/5.4.

Verificación: **188 pruebas backend SQLite y 4.489 aserciones**, **240 pruebas
frontend** (47 Node + 193 Vitest, 44 nuevas), lint y build aprobados.
Las regresiones previas de liga y eliminación directa se conservaron.

Recorrido visual con `tests/browser/grupos.html`: creación, asignación manual
y automática, invalidación de firmas, calendario, partido extra, penales con
perfil arbitral, clasificación, cruces manuales/automáticos, descanso, cuadro,
409 y revocación. Se revisaron escritorio y móvil de 390 × 844; tablas y rondas
desplazan contenido internamente, sin desbordamiento horizontal de la página.
El adaptador de prueba es cerrado y usa respuestas ficticias; ninguna llamada
del recorrido se envía a Laravel. **No equivale a integración real React/API.**

Se corrigieron los accesos al calendario genérico y la edición de jornada y
equipos para partidos de grupos; se añadió regresión para un error tardío que
podía borrar una consulta más reciente. No quedaron fallos de pruebas.

Los 33 archivos anteriores quedaron respaldados y preservados byte a byte.
Se creó un respaldo SQL externo restringido actualizado después de 5.5. La
comparación final de solo lectura confirmó 28 tablas y 51 registros totales
(incluida la entrada nueva de migración), sin cambios durante este sprint.
No se modificaron backend, migraciones, históricos o dependencias; no se hicieron
commits ni push. Queda pendiente el recorrido React con API y MariaDB reales en
un laboratorio aislado. El servidor visual propio se detuvo al finalizar.
