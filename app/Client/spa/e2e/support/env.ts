export const e2eEnv = {
  userEmail: process.env.E2E_USER_EMAIL ?? 'journey-user@example.com',
  userPassword: process.env.E2E_USER_PASSWORD ?? 'password123',
  assistantEmail: process.env.E2E_ASSISTANT_EMAIL,
  assistantPassword: process.env.E2E_ASSISTANT_PASSWORD,
  spaBaseUrl: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:4173',
  legacyBaseUrl: process.env.E2E_LEGACY_BASE_URL ?? 'http://127.0.0.1:4180',
  rolloutSpaBaseUrl: process.env.E2E_ROLLOUT_SPA_BASE_URL,
  canaryRolloutPhase: process.env.E2E_CANARY_ROLLOUT_PHASE,
};

export const hasAssistantCredentials = Boolean(
  e2eEnv.assistantEmail && e2eEnv.assistantPassword
);
