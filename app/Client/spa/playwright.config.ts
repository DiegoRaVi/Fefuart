import { defineConfig, devices } from '@playwright/test';

const host = process.env.E2E_HOST ?? '127.0.0.1';
const port = Number(process.env.E2E_PORT ?? 4173);
const baseURL = process.env.E2E_BASE_URL ?? `http://${host}:${port}`;
const legacyHost = process.env.E2E_LEGACY_HOST ?? host;
const legacyPort = Number(process.env.E2E_LEGACY_PORT ?? 4180);
const legacyBaseURL = process.env.E2E_LEGACY_BASE_URL ?? `http://${legacyHost}:${legacyPort}`;

export default defineConfig({
  testDir: './e2e',
  timeout: 90_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  retries: process.env.CI ? 2 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    headless: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  webServer: [
    {
      command: `npm run dev -- --host ${host} --port ${port}`,
      url: baseURL,
      reuseExistingServer: !process.env.CI,
      timeout: 120_000,
    },
    {
      command: `npm run serve:legacy:e2e -- --host ${legacyHost} --port ${legacyPort}`,
      url: `${legacyBaseURL}/views/index.html`,
      reuseExistingServer: !process.env.CI,
      timeout: 120_000,
    },
  ],
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
      },
    },
  ],
});
