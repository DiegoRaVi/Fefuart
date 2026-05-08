### IES ABASTOS - DAW 7K
### TRABAJO FIN CICLO 
### Diego Ramírez Villar

# FefuArt
[![CI](https://github.com/DiegoRaVi/Fefuart/actions/workflows/ci.yml/badge.svg)](https://github.com/DiegoRaVi/Fefuart/actions/workflows/ci.yml)

**FefuArt** es una página web desarrollada para el negocio de Felicitas Varela, una artista de LiveArt en eventos como bodas. El objetivo de dicha plataforma es atraer y generar confianza a su público objetivo, además de facilitar y optimizar la gestión de reservas para eventos u otros productos.

## Funcionalidades

- Frontend legacy multipage en app/Client.
- Nuevo frontend SPA v1 en app/Client/spa (migración progresiva).
- Backend Laravel modular v1 en app/Server con API versionada en /api/v1.

## Ejecución rápida

Backend (API v1):

- cd app/Server
- php artisan serve

Frontend SPA v1:

- cd app/Client/spa
- npm install
- npm run dev

Documento de trazabilidad del rebuild:

- RECREACION_DESDE_CERO.md
- ROLLOUT_PHASE_PLAN.md

## Estado CI

- Workflow principal: .github/workflows/ci.yml
- Validaciones automáticas: backend tests, SPA build y Playwright smoke.
- Gate pre-release manual: prerelease-rollout-gates (workflow_dispatch) con validación de URL objetivo de rollout y canary E2E.
- En prerelease-rollout-gates tambien se ejecuta validation smoke y se publica resultado por suite en GITHUB_STEP_SUMMARY.
- Carril extendido opcional: e2e-extended (workflow_dispatch) para escenarios assistant/media con upload real.
- Script de disparo rápido del gate: scripts/run-prerelease-gate.ps1 (modo auto: usa gh si está autenticado o REST con -GitHubToken/env GITHUB_TOKEN; soporta watch y descarga de artefactos).
- El helper remoto soporta -PassThru para devolver run_id/run_url en automatizaciones.
- Helper API para workflow_dispatch sin gh CLI: scripts/dispatch-prerelease-workflow.ps1 (requiere token explicito).
- Inspector API de outcomes por run: scripts/inspect-prerelease-run.ps1 (verifica canary + validation smoke + summary publish en el job prerelease).
- Alternativa local sin despliegue: scripts/run-prerelease-gate-local.ps1 (levanta backend local, ejecuta canary por fase y guarda artefactos).
- El runner local tambien ejecuta smoke.validation-feedback.spec.ts despues del canary (puede omitirse con -SkipValidationSmoke).
- El runner local genera artifacts/local-prerelease/run-summary.md y artifacts/local-prerelease/run-summary.json con estado final, duracion, cobertura de specs y artefactos detectados.
- Esquema del resumen JSON para integraciones: scripts/local-prerelease-summary.schema.json.
- El runner local soporta -SeedDemoCatalog para poblar productos de demo en entorno local.
- Con -SeedDemoCatalog, el runner intenta sembrar tanto el entorno testing (sqlite) como el entorno local por defecto (best-effort).

