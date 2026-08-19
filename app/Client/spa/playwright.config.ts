import { defineConfig, devices } from '@playwright/test'

/**
 * E2E de los cuatro flujos (Fase 7).
 *
 * Tres piezas tienen que estar vivas a la vez y las levanta Playwright:
 * Laravel en :8000 con el entorno `e2e`, Vite en :5173 y Mailpit. La tercera
 * no es opcional — el recorrido de registro lee el enlace de verificacion de
 * la bandeja, que es la unica forma de probar que ese correo sale de verdad.
 *
 * **Todo ocurre en local.** Ningun recorrido pulsa «Pagar»: eso haria que el
 * servidor llamase a la API de Stripe, y una bateria que depende de la red
 * falla por motivos que no son el codigo. La mitad nuestra del cobro la
 * cubren los 390 tests de Pest —incluida la firma real del webhook— y aqui
 * se cubre lo que solo se ve en un navegador: que la pantalla de vuelta
 * cambia sola cuando el aviso llega.
 */
export default defineConfig({
  testDir: './e2e',

  // Cada fichero es un recorrido con su propio estado en base de datos; en
  // paralelo se pisarian la agenda de N16 y las cuentas sembradas.
  workers: 1,
  fullyParallel: false,

  // Un E2E que se reintenta es un E2E que esconde una carrera. Si falla,
  // que se vea.
  retries: 0,

  reporter: [['list']],

  // Deja la base en su estado de semilla antes de cada tanda.
  globalSetup: './e2e/preparar.ts',

  use: {
    baseURL: 'http://localhost:5173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  webServer: [
    {
      // APP_ENV=e2e es lo que hace que Laravel cargue `.env.e2e` en lugar de
      // `.env`, y con el la base `fefuart_e2e`. Sin esto los recorridos
      // vaciarian la base de desarrollo.
      command: 'php artisan serve --host=127.0.0.1 --port=8000',
      cwd: '../../Server',
      env: { APP_ENV: 'e2e' },
      url: 'http://127.0.0.1:8000/api/catalog/products',
      reuseExistingServer: false,
      timeout: 60_000,
    },
    {
      // Tiene que ser el 5173: `strictPort` esta puesto justo para que esto
      // no caiga en otro puerto y rompa la sesion sin decir por que (D2).
      command: 'npm run dev',
      url: 'http://localhost:5173',
      reuseExistingServer: true,
      timeout: 60_000,
    },
    {
      command: 'C:\\xampp\\mailpit\\mailpit.exe --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025',
      url: 'http://127.0.0.1:8025',
      reuseExistingServer: true,
      timeout: 30_000,
    },
  ],
})
