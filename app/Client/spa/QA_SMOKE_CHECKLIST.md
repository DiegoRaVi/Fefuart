# SPA v1 Smoke Checklist

## Preconditions

- Backend API running at VITE_API_BASE_URL.
- Database migrated with sample users and catalog products.
- SPA running with npm run dev.

## Auth

- Register new user from /auth and verify success message.
- Login from /auth and verify topbar shows user name and role.
- Logout and verify routes requiring auth show warning panel.

## Catalog + Cart

- Open /catalog and verify product cards load.
- In /cart, run "Crear o recuperar carrito" and verify cart id/status appears.
- Add custom item and verify total updates.
- Add from catalog by product id and verify new item is listed.
- Checkout with address and verify status transitions to pending.

## Live Art

- Open /live-art as authenticated user.
- Submit request and verify request id/status success message.

## Backoffice + Notifications

- Login with assistant/admin user.
- Open /backoffice and verify summary loads.
- Change order status and save.
- Login with affected customer in another browser session and verify /notifications shows update.
- Mark notification as read and verify unread style disappears.

## Media Assets

- Open /media and upload image (public).
- Lookup uploaded id and verify metadata.
- Delete uploaded asset and verify item disappears from session list.

## Build Validation

- Run npm run build and verify successful output in dist/.
