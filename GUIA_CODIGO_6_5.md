# Guía breve para estudiar el código de BEESOCC

## Dónde buscar

| Carpeta | Responsabilidad |
| --- | --- |
| `frontend/src/components` | Elementos reutilizables: botones, formularios, tarjetas y cuadros. |
| `frontend/src/pages` | Pantallas completas que reúnen componentes. |
| `frontend/src/routes` | Relación entre direcciones del navegador y pantallas. |
| `frontend/src/services` | Peticiones HTTP a Laravel mediante Axios. |
| `frontend/src/stores` | Estado compartido y acciones de Zustand. |
| `frontend/src/utils` | Funciones pequeñas para transformar o presentar datos. |
| `frontend/src/styles` | Estilos existentes de la aplicación. |
| `frontend/tests` | Pruebas de componentes, servicios y contratos. |
| `backend/routes/api.php` | Endpoints públicos y privados de Laravel. |
| `backend/app/Http/Controllers/Api` | Recibe solicitudes, valida entradas y aplica autorizaciones. |
| `backend/app/Services` | Reglas deportivas, operaciones y cálculos del servidor. |
| `backend/app/Models` | Modelos, relaciones y campos de los datos. |
| `backend/tests` | Pruebas backend y herramientas de validación aislada. |

## Componente, servicio y store

Un **componente** recibe propiedades y devuelve una parte de la interfaz. Por ejemplo, `EstadoVista` muestra carga, error o ausencia de registros. No debe decidir quién gana un partido.

Un **servicio frontend** reúne peticiones HTTP. `grupoService.js` utiliza Axios para consultar o solicitar operaciones de grupos. Su nombre permanece en singular. Un **servicio Laravel**, en cambio, contiene reglas y operaciones del servidor, como resolver una clasificación dentro de una transacción.

Un **store** conserva datos que necesita la interfaz: listado, selección, carga, error y acciones. Ayuda a evitar envíos duplicados o respuestas antiguas. Los estados locales de un formulario pueden quedarse en `useState`; no todo necesita un store. La carpeta `hooks` permanece disponible para lógica reutilizable que realmente tenga más de un consumidor.

## Cómo viaja una solicitud

```text
Persona → página/componente → servicio Axios → ruta Laravel
         → autorización y validación → servicio Laravel → respuesta JSON
         → store o estado local → componente actualizado
```

Las operaciones privadas mantienen Sanctum, cookies y CSRF. Las consultas de espectadores usan los endpoints públicos y sus campos permitidos. React presenta la respuesta; Laravel decide los resultados oficiales, las clasificaciones y el campeón.

## Formato y revisión

Desde `frontend`:

```powershell
npm run format:check  # Comprueba el formato sin escribir.
npm run format        # Aplica Prettier a código, pruebas y configuración frontend.
npm run lint          # Ejecuta ESLint y después Oxlint.
npm run test:unit
npm test
npm run build
```

Prettier utiliza dos espacios, comillas simples en JavaScript, sin punto y coma, y ancho orientativo de 100 caracteres. Un texto literal o una lista de clases CSS puede superar ese ancho; no se corta su contenido para forzarlo. JSX conserva sus comillas de atributos habituales.

Desde `backend`, comprobar únicamente PHP versionado:

```powershell
$archivos = @(git ls-files -- '*.php')
php vendor/bin/pint --test @archivos
php artisan test --compact
```

Para aplicar formato a la aplicación y sus rutas: `php vendor/bin/pint app routes`. Pint conserva el estilo PHP de Laravel y su indentación de cuatro espacios. No usar una búsqueda recursiva que incluya herramientas privadas ignoradas. Si PHP no está en el PATH, usar el ejecutable verificado de XAMPP en lugar de `php`. Ningún comando de esta guía requiere migrar la principal.

Las recomendaciones y ajustes nuevos de `.vscode` pertenecen al proyecto; no modifican la configuración global del editor. Prettier se propone para JavaScript, JSX y CSS; Pint para PHP. Las extensiones se recomiendan, no se instalan automáticamente.

## Antes y después

Antes, un bloque comprimido obliga a leer varias decisiones juntas:

```jsx
if (error) return <div><Aviso error={error} />{reintentar && <Boton onClick={reintentar}>Volver a intentar</Boton>}</div>
```

Después, se distinguen la condición, el mensaje y la acción:

```jsx
if (error) {
  return (
    <div>
      <Aviso error={error} />
      {reintentar && <Boton onClick={reintentar}>Volver a intentar</Boton>}
    </div>
  )
}
```

En `AccionesEstadoPartido`, el ternario anidado de etiquetas pasó a la función local `textoAccion`. Las acciones permitidas siguen llegando de `accionesEstado`; no se trasladaron ni cambiaron las reglas.

Para estudiar una función, identificar primero qué recibe, qué devuelve y si modifica datos. Para explicar un cambio, mostrar su diferencia, sus pruebas y el motivo. Separar siempre formato, comportamiento y diseño visual.

Referencias de las herramientas: [configuración de Prettier](https://prettier.io/docs/configuration), [configuración de ESLint](https://eslint.org/docs/latest/use/configure/configuration-files) y [extensión de Pint](https://github.com/open-southeners/vscode-laravel-pint).
