# Catalog module

This module owns catalog product publishing and retrieval.

Current vertical slice implemented:

- GET /api/v1/catalog/products
- GET /api/v1/catalog/products/{id}
- POST /api/v1/catalog/products
- PATCH /api/v1/catalog/products/{id}
- filters by category and subcategory
- repository abstraction and API envelope v1
