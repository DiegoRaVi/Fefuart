import { expect, test } from '@playwright/test';
import { loginThroughAuthPage } from './support/auth';
import { e2eEnv } from './support/env';

const runMediaUpload = process.env.E2E_ENABLE_MEDIA_UPLOAD === 'true';

test('authenticated user can open media page', async ({ page }) => {
  await loginThroughAuthPage(page, e2eEnv.userEmail, e2eEnv.userPassword, {
    ensureAccount: true,
    accountName: 'E2E User',
  });

  await page.goto('/media');
  await expect(page.getByRole('heading', { name: 'Media assets' })).toBeVisible();
  await expect(page.getByText('Assets subidos en esta sesion')).toBeVisible();
});

test.describe('media upload flow', () => {
  test.skip(
    !runMediaUpload,
    'Set E2E_ENABLE_MEDIA_UPLOAD=true to run media upload/delete validation.'
  );

  test('authenticated user can upload and delete media asset', async ({ page }) => {
    await loginThroughAuthPage(page, e2eEnv.userEmail, e2eEnv.userPassword, {
      ensureAccount: true,
      accountName: 'E2E User',
    });

    await page.goto('/media');
    await expect(page.getByRole('heading', { name: 'Media assets' })).toBeVisible();

    await page.locator('input[type="file"][name="file"]').setInputFiles('../img/login.png');

    await page.getByRole('button', { name: 'Subir archivo' }).click();
    await expect(page.locator('.feedback.success')).toContainText('Asset #');

    await page.getByRole('button', { name: 'Eliminar' }).first().click();
    await expect(page.locator('.feedback.success')).toContainText('eliminado');
  });
});
