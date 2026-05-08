import { expect, test } from '@playwright/test';
import { loginThroughAuthPage } from './support/auth';

test('forms expose field-level validation feedback in auth and checkout flows', async ({ page }) => {
  const runId = Date.now();
  const registerEmail = `validation-feedback-${runId}@example.com`;
  const registerPassword = 'password123';

  await page.goto('/auth');

  const registerCard = page.locator('article:has(h1:has-text("Crear cuenta"))');
  await registerCard.getByLabel('Nombre').fill('Validation User');
  await registerCard.getByLabel('Email').fill(registerEmail);
  await registerCard.getByLabel('Password', { exact: true }).fill(registerPassword);
  await registerCard.getByLabel('Confirmar password').fill('otra-clave');
  await registerCard.getByRole('button', { name: 'Registrar y entrar' }).click();

  await expect(registerCard.locator('.field-error')).toContainText('La confirmacion debe coincidir con el password.');

  await loginThroughAuthPage(page, registerEmail, registerPassword, {
    ensureAccount: true,
    accountName: 'Validation User',
  });

  await page.goto('/cart');
  await page.getByRole('button', { name: 'Crear o recuperar carrito' }).click();
  await expect(page.getByText('Carrito preparado correctamente.')).toBeVisible();

  const customItemCard = page.locator('article:has(h2:has-text("Agregar item personalizado"))');
  await customItemCard.getByLabel('Nombre').fill(`Linea Validacion ${runId}`);
  await customItemCard.getByLabel('Precio').fill('12');
  await customItemCard.getByLabel('Cantidad').fill('1');
  await customItemCard.getByRole('button', { name: 'Agregar item' }).click();

  await expect(page.getByText('Item personalizado agregado al carrito.')).toBeVisible();

  const checkoutCard = page.locator('article:has(h2:has-text("Checkout"))');
  await checkoutCard.getByLabel('Direccion').fill('abc');
  await checkoutCard.getByRole('button', { name: 'Confirmar checkout' }).click();

  await expect(checkoutCard.locator('.field-error')).toContainText('Incluye una direccion valida (minimo 5 caracteres).');
});
