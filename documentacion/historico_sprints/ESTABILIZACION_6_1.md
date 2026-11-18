# Sprint 6.1 — Imágenes, dependencias y formato

Fecha: 15 de noviembre de 2026. Rama `develop`. Alcance: F01, actualización
compatible de los paquetes afectados de S01 y formato versionado de Q01.
No se implementaron otros pendientes deportivos ni se editaron documentos maestros.

## Commits autorizados y protección

Se verificó el respaldo externo de HEAD y las siete copias de 5.7, con inventario
SHA-256 coincidente y ACL limitada. También se respaldó y cotejó la auditoría
6.0 antes de incorporarla. Se revisaron rutas, espacios y patrones de secretos.
Cada selección se preparó contra su HEAD vigente, con índice inicialmente vacío
y `git diff --cached --check` aprobado, sin reutilizar índices temporales.

| Commit local | Selección exacta |
| --- | --- |
| `10c4990a9fec16722f9fc6158804831e304ba8f0` | Las siete rutas de 5.7 enumeradas en la sección 3 de `AUDITORIA_CIERRE_6_0.md` |
| `d82800f1949dd09c6c7fec8a8c00c94fa828bb80` | Únicamente `AUDITORIA_CIERRE_6_0.md` |

No se hizo push. Los cambios de 6.1 permanecen sin commit y sin preparar en el
índice. Los respaldos, inventarios privados y dependencias instaladas no se
incorporan a Git. Se conserva una copia externa de los avances de 6.1 y del
historial local; la ruta se mantiene fuera de la documentación publicable.

## F01 corregido

Fotos y escudos comparten un contrato sencillo de **archivo**:

- Alta: `foto` o `escudo` opcional, JPG/JPEG, PNG o WEBP, hasta 5120 KiB (5 MiB).
  La interfaz lo presenta como 5 MB. El servidor verifica contenido, formato y
  tamaño; el cliente aplica una comprobación temprana y explica el límite.
- Edición sin archivo nuevo: conserva la referencia, incluso si un cliente
  anterior envía `null`. Ya no se borra el escudo al editar solamente nombre o ciudad.
- Reemplazo: subir el archivo y guardar la URL segura devuelta por Cloudinary.
- Quitar referencia: marcar la acción visible y guardar; la API exige
  `eliminar_imagen=true` (o `1` en multipart). No basta enviar un campo vacío.
- Archivo nuevo y acción de quitar a la vez: 422 antes de subir o modificar datos.
- Las URLs existentes siguen siendo referencias consultables. Los formularios
  ya no solicitan una URL de foto; enviar una URL en lugar de un archivo al
  campo de subida se rechaza con 422, de forma uniforme en alta y edición.

React usa multipart para archivos y POST con `_method=PATCH` para el reemplazo,
compatible con el procesamiento de archivos de PHP/Laravel. Sin archivo usa
JSON, omitiendo el campo de imagen y la acción de borrado cuando no se solicita.
Reutiliza Axios, cookies, XSRF y componentes existentes. Durante el envío se
bloquean los campos y se evita una segunda ejecución del formulario.

`ImagenCatalogoService` concentra la subida Cloudinary y la conservación o
eliminación explícita de la referencia. Los controladores mantienen sus Policies
antes de subir y sus validaciones antes de escribir. No se agregaron rutas,
columnas, migraciones, dependencias o permisos para esta corrección.

Un error del proveedor devuelve 502 sin divulgar su respuesta ni credenciales;
no crea el registro ni cambia sus datos o referencia anterior. El formulario
permite reintentar y el cliente no reenvía automáticamente una subida fallida.
Las respuestas de validación de imágenes son claras y en español.

**No se elimina ningún archivo remoto**, ni al reemplazar ni al quitar referencia.
Cloudinary conserva los archivos previos. Si una subida termina y después falla
la escritura en base, podría quedar un archivo remoto sin referencia; su eventual
limpieza debe ser revisada, nunca una eliminación automática dentro de este flujo.

## S01 actualizado sin cambiar versiones mayores

Primero se ejecutó el ensayo de resolución con `--dry-run`. Solo después se
aplicó el mismo conjunto acotado, sin scripts Composer ni operaciones Artisan:

```powershell
composer update laravel/framework symfony/http-foundation symfony/http-kernel symfony/mime symfony/routing symfony/yaml --with-dependencies --minimal-changes --no-interaction --no-scripts
```

| Paquete | Antes | Después |
| --- | --- | --- |
| laravel/framework | 12.70.1 | 12.72.0 |
| symfony/http-foundation | 7.4.21 | 7.4.23 |
| symfony/http-kernel | 7.4.21 | 7.4.23 |
| symfony/mime | 7.4.19 | 7.4.22 |
| symfony/routing | 7.4.21 | 7.4.23 |
| symfony/yaml, desarrollo | 7.4.21 | 7.4.23 |

La comparación de ambos lockfiles confirmó exactamente seis paquetes
actualizados, sin altas, bajas ni cambios mayores. `composer.json` no cambió;
Sanctum permanece en 4.4.0 y Cloudinary conserva su versión previa. No se tocaron
PHP, XAMPP, MariaDB, los manifiestos/lockfiles frontend o la configuración privada.

Los nueve avisos inventariados en 6.0 quedaron cubiertos por estas versiones:
Concurrency de Laravel; los cuatro de HTTP Foundation; HTTP Kernel; MIME;
Routing; y YAML de desarrollo. Sus referencias oficiales permanecen en la
sección 7 de la auditoría. No se aplicó una actualización global.

Resultados posteriores:

```text
composer audit --locked --format=json: advisories=[], abandoned=[], salida 0
composer check-platform-reqs: aprobado, incluido PHP 8.2.12 y sus extensiones
npm audit --json: 0 vulnerabilidades, salida 0
```

**No quedan avisos pendientes de ese audit Composer.** Esto no certifica todo
el entorno: el mantenimiento de MariaDB 10.4 y la revisión antigua de PHP,
así como HTTPS/hosting, siguen fuera del alcance autorizado. No se actualizaron
por la restricción expresa de este sprint ni se afirma que los ensayos locales
habiliten una publicación general en Internet.

## Q01 y comprobaciones

Pint corrigió únicamente la indentación y espacios de
`backend/config/services.php`, conservando las referencias `env()` y sin
incorporar secretos. El servicio y el test nuevos también se revisaron.
La verificación general se hizo sobre las rutas PHP **versionadas**, más las
dos rutas PHP nuevas revisadas, para no incluir herramientas privadas ignoradas:

```powershell
# Desde backend. Comprobación de solo lectura, sin formato automático global.
$rutas = @(git ls-files '*.php')
php vendor/bin/pint --test $rutas app/Services/ImagenCatalogoService.php tests/Feature/ImagenCatalogoTest.php
```

Resultado: aprobado. No se cambió la configuración de Pint ni se añadieron a
Git los scripts privados de aplicación/mantenimiento. Un comando indiscriminado
sobre el directorio todavía puede encontrar el formato de esos archivos ignorados;
no forman parte de la entrega versionada ni de Q01.

| Verificación | Resultado y alcance |
| --- | --- |
| Backend completo | 192 pruebas y 4.555 aserciones aprobadas; SQLite en memoria |
| Regresión de imágenes | 4 pruebas nuevas, 66 aserciones; dos catálogos, alta/reemplazo, omisión/null, quitar explícito, inválidos/tamaño, contradicción, proveedor fallido y 403 ajeno |
| Frontend Node | 47 pruebas aprobadas |
| Frontend Vitest | 200 pruebas aprobadas, 7 nuevas; total frontend 247 |
| Lint y build frontend | Aprobados |
| Pint del código versionado y PHP nuevo | Aprobado |
| Diferencias y revisión de seguridad | Espacios y selección revisados; sin nuevos secretos o archivos privados |

El backend prueba peticiones del **kernel Laravel**, incluyendo multipart y
method override; no son pruebas HTTP de red. Solo se sustituyó la operación de
subida de Cloudinary, sin credenciales reales ni llamadas al proveedor. Las
Policies se ejecutan realmente; el mock comprueba que un usuario ajeno no llega
a subir. Un ajuste inicial del test consistió en limpiar los guards al cambiar
de usuario, igual que en las pruebas de propiedad existentes.

Frontend comprueba contratos Axios multipart/JSON, conservación, eliminación
explícita, ausencia de reintento automático, límites y presentación de los dos
formularios mediante renderizado a HTML. No es un nuevo recorrido DOM en
navegador. No se repitieron laboratorios MariaDB, las 380/194 comprobaciones
HTTP anteriores ni carreras InnoDB, porque no corresponden a estos cambios.

La suite completa posterior a la actualización aprobó autenticación, CSRF,
Policies, publicación, actas y los tres formatos. Tras el ajuste final de mensajes
se repitieron solamente las regresiones focales de imágenes. Tras añadir el caso
frontend de fallo de subida se revalidaron Vitest y lint; el código compilado
ya coincidía con el build aprobado.

## Archivos del Sprint 6.1

Ocho modificados:

- `backend/app/Http/Controllers/Api/EquipoController.php`
- `backend/app/Http/Controllers/Api/JugadorController.php`
- `backend/composer.lock`
- `backend/config/services.php`
- `frontend/src/components/EquipoForm.jsx`
- `frontend/src/components/JugadorForm.jsx`
- `frontend/src/services/equipoService.js`
- `frontend/src/services/jugadorService.js`

Seis nuevos, incluido este informe:

- `backend/app/Services/ImagenCatalogoService.php`
- `backend/tests/Feature/ImagenCatalogoTest.php`
- `frontend/src/components/ImagenCatalogoForm.jsx`
- `frontend/src/services/imagenCatalogo.js`
- `frontend/tests/unit/imagenes.test.jsx`
- `ESTABILIZACION_6_1.md`

## Integridad, límites y estado final

No se accedió a `beesocc_v2` durante 6.1 ni se ejecutaron migraciones en ella.
Las pruebas escribieron únicamente en SQLite en memoria y archivos de subida
ficticios. Las actualizaciones Composer usaron `--no-scripts`. No se hizo una
nueva comparación de la principal y no se atribuyen a este sprint instantáneas
de sprints anteriores.

No hay bloqueos funcionales observados para F01/Q01 ni avisos Composer de los
nueve inventariados. La subida con una cuenta Cloudinary real no se ejecutó;
queda como verificación operativa privada, sin incluir sus claves en Git y sin
eliminar imágenes remotas. No se tocaron los pendientes de 6.2 y posteriores.

`develop` queda con los dos commits autorizados, adelantado dos commits respecto
a la referencia local `origin/develop`. HEAD: `d82800f1949dd09c6c7fec8a8c00c94fa828bb80`.
Los 14 archivos de 6.1 quedan sin commit y sin preparar; no se hizo push ni se
modificó `main`. El informe 6.0 se conserva como auditoría histórica previa a
estas correcciones.
