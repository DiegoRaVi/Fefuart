import { expect, test, type Page } from '@playwright/test';
import { loginThroughAuthPage } from './support/auth';
import { e2eEnv, hasAssistantCredentials } from './support/env';

const apiBaseUrl =
  process.env.E2E_API_BASE_URL ??
  process.env.VITE_API_BASE_URL ??
  'http://127.0.0.1:8000/api/v1';

type ManagedOrderStatus = 'paid' | 'shipped';

function extractOrderIdFromCartStateLine(text: string): number {
  const orderIdMatch = text.match(/ID\s+(\d+)/);

  if (!orderIdMatch?.[1]) {
    throw new Error(`Could not extract order ID from cart state line: ${text}`);
  }

  const parsed = Number(orderIdMatch[1]);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    throw new Error(`Invalid order ID parsed from cart state line: ${text}`);
  }

  return parsed;
}

async function createPendingOrder(
  page: Page,
  itemName: string,
  checkoutAddress: string
): Promise<number> {
  const customItemCard = page.locator('article:has(h2:has-text("Agregar item personalizado"))');
  const checkoutCard = page.locator('article:has(h2:has-text("Checkout"))');
  const cartStateArticle = page.locator('article:has(h2:has-text("Estado del carrito"))');

  await page.getByRole('button', { name: 'Crear o recuperar carrito' }).click();
  await expect(page.getByText('Carrito preparado correctamente.')).toBeVisible();

  await customItemCard.getByLabel('Nombre').fill(itemName);
  await customItemCard.getByLabel('Precio').fill('29');
  await customItemCard.getByLabel('Cantidad').fill('1');
  await customItemCard.getByRole('button', { name: 'Agregar item' }).click();
  await expect(page.getByText('Item personalizado agregado al carrito.')).toBeVisible();
  await expect(cartStateArticle).toContainText(itemName);

  await checkoutCard.getByLabel('Direccion').fill(checkoutAddress);
  await checkoutCard.getByRole('button', { name: 'Confirmar checkout' }).click();
  await expect(page.getByText('Checkout completado. Estado actual: pending.')).toBeVisible();
  await expect(cartStateArticle).toContainText('estado pending');

  const cartStateLine = cartStateArticle.locator('p').first();
  const stateText = await cartStateLine.innerText();

  return extractOrderIdFromCartStateLine(stateText);
}

async function getAssistantToken(page: Page): Promise<string> {
  if (!e2eEnv.assistantEmail || !e2eEnv.assistantPassword) {
    throw new Error('Assistant credentials are required for this operation.');
  }

  const response = await page.request.post(`${apiBaseUrl}/auth/login`, {
    headers: {
      Accept: 'application/json',
    },
    data: {
      email: e2eEnv.assistantEmail,
      password: e2eEnv.assistantPassword,
    },
  });

  expect(response.status()).toBe(200);

  const payload = (await response.json()) as {
    data?: {
      token?: string;
    };
  };

  const token = payload.data?.token;
  if (!token) {
    throw new Error('Assistant login did not return token.');
  }

  return token;
}

async function updateOrderStatusAsAssistant(
  page: Page,
  token: string,
  orderId: number,
  status: ManagedOrderStatus
): Promise<void> {
  const response = await page.request.patch(`${apiBaseUrl}/backoffice/orders/${orderId}/status`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    data: {
      status,
    },
  });

  expect(response.status()).toBe(200);
}

test('authenticated user can edit cart lines and inspect filtered order history', async ({ page }) => {
  const runId = Date.now();
  const userEmail = `cart-orders-${runId}@example.com`;
  const userPassword = 'password123';
  const updatedLineName = `E2E Linea Editable ${runId}`;
  const checkoutLineName = `E2E Pedido Final ${runId}`;
  const targetCheckoutAddress = `Avenida Iteracion ${runId}`;
  const updateActionLabel = `Actualizar cantidad ${updatedLineName}`;
  const removeActionLabel = `Eliminar linea ${updatedLineName}`;

  await loginThroughAuthPage(page, userEmail, userPassword, {
    ensureAccount: true,
    accountName: 'E2E Cart Orders User',
  });

  await page.goto('/cart');
  await expect(page.getByRole('heading', { name: 'Carrito v1' })).toBeVisible();

  await page.getByRole('button', { name: 'Crear o recuperar carrito' }).click();
  await expect(page.getByText('Carrito preparado correctamente.')).toBeVisible();

  const customItemCard = page.locator('article:has(h2:has-text("Agregar item personalizado"))');

  await customItemCard.getByLabel('Nombre').fill(updatedLineName);
  await customItemCard.getByLabel('Precio').fill('17.5');
  await customItemCard.getByLabel('Cantidad').fill('2');
  await customItemCard.getByRole('button', { name: 'Agregar item' }).click();

  await expect(page.getByText('Item personalizado agregado al carrito.')).toBeVisible();

  const updatedLineCard = page
    .getByRole('button', { name: updateActionLabel })
    .locator('xpath=ancestor::li[contains(@class,"panel")]');
  await expect(updatedLineCard).toBeVisible();

  await updatedLineCard.getByRole('spinbutton').fill('3');
  await page.getByRole('button', { name: updateActionLabel }).click();

  await expect(page.getByText(`Cantidad actualizada para ${updatedLineName}.`)).toBeVisible();
  await expect(page.getByText(`${updatedLineName} · 3 x 17.50 EUR`)).toBeVisible();

  await page.getByRole('button', { name: removeActionLabel }).click();

  await expect(page.getByText(`Item eliminado: ${updatedLineName}.`)).toBeVisible();
  await expect(page.getByRole('button', { name: removeActionLabel })).toHaveCount(0);

  const targetOrderId = await createPendingOrder(page, checkoutLineName, targetCheckoutAddress);
  expect(targetOrderId).toBeGreaterThan(0);

  for (let i = 0; i < 5; i += 1) {
    await createPendingOrder(page, `E2E Pedido Extra ${runId} ${i}`, `Avenida Extra ${runId} ${i}`);
  }

  await page.goto('/orders');
  await expect(page.getByRole('heading', { name: 'Historial de pedidos' })).toBeVisible();

  await page.getByLabel('Estado').selectOption('pending');
  await page.getByLabel('Items por pagina').selectOption('5');
  await page.getByLabel('Buscar en pagina').fill('');

  await expect(page.getByText(/Pagina\s*1\/2/i)).toBeVisible();

  await expect(page.locator('li.panel', { hasText: targetCheckoutAddress })).toHaveCount(0);

  await page.getByRole('button', { name: 'Siguiente' }).click();
  await expect(page.getByText(/Pagina\s*2\/2/i)).toBeVisible();

  const targetOrderCard = page.locator('li.panel', { hasText: targetCheckoutAddress }).first();
  await expect(targetOrderCard).toBeVisible();
  await expect(targetOrderCard).toContainText('Pending');

  await page.getByLabel('Buscar en pagina').fill(String(runId));
  await targetOrderCard.getByRole('button', { name: /Ver lineas/ }).click();
  await expect(targetOrderCard).toContainText(checkoutLineName);
});

test('assistant can move order pending to paid and shipped for customer filter validation', async ({ page }) => {
  test.skip(!hasAssistantCredentials, 'This test requires E2E_ASSISTANT_EMAIL and E2E_ASSISTANT_PASSWORD.');

  const runId = Date.now();
  const userEmail = `cart-orders-status-${runId}@example.com`;
  const userPassword = 'password123';
  const checkoutLineName = `E2E Status Order ${runId}`;
  const checkoutAddress = `Avenida Estado ${runId}`;

  await loginThroughAuthPage(page, userEmail, userPassword, {
    ensureAccount: true,
    accountName: 'E2E Cart Status User',
  });

  await page.goto('/cart');
  await expect(page.getByRole('heading', { name: 'Carrito v1' })).toBeVisible();

  const orderId = await createPendingOrder(page, checkoutLineName, checkoutAddress);
  const assistantToken = await getAssistantToken(page);

  await updateOrderStatusAsAssistant(page, assistantToken, orderId, 'paid');
  await updateOrderStatusAsAssistant(page, assistantToken, orderId, 'shipped');

  await page.goto('/orders');
  await expect(page.getByRole('heading', { name: 'Historial de pedidos' })).toBeVisible();

  await page.getByLabel('Estado').selectOption('shipped');
  await page.getByLabel('Buscar en pagina').fill(String(runId));

  const shippedOrderCard = page.locator('li.panel', { hasText: checkoutAddress }).first();
  await expect(shippedOrderCard).toBeVisible();
  await expect(shippedOrderCard).toContainText('Shipped');

  await page.getByLabel('Estado').selectOption('paid');
  await expect(page.locator('li.panel', { hasText: checkoutAddress })).toHaveCount(0);
});
