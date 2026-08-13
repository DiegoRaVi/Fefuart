import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],

  resolve: {
    // Mismo alias que `paths` en tsconfig.app.json. Hacen falta los dos:
    // TypeScript resuelve tipos y Vite resuelve modulos.
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  server: {
    port: 5173,
    // Sin fallback a otro puerto: si 5173 esta ocupado, Vite arrancaria en
    // 5174 y ese origen no esta en SANCTUM_STATEFUL_DOMAINS, asi que la
    // sesion dejaria de funcionar con un error que no dice por que.
    strictPort: true,

    /**
     * D2 — la pieza sin la que Sanctum en modo SPA no funciona.
     *
     * El navegador pide siempre a `localhost:5173`, de modo que la cookie de
     * sesion es first-party y viaja sola. Vite reenvia a Laravel conservando
     * la cabecera `Referer`, que es como Sanctum decide si la peticion es
     * stateful: `localhost:5173` esta declarado en SANCTUM_STATEFUL_DOMAINS.
     *
     * Sin proxy habria que hacer CORS con credenciales entre dos origenes
     * distintos, que es justo lo que D2 evita.
     */
    proxy: {
      '/api': 'http://localhost:8000',
      // El endpoint que entrega la cookie CSRF lo registra el propio Sanctum
      // fuera de /api.
      '/sanctum': 'http://localhost:8000',
      // Las imagenes de referencia se sirven desde el disco publico.
      '/storage': 'http://localhost:8000',
    },
  },

  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: true,
  },
})
