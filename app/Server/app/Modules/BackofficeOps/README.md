# BackofficeOps module

This module owns backoffice operational workflows.

Current vertical slice implemented:

- GET /api/v1/backoffice/summary
- GET /api/v1/backoffice/orders
- PATCH /api/v1/backoffice/orders/{id}/status
- GET /api/v1/backoffice/events
- PATCH /api/v1/backoffice/events/{id}/status
- role protection with backoffice middleware
- pagination and status filters
