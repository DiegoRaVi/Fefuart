# IdentityAccess module

This module owns:

- authentication and session strategy
- role and permission model
- profile identity boundaries

Current vertical slice implemented:

- POST /api/v1/auth/register
- POST /api/v1/auth/login
- GET /api/v1/auth/me
- POST /api/v1/auth/logout
- request validation via FormRequest
- use case orchestration
- repository abstraction and JWT token manager
- API response envelope v1

Layer layout:

- Domain
- Application
- Infrastructure
- Interfaces
