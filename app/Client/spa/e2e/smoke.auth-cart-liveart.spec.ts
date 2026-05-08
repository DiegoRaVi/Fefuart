import { expect, test } from '@playwright/test';
import { loginThroughAuthPage } from './support/auth';
import { e2eEnv } from './support/env';

function getTomorrowDateISO(): string {
  const date = new Date();
  date.setDate(date.getDate() + 1);

  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

test('authenticated user can run cart and live-art smoke flow', async ({ page }) => {
  await loginThroughAuthPage(page, e2eEnv.userEmail, e2eEnv.userPassword, {
    ensureAccount: true,
    accountName: 'E2E User',
  });

  await page.goto('/cart');
  await expect(page.getByRole('heading', { name: 'Carrito v1' })).toBeVisible();

  await page.getByRole('button', { name: 'Crear o recuperar carrito' }).click();
  await expect(page.getByText('Carrito preparado correctamente.')).toBeVisible();

  const customItemCard = page.locator('article:has(h2:has-text("Agregar item personalizado"))');
  await customItemCard.getByRole('button', { name: 'Agregar item' }).click();
  await expect(page.getByText('Item personalizado agregado al carrito.')).toBeVisible();

  const checkoutCard = page.locator('article:has(h2:has-text("Checkout"))');
  await checkoutCard.getByLabel('Direccion').fill('Avenida Playwright 123');
  await checkoutCard.getByRole('button', { name: 'Confirmar checkout' }).click();

  await expect(page.getByText('Checkout completado. Estado actual:')).toBeVisible();

  await page.goto('/live-art');
  await expect(page.getByRole('heading', { name: 'Solicitud de Live Art' })).toBeVisible();

  await page.getByLabel('Titulo').fill('Smoke Playwright Live Art');
  await page.getByLabel('Descripcion').fill('Solicitud automatizada de smoke test.');
  await page.getByLabel('Telefono').fill('600111222');
  await page.getByLabel('Fecha').fill(getTomorrowDateISO());
  await page.getByLabel('Ubicacion').fill('Sevilla');
  await page.getByLabel('Horario').selectOption('morning');

  await page.getByRole('button', { name: 'Enviar solicitud' }).click();
  await expect(page.locator('.feedback.success')).toContainText('Solicitud #');
});
