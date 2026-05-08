import { expect, type Page } from '@playwright/test';

const apiBaseUrl =
  process.env.E2E_API_BASE_URL ??
  process.env.VITE_API_BASE_URL ??
  'http://127.0.0.1:8000/api/v1';

type LoginOptions = {
  ensureAccount?: boolean;
  accountName?: string;
};

async function ensureAccountExists(
  page: Page,
  email: string,
  password: string,
  accountName: string
): Promise<void> {
  const response = await page.request.post(`${apiBaseUrl}/auth/register`, {
    headers: {
      Accept: 'application/json',
    },
    data: {
      name: accountName,
      email,
      password,
      password_confirmation: password,
    },
  });

  if (response.status() !== 201 && response.status() !== 422) {
    const body = await response.text();
    throw new Error(`Could not ensure account exists. HTTP ${response.status()} - ${body}`);
  }
}

export async function loginThroughAuthPage(
  page: Page,
  email: string,
  password: string,
  options: LoginOptions = {}
): Promise<void> {
  const { ensureAccount = false, accountName = 'E2E User' } = options;

  if (ensureAccount) {
    await ensureAccountExists(page, email, password, accountName);
  }

  await page.goto('/auth');

  const sessionHeading = page.getByRole('heading', { name: 'Sesion activa' });
  if (await sessionHeading.isVisible().catch(() => false)) {
    await expect(page.getByRole('button', { name: 'Cerrar sesion' })).toBeVisible();
    return;
  }

  const loginCard = page.locator('article:has(h1:has-text("Entrar"))');
  await loginCard.getByLabel('Email').fill(email);
  await loginCard.getByLabel('Password').fill(password);
  await loginCard.getByRole('button', { name: 'Iniciar sesion' }).click();

  await expect(page.getByRole('button', { name: 'Cerrar sesion' })).toBeVisible();
}
