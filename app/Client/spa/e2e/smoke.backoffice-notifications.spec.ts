import { expect, test } from '@playwright/test';
import { loginThroughAuthPage } from './support/auth';
import { e2eEnv, hasAssistantCredentials } from './support/env';

test.skip(
  !hasAssistantCredentials,
  'This test requires E2E_ASSISTANT_EMAIL and E2E_ASSISTANT_PASSWORD.'
);

test('assistant status update appears as customer notification', async ({ browser }) => {
  const customerContext = await browser.newContext();
  const customerPage = await customerContext.newPage();

  await loginThroughAuthPage(customerPage, e2eEnv.userEmail, e2eEnv.userPassword, {
    ensureAccount: true,
    accountName: 'E2E User',
  });

  await customerPage.goto('/cart');
  await customerPage.getByRole('button', { name: 'Crear o recuperar carrito' }).click();

  const customItemCard = customerPage.locator('article:has(h2:has-text("Agregar item personalizado"))');
  await customItemCard.getByRole('button', { name: 'Agregar item' }).click();

  const checkoutCard = customerPage.locator('article:has(h2:has-text("Checkout"))');
  await checkoutCard.getByLabel('Direccion').fill('Calle Notificacion 99');
  await checkoutCard.getByRole('button', { name: 'Confirmar checkout' }).click();

  const cartStateLine = customerPage.locator('article:has(h2:has-text("Estado del carrito")) p').first();
  await expect(cartStateLine).toBeVisible();
  const stateText = await cartStateLine.innerText();

  const orderIdMatch = stateText.match(/ID\s+(\d+)/);
  expect(orderIdMatch).not.toBeNull();

  const orderId = Number(orderIdMatch?.[1]);
  expect(orderId).toBeGreaterThan(0);

  const assistantContext = await browser.newContext();
  const assistantPage = await assistantContext.newPage();

  await loginThroughAuthPage(
    assistantPage,
    e2eEnv.assistantEmail as string,
    e2eEnv.assistantPassword as string
  );

  await assistantPage.goto('/backoffice');
  await expect(assistantPage.getByRole('heading', { name: 'Backoffice operativo' })).toBeVisible();

  const orderRow = assistantPage.locator('li', { hasText: `#${orderId}` }).first();
  await expect(orderRow).toBeVisible();

  await orderRow.locator('select').selectOption('paid');
  await orderRow.getByRole('button', { name: 'Guardar' }).click();

  await expect(assistantPage.getByText(`Pedido #${orderId} actualizado a paid.`)).toBeVisible();

  await customerPage.goto('/notifications');
  const targetCard = customerPage.locator('article.notice-card', {
    hasText: `order #${orderId}`,
  }).first();

  await expect(targetCard).toBeVisible();
  const readButton = targetCard.getByRole('button', { name: 'Marcar como leida' });
  await expect(readButton).toBeVisible();
  await readButton.click();
  await expect(targetCard.getByRole('button', { name: 'Marcar como leida' })).toHaveCount(0);

  await assistantContext.close();
  await customerContext.close();
});
