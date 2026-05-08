import { expect, test } from '@playwright/test';

test('public shell and catalog route render', async ({ page }) => {
  await page.goto('/');

  await expect(
    page.getByRole('heading', {
      name: 'Frontend SPA por dominios, conectado a API v1',
    })
  ).toBeVisible();

  await page.getByRole('link', { name: 'Catalogo', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Catalogo v1' })).toBeVisible();
});
