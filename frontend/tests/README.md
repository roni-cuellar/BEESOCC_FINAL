# Pruebas frontend

- `npm test`: conserva los 47 casos existentes con el runner de Node. Verifica
  servicios Axios, errores, imágenes y respuestas de Zustand fuera de orden.
- `npm run test:unit`: Vitest, entorno Node, solo `tests/unit/**/*.test.{js,jsx}`.
- `npm run test:watch`: Vitest en modo observación.
- `npm run lint` y `npm run build`: comprobaciones de código y compilación.

Vitest 5.0.5 se verificó con Node 22.18.0 y Vite 8.3.5. La configuración separa
los tests de Node para que Vitest no intente ejecutarlos como su propia suite.
No se añadieron jsdom, Testing Library ni herramientas de cobertura.

Los cinco casos iniciales de Vitest verifican contratos accesibles mediante
renderizado React a HTML y funciones de presentación existentes. No son pruebas
de interacción DOM ni un recorrido end-to-end y no conectan con ninguna API.
Los adaptadores de servicios de `servicios.test.js` tampoco escriben en bases.

Las pruebas Laravel, su aislamiento y la propuesta MySQL `beesocc_v2_test`
se documentan en `backend/tests/README.md`. Nunca dirigir pruebas con escrituras
a `beesocc_v2` ni sustituir una operación fallida por un resultado simulado.
