# Sprint 6.4 — Experiencia pública de campeonatos

Fecha: 16 de noviembre de 2026. Rama `develop`. Cierre 6.3 protegido en el commit local `b916aeb3555b22e50810c6221bf881be2692115f`, con las seis rutas exactas verificadas contra su respaldo SHA-256. Sin push. El trabajo nuevo de 6.4 queda sin commit.

## Alcance implementado

- **Todos contra todos:** equipos, calendario, resultados, tabla y estadísticas permitidas. La tabla avisa que no determina por sí sola al campeón.
- **Eliminación directa:** cuadro público con rondas, enfrentamientos, procedencias, descansos, resultados oficiales, tandas y clasificados resueltos por Laravel. Final pendiente visible sin inventar ganador.
- **Grupos + eliminación:** grupos, equipos, calendario por grupo, posiciones oficiales y provisionales diferenciadas, empates pendientes, clasificados oficiales confirmados, partidos adicionales y cuadro posterior. Los partidos extra y sus tandas reutilizan el cuadro existente; las liguillas adicionales aparecen en el calendario del grupo y no se suman a la tabla regular.
- **Campeón:** Laravel devuelve únicamente el equipo de la resolución auditada de finalización del campeonato. Si falta esa resolución, devuelve `null`, incluso en históricos marcados como finalizados. React no deduce campeones de nombres, identificadores, posiciones ni marcadores.
- **Administrativos:** marcador oficial efectivo y deportivo conservado por separado; no se muestran motivos internos ni se generan eventos o goles individuales.
- **Interfaz:** navegación según formato, carga, vacío, error y reintento; revisión de publicación al navegar, actualizar o recuperar el foco. Rutas públicas antiguas de tabla redirigen a grupos/cuadro cuando corresponda. Colores, imágenes y logotipo oficiales conservados; sin rediseño general.

## Contratos y seguridad

Nuevo endpoint de solo lectura:

```text
GET /api/publico/campeonatos/{id}/deporte
```

Devuelve `fases` y `campeon`. Cada fase contiene únicamente campos deportivos permitidos. Grupos incluye tablas y clasificación; eliminación incluye rondas, plazas y resultados. Las rondas de desempate incluyen tandas y su resolución deportiva, sin expedientes administrativos. Los identificadores deportivos necesarios para relacionar grupos, puestos y llaves no incluyen propietarios ni usuarios.

Se reutilizan los GET públicos existentes de catálogo, detalle, equipos, plantillas, partidos, eventos, tabla y estadísticas. Partidos añade grupo, tipo de partido, oficialización, tipo de resultado y marcador deportivo. No se retiró autenticación de ninguna ruta privada ni se añadieron operaciones públicas de escritura.

`DeportePublicoService` construye listas explícitas de campos. Las lecturas públicas de tablas y clasificación comparten el cálculo deportivo existente, con una entrada separada que exige campeonato publicado y propietario existente. Las entradas privadas mantienen sus Policies/Gates. Los cálculos consultan datos; no clasifican, generan cuadros ni escriben auditoría por una visita pública.

Todos los endpoints vuelven a comprobar publicación y devuelven 404 tras ocultamiento. Respuestas exitosas con `Cache-Control: no-store`. Equipos, jugadores, fases y partidos se validan dentro de su campeonato y propietario; una relación incoherente no permite filtrar nombres ajenos. No se serializan modelos completos, correos, nacimientos, propietarios, motivos disciplinarios, autorizaciones, huellas, firmas ni auditorías. React usa exclusivamente GET públicos sin solicitar cookies ni CSRF.

## Componentes y archivos

- Backend: `PublicoCampeonatoController`, `DeportePublicoService`, entradas de consulta en `TablaGruposService` y `ClasificacionGruposService`, y ruta pública.
- React: `DeportePublicoPage`, `DetallePublicoPage`, `publicoService` y rutas. Se reutilizan `CuadroEliminacion`, `TablasGrupos`, `PartidoCard`, `ResultadoOficial`, `TablaPage` y las estadísticas existentes. El modo público del cuadro no muestra enlaces privados, botones de avance ni certificación de penales.
- Regresiones: `PublicoFormatosTest.php`, `publico-formatos.test.jsx` y ajuste de la expectativa anterior sobre oficialización pública en `resultados-oficiales.test.jsx`.
- Reproducción HTTP: `backend/tests/Integral/sprint64-publico.mjs`. Evidencia sanitizada: `backend/tests/Evidencias/sprint64-publico-evidencia.json`.

## Verificación

| Nivel | Resultado y límites |
| --- | --- |
| Backend SQLite / kernel Laravel | **219 pruebas aprobadas, 7.123 aserciones.** Ocho pruebas nuevas de contratos públicos: publicación/ocultamiento, tres formatos, privados ausentes, incompletos, administrativos, clasificación confirmada, campeón, aislamiento de fases y partidos extra con tandas. No representa HTTP de red. |
| Frontend | **265 pruebas aprobadas: 218 Vitest y 47 Node.** Incluye cuadros públicos sin acciones, posiciones oficiales/provisionales, candidatos no confirmados ocultos, administrativos, vacíos, tandas y contrato GET sin cookies. Los adaptadores usados en estas pruebas unitarias no se usaron en el recorrido real. |
| HTTP real | **39 comprobaciones aprobadas:** 26 de preparación/consulta, 4 de cuadro derivado y 9 de ocultamiento. Visitante sin sesión, GET de tres formatos, listas de campos, 401 privado, 404 entre campeonatos, 405 de escritura y revocación pública. |
| Navegador real | Sin iniciar sesión: liga con calendario, tabla y único gol deportivo real; directa con semifinales resueltas y final pendiente; grupos oficiales/provisionales, clasificación y calendario; cuadro derivado con procedencias de grupo. Ocultamiento por API y actualización de React retirando la información. |
| Responsive | Navegador a 390 × 844: ancho útil y contenido de 375 px, sin desbordamiento global en grupos ni cuadro. Tablas y rondas conservan su desplazamiento interior y controles accesibles. Se restableció el viewport después. |
| Calidad | Pint sobre PHP versionado y nuevos revisados, lint sin avisos, sintaxis Node, build y diferencias. Build exitoso con advertencia de paquete JS de aproximadamente 504 kB sin comprimir; no se alteró el umbral para ocultarla. |
| Concurrencia | No se repitieron carreras anteriores: las nuevas operaciones públicas son de lectura. |

El campeón y las tandas adicionales se verificaron con fixtures automatizados del kernel/React; no se presentan como un nuevo campeonato completado ni una tanda certificada desde navegador. El recorrido real reutilizó los campeonatos ficticios conservados de 6.3; no repitió sus resoluciones ni sus 98 comprobaciones.

## Laboratorio y reproducción focalizada

Se reanudó la instancia ficticia conservada, sin restaurar, borrar datos ni ejecutar migraciones. Se comprobó MariaDB 10.4.32 en `127.0.0.1:3317`, cuenta limitada, cuatro rutas físicas aisladas y API apuntando a esa misma base. Principal separada en 3306. Laravel real en 8013 y Vite en 5175. El servidor Vite dentro del sandbox escuchaba sin responder; se reinició solo ese servicio fuera del sandbox, conservando datos y configuración.

1. Reanudar únicamente el motor ficticio propio y verificar sus rutas/puerto/cuenta mediante los procedimientos de aislamiento existentes. Usar `entorno.php` y `sprint57-servidor.php`, nunca el `.env` principal para servir el laboratorio.
2. Definir `BEESOCC_INTEGRAL_DIR` con el directorio privado conservado, que contiene su configuración y `cierre63-casos.json`. El ejecutor exige base/cuenta de pruebas y puerto 3317; las solicitudes se dirigen exclusivamente a Laravel local en 8013.
3. Ejecutar `node backend/tests/Integral/sprint64-publico.mjs preparar`: autentica únicamente al organizador ficticio para publicar los casos y consulta como espectador sin cookies. No imprime claves ni sesiones.
4. Ejecutar el modo `cuadro` una sola vez para preparar por API el cuadro derivado ficticio. Visitar `/campeonatos/{id}/grupos`, `/cuadro`, `/tabla` y `/estadisticas` sin login. Alternar vistas y revisar escritorio/móvil.
5. Ejecutar el modo `ocultar` y actualizar React: se verifica 404 y exclusión del catálogo. Cada modo guarda evidencia privada y rechaza repetir un modo aprobado. No se reutiliza este procedimiento para modificar la principal.
6. Conservar configuraciones, logs, cookies y fixtures fuera de Git. Las capturas contienen solo datos ficticios y permanecen fuera del repositorio. Detener únicamente los procesos propios, conservando sus directorios.

## Integridad y pendientes

La comparación final exclusivamente de lectura contra el respaldo externo aprobó: **28 tablas, 55 registros de referencia, 20 migraciones y huellas anteriores conservadas**, incluidas sesiones. 000400 permanece pendiente junto con tokens Sanctum; no se ejecutó ninguna migración, ni siquiera en el laboratorio reanudado. No se modificaron históricos, propietarios ni sesiones de la principal.

El desempate absoluto de liga por puntos, diferencia y goles sigue bloqueando la finalización. Ordenar visualmente por nombre/ID no atribuye un título. Falta una decisión deportiva aprobada para resolver ese empate; este sprint no amplía el motor. DP-03 conserva su bloqueo extraordinario y DP-07 su elegibilidad DATE inclusiva. El rediseño general permanece reservado para 6.6. La advertencia de tamaño JS queda documentada para optimización posterior, sin refactorización general en este sprint.

Sin dependencias nuevas, migraciones nuevas, cambios en `main` ni push. Solo se creó el commit autorizado del cierre 6.3. Las rutas de 6.4 quedan sin staging ni commit, con respaldo externo e inventario SHA-256 revisados.
