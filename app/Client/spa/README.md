# FefuArt SPA v1

React + TypeScript + Vite frontend prepared for modular backend v1.

## Scripts

- npm install
- npm run dev
- npm run build
- npm run preview

## Environment

Create .env from .env.example:

- VITE_API_BASE_URL (default suggested: http://127.0.0.1:8000/api/v1)

## Functional routes

- /auth: register + login with field-level validation feedback.
- /catalog: public catalog listing with filters.
- /cart: create/get cart, add custom item, add catalog item via selector, update/remove cart lines, checkout with address hints and client-side validation.
- /orders: user order history with status filter, in-page search, status breakdown cards and expandable line details.
- /live-art: create live art request with local draft persistence, date/phone validation, and field-level backend validation feedback.
- /notifications: list and mark read user notifications.
- /media: upload, read and delete media assets.
- /backoffice: summary + status updates for orders/events (assistant/admin).

## Integration requirements

- Backend API running from app/Server (Laravel) and reachable from VITE_API_BASE_URL.
- JWT-based auth enabled in backend.
- Backoffice route requires user role assistant or admin.
- API client now surfaces validation detail messages and explicit network connectivity errors.
- Cart UI expects v1 endpoints for PATCH/DELETE line management (/api/v1/cart/items/{id}) and filtered history (/api/v1/orders/my?status=...).

## Progressive rollout from legacy

- Legacy pages can be redirected to SPA through app/Client/config/config.js.
- Enable rollout persistently in browser: localStorage.setItem('fefuart_spa_rollout', '1').
- Disable rollout: localStorage.removeItem('fefuart_spa_rollout').
- Override SPA destination persistently: localStorage.setItem('fefuart_spa_base_url', 'https://your-spa-host').
- Scope rollout to selected legacy views: localStorage.setItem('fefuart_spa_rollout_scope', 'cart,login') or 'all'.
- Use named rollout phases: localStorage.setItem('fefuart_spa_rollout_phase', 'phase1').
- Available named phases:
  - phase1: login/register/cart
  - phase2: phase1 + services/galeria + catalog-related legacy pages + live-art pages
  - phase3: all mapped legacy views
- One-shot URL toggles from legacy page:
	- ?spa=1 enables rollout and redirects according to legacy-to-SPA map.
	- ?spa=0 disables rollout.
	- ?spaBase=https://your-spa-host stores a custom SPA base URL before redirect.
	- ?spaViews=cart,login scopes redirect to selected legacy pages only.
	- ?spaPhase=phase1 enables a named phase preset.

## QA

- Manual smoke checklist: QA_SMOKE_CHECKLIST.md

## E2E (Playwright)

- Install browsers (one-time): npx playwright install chromium
- Run smoke suite: npm run test:e2e
- Run canary suite (legacy rollout + login/cart/live-art + cart/orders editing flow): npm run test:e2e:canary
- Run extended suite (assistant + media upload): npm run test:e2e:extended
- Additional validation smoke: npm run test:e2e:validation
- Run headed mode: npm run test:e2e:headed
- Run UI mode: npm run test:e2e:ui
- Includes legacy rollout smoke that verifies redirect from legacy index to SPA and rollback via query toggle.

Optional E2E env file:

- Use .env.e2e.example as reference for test-only variables.
- E2E_ASSISTANT_EMAIL and E2E_ASSISTANT_PASSWORD enable backoffice/notifications smoke test.
- e2e/smoke.cart-orders.spec.ts includes an assistant-only status progression test (pending -> paid -> shipped) that is skipped when assistant credentials are missing.
- E2E_ENABLE_MEDIA_UPLOAD=true enables upload/delete media smoke test (disabled by default).
- E2E_LEGACY_BASE_URL configures legacy static host used by rollout smoke (default: http://127.0.0.1:4180).
- E2E_LEGACY_HOST and E2E_LEGACY_PORT control the local legacy static server used by Playwright webServer.
- E2E_ROLLOUT_SPA_BASE_URL enables external target validation in legacy rollout smoke (used by prerelease gate).
- E2E_CANARY_ROLLOUT_PHASE (phase1|phase2|phase3|all) validates selected rollout phase(s) in canary execution.

## CI prerelease gate

- Workflow dispatch input rollout_spa_base_url overrides repository variable ROLLOUT_SPA_BASE_URL.
- Workflow dispatch input rollout_phase selects phase1/phase2/phase3/all for canary rollout validation.
- Workflow dispatch default rollout_phase is all.
- Pre-release job validates target URL availability and then runs npm run test:e2e:canary.
- The gate now requires a valid HTML response and SPA bootstrap signature (root container or module/script entrypoint).
- Optional workflow_dispatch input run_extended_e2e=true runs assistant/media E2E with strict credential prerequisites.
- Helper script for dispatch from CLI: scripts/run-prerelease-gate.ps1
- Script default rollout phase is all when -RolloutPhase is omitted.
- Script supports -Watch to follow the run and -DownloadArtifacts to export artifacts locally.
- Script auth mode is auto by default (gh when available, otherwise REST with -GitHubToken or env:GITHUB_TOKEN).
- Script supports -PassThru to return run_id and run_url for automation chaining.
- Outcome inspector helper: scripts/inspect-prerelease-run.ps1 validates prerelease job step outcomes (rollout canary + validation smoke + summary publish).

Local-only equivalent (no deployment required):

- powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate-local.ps1
- powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate-local.ps1 -SeedDemoCatalog
- This command prepares sqlite testing DB, starts local Laravel backend, runs canary E2E by phase plus validation smoke, and stores artifacts under artifacts/local-prerelease.
- It also writes artifacts/local-prerelease/run-summary.md and artifacts/local-prerelease/run-summary.json with final status, duration, suites, expected spec coverage and artifact presence.
- JSON output includes schema_version and can be validated against scripts/local-prerelease-summary.schema.json.
- With -SeedDemoCatalog it seeds testing sqlite and also attempts best-effort seeding on default local environment for manual browsing.
- Use -SkipValidationSmoke only when you need to bypass e2e/smoke.validation-feedback.spec.ts temporarily.
