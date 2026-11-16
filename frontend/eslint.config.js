import js from '@eslint/js'
import globals from 'globals'

// Prettier se ocupa del formato; ESLint y Oxlint comprueban el código.
export default [
  { ignores: ['node_modules/**', 'dist/**', 'coverage/**', 'tests/browser/**'] },
  {
    files: ['src/**/*.{js,jsx}', 'tests/**/*.{js,jsx,mjs}', '*.js'],
    ...js.configs.recommended,
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      parserOptions: { ecmaFeatures: { jsx: true } },
      globals: { ...globals.browser, ...globals.node },
    },
    rules: {
      'no-unused-vars': ['error', { argsIgnorePattern: '^_', caughtErrors: 'none' }],
    },
  },
]
