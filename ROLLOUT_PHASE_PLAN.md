# Rollout Phase Plan (Legacy -> SPA)

## Objetivo

Activar la migracion a SPA en produccion de forma progresiva, reversible y medible.

## Fases definidas

- phase1: login, register, cart.
- phase2: phase1 + services, galeria, dibujo-encargo, letras-infantiles, ramo-flores, live-art, live-art-info.
- phase3: todas las vistas legacy mapeadas (switch practicamente completo a SPA).

## Mecanismos de activacion

Persistente (navegador):

- localStorage.setItem('fefuart_spa_rollout', '1')
- localStorage.setItem('fefuart_spa_rollout_phase', 'phase1')
- localStorage.setItem('fefuart_spa_base_url', 'https://tu-spa')

Por URL (one-shot con persistencia):

- ?spa=1
- ?spa=0
- ?spaBase=https://tu-spa
- ?spaPhase=phase1
- ?spaViews=cart,login (override fino de scope)

## Secuencia recomendada de despliegue

1. Ejecutar CI prerelease-rollout-gates con rollout_spa_base_url real y rollout_phase objetivo.
  - El lane prerelease ejecuta canary + validation smoke y publica outcome por suite en el step summary.
2. Activar phase1 en produccion y monitorizar 24-48h.
3. Si no hay regresiones, activar phase2 y monitorizar 24-48h.
4. Si se mantiene estable, activar phase3.
5. Preparar retirada controlada de vistas legacy restantes.

El gate en workflow_dispatch usa all como fase por defecto si no se selecciona otra.

## Disparo rapido desde CLI

Usa el script de ayuda para lanzar el workflow_dispatch de CI:

- powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate.ps1 -RolloutSpaBaseUrl "https://tu-spa"

Nota: si no se indica -RolloutPhase, el script usa all por defecto.

Con carril extendido opcional:

- powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate.ps1 -RolloutSpaBaseUrl "https://tu-spa" -RolloutPhase phase2 -RunExtendedE2E

Validacion canary completa en una sola ejecucion:

- powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate.ps1 -RolloutSpaBaseUrl "https://tu-spa" -RolloutPhase all

Seguir la ejecucion en tiempo real:

- powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate.ps1 -RolloutSpaBaseUrl "https://tu-spa" -RolloutPhase all -Watch

Seguir y descargar artefactos del run automaticamente:

- powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate.ps1 -RolloutSpaBaseUrl "https://tu-spa" -RolloutPhase all -Watch -DownloadArtifacts -ArtifactsDir "artifacts/prerelease-gate"

Modo de autenticacion del helper remoto:

- Por defecto usa -Mode auto: prioriza gh autenticado; si no existe gh, usa REST cuando hay -GitHubToken o env:GITHUB_TOKEN.
- Forzar REST con token explicito:
  - powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate.ps1 -RolloutSpaBaseUrl "https://tu-spa" -RolloutPhase all -Mode rest -GitHubToken "<token>" -Watch
- Inspeccionar outcomes del run prerelease mas reciente (canary + validation smoke + summary):
  - powershell -ExecutionPolicy Bypass -File scripts/inspect-prerelease-run.ps1 -GitHubToken "<token>" -Ref main -Wait -FailIfNotSuccess
- Para pipelines/scripts, usar -PassThru y reutilizar run_id sin parseo de consola:
  - powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate.ps1 -RolloutSpaBaseUrl "https://tu-spa" -RolloutPhase all -Mode rest -GitHubToken "<token>" -Watch -PassThru

## Flujo equivalente 100% local (sin despliegue)

Para entornos locales, usa el runner local que replica el gate con backend Laravel local + canary E2E:

- powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate-local.ps1

Opciones utiles:

- -RolloutPhase phase1|phase2|phase3|all (default all)
- -RolloutSpaBaseUrl http://127.0.0.1:4173
- -RunExtendedE2E (requiere E2E_ASSISTANT_EMAIL y E2E_ASSISTANT_PASSWORD en la shell)
- -SkipValidationSmoke (omite smoke.validation-feedback.spec.ts)
- -SeedDemoCatalog (carga productos de demo para que catalogo no aparezca vacio)
- -ArtifactsDir artifacts/local-prerelease

Nota: -SeedDemoCatalog siembra en testing sqlite y tambien intenta sembrar en el entorno local por defecto (best-effort) para navegacion manual.

Salida esperada del runner local:

- artifacts/local-prerelease/run-summary.md con estado (passed/failed), duracion, suites ejecutadas, cobertura de specs (canary + validation smoke) y presencia de artefactos.
- artifacts/local-prerelease/run-summary.json con los mismos datos en formato estructurado para consumo automatizado.

## Checklist por fase

- Error rate frontend estable.
- Exitos de login/register/cart sin degradacion.
- Sin incremento de errores API v1 en backend.
- Smoke E2E en verde (rollout y canary).

## Rollback rapido

- URL: ?spa=0
- o borrar flag persistente:
  - localStorage.removeItem('fefuart_spa_rollout')
  - localStorage.removeItem('fefuart_spa_rollout_scope')
  - localStorage.removeItem('fefuart_spa_rollout_phase')

## Referencias

- .github/workflows/ci.yml
- scripts/dispatch-prerelease-workflow.ps1
- scripts/inspect-prerelease-run.ps1
- scripts/local-prerelease-summary.schema.json
- app/Client/config/config.js
- app/Client/spa/e2e/smoke.legacy-rollout.spec.ts
- SESSION_HANDOFF_2026-04-29.md
