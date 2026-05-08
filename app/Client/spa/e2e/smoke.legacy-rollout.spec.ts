import { expect, test, type Page } from '@playwright/test';
import { e2eEnv } from './support/env';

const hasExternalRolloutTarget = Boolean(e2eEnv.rolloutSpaBaseUrl);
const requestedCanaryPhase = e2eEnv.canaryRolloutPhase?.toLowerCase();
const canaryPhases = ['phase1', 'phase2', 'phase3'] as const;
const validCanaryPhases = new Set([...canaryPhases, 'all']);

type RolloutPhase = (typeof canaryPhases)[number];

function ensureTrailingSlash(url: string): string {
  return url.endsWith('/') ? url : `${url}/`;
}

function escapeRegex(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function assertNamedPhaseBehavior(
  page: Page,
  phase: RolloutPhase
): Promise<void> {
  const legacyHomeUrl = `${e2eEnv.legacyBaseUrl}/views/index.html`;
  const legacyLoginUrl = `${e2eEnv.legacyBaseUrl}/views/login.html`;
  const legacyServicesUrl = `${e2eEnv.legacyBaseUrl}/views/services.html`;
  const spaAuthUrl = `${e2eEnv.spaBaseUrl.replace(/\/$/, '')}/auth`;
  const spaCatalogUrl = `${e2eEnv.spaBaseUrl.replace(/\/$/, '')}/catalog`;
  const spaHomeUrl = ensureTrailingSlash(e2eEnv.spaBaseUrl);

  await page.goto(`${legacyHomeUrl}?spa=1&spaPhase=${phase}`);

  if (phase === 'phase3') {
    await expect(page).toHaveURL(spaHomeUrl);
    await expect(
      page.getByRole('heading', {
        name: 'Frontend SPA por dominios, conectado a API v1',
      })
    ).toBeVisible();
  } else {
    await expect(page).toHaveURL(new RegExp(`^${escapeRegex(legacyHomeUrl)}\\?spa=1&spaPhase=${phase}$`));
  }

  await page.goto(legacyLoginUrl);
  await expect(page).toHaveURL(spaAuthUrl);
  await expect(page.getByRole('heading', { name: 'Entrar' })).toBeVisible();

  await page.goto(legacyServicesUrl);

  if (phase === 'phase1') {
    await expect(page).toHaveURL(legacyServicesUrl);
    await expect(page.getByRole('heading', { name: 'SERVICIOS' })).toBeVisible();
    return;
  }

  await expect(page).toHaveURL(spaCatalogUrl);
  await expect(page.getByRole('heading', { name: 'Catalogo v1' })).toBeVisible();
}

test('legacy rollout gate redirects to SPA and can be disabled', async ({ page }) => {
  const legacyHomeUrl = `${e2eEnv.legacyBaseUrl}/views/index.html`;
  const spaHomeUrl = ensureTrailingSlash(e2eEnv.spaBaseUrl);

  await page.goto(`${legacyHomeUrl}?spa=1`);
  await expect(page).toHaveURL(spaHomeUrl);
  await expect(page.getByRole('heading', { name: 'Frontend SPA por dominios, conectado a API v1' })).toBeVisible();

  await page.goto(legacyHomeUrl);
  await expect(page).toHaveURL(spaHomeUrl);

  await page.goto(`${legacyHomeUrl}?spa=0`);
  await expect(page).toHaveURL(new RegExp(`^${escapeRegex(legacyHomeUrl)}\\?spa=0$`));
  await expect(page.getByRole('heading', { name: 'FEFUART' }).first()).toBeVisible();
});

test('legacy rollout gate can be scoped to selected views', async ({ page }) => {
  const legacyHomeUrl = `${e2eEnv.legacyBaseUrl}/views/index.html`;
  const legacyCartUrl = `${e2eEnv.legacyBaseUrl}/views/cart.html`;
  const spaCartUrl = `${e2eEnv.spaBaseUrl.replace(/\/$/, '')}/cart`;

  await page.goto(`${legacyHomeUrl}?spa=1&spaViews=cart`);
  await expect(page).toHaveURL(new RegExp(`^${escapeRegex(legacyHomeUrl)}\\?spa=1&spaViews=cart$`));
  await expect(page.getByRole('heading', { name: 'FEFUART' }).first()).toBeVisible();

  await page.goto(legacyHomeUrl);
  await expect(page).toHaveURL(legacyHomeUrl);

  await page.goto(legacyCartUrl);
  await expect(page).toHaveURL(spaCartUrl);
  await expect(page.getByText('Debes iniciar sesion para usar carrito y checkout.')).toBeVisible();

  await page.goto(`${legacyHomeUrl}?spa=0`);
  await expect(page).toHaveURL(new RegExp(`^${escapeRegex(legacyHomeUrl)}\\?spa=0$`));
});

test('legacy rollout gate can use named phase presets', async ({ page }) => {
  const legacyHomeUrl = `${e2eEnv.legacyBaseUrl}/views/index.html`;

  for (const phase of canaryPhases) {
    await assertNamedPhaseBehavior(page, phase);
  }

  await page.goto(`${legacyHomeUrl}?spa=0`);
  await expect(page).toHaveURL(new RegExp(`^${escapeRegex(legacyHomeUrl)}\\?spa=0$`));
});

test('legacy rollout gate can redirect to configured rollout target', async ({ page }) => {
  test.skip(
    !hasExternalRolloutTarget,
    'Set E2E_ROLLOUT_SPA_BASE_URL to validate rollout against an external SPA target.'
  );

  const legacyHomeUrl = `${e2eEnv.legacyBaseUrl}/views/index.html`;
  const rolloutTargetUrl = ensureTrailingSlash(e2eEnv.rolloutSpaBaseUrl as string);
  const encodedTarget = encodeURIComponent(rolloutTargetUrl);

  await page.goto(`${legacyHomeUrl}?spa=1&spaBase=${encodedTarget}`);
  await expect(page).toHaveURL(new RegExp(`^${escapeRegex(rolloutTargetUrl)}`));
});

test('canary validates selected rollout phase preset', async ({ page }) => {
  test.skip(
    !requestedCanaryPhase,
    'Set E2E_CANARY_ROLLOUT_PHASE=phase1|phase2|phase3|all to validate canary rollout gate.'
  );

  if (!requestedCanaryPhase || !validCanaryPhases.has(requestedCanaryPhase)) {
    throw new Error(
      `Invalid E2E_CANARY_ROLLOUT_PHASE: ${e2eEnv.canaryRolloutPhase}. Allowed values: phase1, phase2, phase3, all.`
    );
  }

  const legacyHomeUrl = `${e2eEnv.legacyBaseUrl}/views/index.html`;

  if (requestedCanaryPhase === 'all') {
    for (const phase of canaryPhases) {
      await assertNamedPhaseBehavior(page, phase);
    }
  } else {
    await assertNamedPhaseBehavior(page, requestedCanaryPhase as RolloutPhase);
  }

  await page.goto(`${legacyHomeUrl}?spa=0`);
  await expect(page).toHaveURL(new RegExp(`^${escapeRegex(legacyHomeUrl)}\\?spa=0$`));
});
