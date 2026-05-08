# OrderManagement module

This module owns:

- cart lifecycle and checkout orchestration
- order status transitions and invariants
- admin fulfillment operations

Current vertical slice implemented:

- POST /api/v1/cart
- GET /api/v1/cart
- POST /api/v1/cart/items
- PATCH /api/v1/cart/items/{id}
- DELETE /api/v1/cart/items/{id}
- POST /api/v1/cart/checkout
- GET /api/v1/orders/my (supports status and per_page)
- request validation via FormRequest
- use case orchestration and DB transaction for add item flow
- repository abstraction for carts and items
- API response envelope v1

Layer layout:

- Domain
- Application
- Infrastructure
- Interfaces
