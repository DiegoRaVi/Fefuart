# Fefuart v2 — Roadmap

> **Documento vivo.** Se actualiza cuando una decisión importante cambia la arquitectura o el plan.
> **Punto de partida:** [AUDIT.md](AUDIT.md) — auditoría de v1, 2026-08-11.
> **Última actualización:** 2026-08-11.

---

## 1. Objetivo

Fefuart v2 es **la primera versión funcional y desplegada oficialmente** del proyecto: backend Laravel como API + frontend React como SPA independiente, sobre MySQL.

No es una migración visual de v1. El motivo de reconstruir en lugar de endurecer es concreto: en v1 **el navegador decide el rol del usuario, el precio del producto y el estado del pedido**. Ese modelo de confianza invertido no se corrige refactorizando.

Todo el desarrollo se realiza **en local**. El despliegue a staging y producción es una fase posterior y separada (Fase 8).

---

## 2. Decisiones arquitectónicas

| # | Decisión | Justificación y consecuencias |
|---|---|---|
| **D1** | Descartar la rama `autotest`; v2 desde cero | Conservada bajo el tag `archive/v2-autonomous-attempt`. Obliga a resolver DB-001. |
| **D2** | **Sanctum + cookies HttpOnly** (modo SPA) | Frontend y backend serán first-party en el mismo dominio: es el caso de uso exacto de Sanctum. El token deja de ser accesible desde JavaScript (corta la cadena de SEC-005), hay revocación real por dispositivo y logout efectivo, sin implementar refresh a mano. Se elimina `tymon/jwt-auth` y `JWT_SECRET`. Requiere `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, CORS con `supports_credentials: true` y orígenes explícitos, y CSRF vía `/sanctum/csrf-cookie`. |
| **D3** | **Pagos reales con pasarela** | `orders` separa estado de pedido y estado de pago; tablas `payments` y `webhook_events`; verificación de firma e idempotencia. Convierte SEC-006 en bloqueante: sin precios de servidor no puede haber cobro fiable. |
| **D4** | No existe producción ni datos reales | Estrategia de base de datos **limpia**: migraciones nuevas, sin backfill ni compatibilidad temporal. |
| **D5** | **Catálogo gestionable desde el backoffice** | CRUD de productos, variantes, precios y métodos de envío. Felicitas cambia precios sin tocar código. |
| **D6** | **Presupuesto de la administradora + señal** para eventos | Cada evento de bodas es distinto: tarifa fija no encaja. `events` gana estados de presupuesto, importe, señal y caducidad. Exige notificar al cliente. |
| **D7** | **Stripe en modo test** | Desarrollo y pruebas íntegramente en local con claves de test, tarjetas de prueba y Stripe CLI para webhooks. Sin contrato bancario. Redsys puede añadirse después si el banco lo exige. |
| **D8** | **Dos roles** (`customer`, `admin`) con Policies | Sin sistema de permisos en base de datos. Proporcional a un negocio de una persona. |
| **D9** | **Tailwind desde cero** | Se descartan las 2.038 líneas de CSS de v1 (quedan en el histórico de git). Se extraen su paleta y tipografía al tema de Tailwind para conservar la identidad visual. |
| **D10** | **Email + avisos dentro de la app** | El flujo presupuesto → señal exige avisar al cliente fuera de la web. Mailpit en local, sin envío real. Notificaciones nativas de Laravel en cola. |
| **D11** | **Monorepo**, manteniendo `app/Server` y `app/Client` | Un solo histórico, CI única, cambios de API y frontend en el mismo commit. No se reorganizan rutas. |
| **D12** | **TanStack Query** | Caché, estados de carga y error, reintentos e invalidación tras mutaciones sin código repetitivo. |
| **D13** | **Sin prefijo de versión en la API** | v2 es la primera versión oficial: las rutas son `/api/…`, no `/api/v1/…`. Si algún día hace falta versionar, se añade entonces. |
| **D14** | **El encargo a medida es de primera clase** | Cada dibujo, ramo o encargo es único y lleva su descripción e imagen de referencia: eso vive en `order_items` + `media_assets`. El catálogo solo define el tipo de encargo y su precio. |

**Decisiones de bajo impacto adoptadas sin consulta:** React Router para rutas · React Hook Form + Zod para formularios · Pest para backend · Vitest + Testing Library + Playwright para frontend.

### Decisiones pendientes

| # | Decisión | Cuándo hay que resolverla |
|---|---|---|
| P1 | Destino de las tablas huérfanas `media_assets` y `operational_notifications` de la BD local (DB-001) | Fase 0 — antes de crear ninguna migración de v2 |
| P2 | ¿Se conserva el frontend legacy accesible durante todo el desarrollo o se retira por pantallas? | Fase 4 |
| P3 | Política de caducidad del presupuesto de evento (`quote_expires_at`) | Fase 5 |

---

## 3. Arquitectura objetivo

### Backend

Estructura Laravel estándar, con Services solo donde hay lógica real. **Sin repositorios ni DDD por módulos:** para un CRUD de este tamaño, Eloquent ya *es* la capa de persistencia, y envolverlo en interfaces sería sobrearquitectura.

```
app/
  Enums/          OrderStatus, PaymentStatus, EventStatus, DeliveryType
  Http/
    Controllers/Api/        Auth, Catalog, Cart, Order, Event, Payment, Notification, Media
    Controllers/Api/Admin/  Product, Order, Event, Metrics
    Requests/               un Form Request por endpoint con entrada
    Resources/              un API Resource por entidad expuesta
    Middleware/             EnsureIsAdmin
  Models/
  Policies/       OrderPolicy, EventPolicy, MediaAssetPolicy
  Services/       PricingService, CartService, CheckoutService, QuoteService, StripePaymentService
  Notifications/  QuoteReady, PaymentConfirmed, OrderStatusChanged, EventConfirmed
  Jobs/
```

**Reglas no negociables**, cada una atada a un hallazgo de la auditoría:

- El precio **nunca** llega del cliente. `PricingService` lo calcula desde el catálogo → cierra **SEC-006**.
- Toda ruta que toca un recurso propio pasa por Policy, nunca por comparación inline → cierra **SEC-003, SEC-004, SEC-008, SEC-009**.
- `role` jamás es asignable en masa → cierra **SEC-001**.
- `throttle` explícito en login, registro y recuperación de contraseña → cierra **SEC-007**.
- Las respuestas van siempre por API Resource, nunca modelos crudos → cierra **ARCH-005**.
- Errores con formato único y sin trazas al cliente → cierra **SEC-012**.

### Frontend

`app/Client/spa/` — Vite + React + TypeScript. El frontend legacy de `app/Client/views/` se mantiene servido en local hasta que cada pantalla tenga equivalente en React; durante el desarrollo, el rollback es «usa la vista legacy».

```
src/
  app/        router, providers (QueryClient, AuthProvider), layouts
  features/   auth · catalog · cart · orders · live-art · backoffice · notifications
                cada una: api.ts (queries/mutations) · components/ · pages/
  shared/     api/client.ts (axios withCredentials + CSRF) · ui/ · hooks/ · lib/
```

- **Proxy de Vite** `/api` → `:8000`, imprescindible para que Sanctum vea las peticiones como same-site.
- Rutas protegidas por rol, resueltas contra `GET /api/auth/me`, **nunca** contra un valor de `localStorage`.
- **Prohibido `dangerouslySetInnerHTML`** por regla de ESLint → cierra **SEC-005**.

**Qué se reutiliza conceptualmente de v1:** los flujos de usuario, las reglas de precio (que pasan al servidor), la estructura de navegación y la identidad visual.
**Qué desaparece:** todo el JavaScript inline, `auth.js` (lo sustituye la sesión por cookie), `order.js`, `admin.js` y la duplicación de `API_URL`.

---

## 4. Esquema de base de datos

Cambio estructural clave: **separar el catálogo (qué se puede encargar y a qué precio) del encargo concreto (que sí es único y lleva su imagen)**.

El encargo a medida es la esencia del negocio, así que el modelo lo trata como ciudadano de primera. Cada dibujo sigue siendo distinto de otro; lo que deja de ser distinto es el precio, que pasa a estar en el catálogo.

| Tabla | Contenido | Notas |
|---|---|---|
| `users` | + `role` enum(`customer`,`admin`) default `customer` | `role` fuera de `$fillable` |
| `products` | **tipo de encargo:** `slug` único, `name`, `description`, `category`, `base_price`, `is_active`, `sort_order`, `requires_reference_image`, `requires_notes`, `max_quantity` | softDeletes; índice `(is_active, category)` |
| `product_variants` | variante con precio absoluto: «Diseño de moda» 30 €, «Acuarela» 40 €, «Digital» 20 € | ramo y letras tienen una variante única |
| `shipping_methods` | `code` (`physical`/`digital`), `name`, `price` | +5 € físico, 0 € digital |
| `variant_shipping_method` | pivot: qué envíos admite cada variante | modela «envío digital solo si el estilo es digital» |
| `orders` | `status`, `subtotal`, `shipping_total`, `total`, `placed_at`, dirección | importes **calculados en servidor**; softDeletes; índices `(user_id,status)` y `(status,placed_at)` |
| **`order_items`** | **el encargo concreto:** `customer_notes` · `reference_media_id` · `product_id`, `variant_id`, `shipping_method_id`, `quantity` · **snapshot** de `product_name`, `variant_name`, `unit_price`, `line_total` | Aquí vive todo lo que hace único a cada encargo. El snapshot congela el precio de compra: cambiar el catálogo no reescribe el histórico. Índice `(order_id)` |
| `media_assets` | imágenes de referencia que sube el cliente: `user_id`, `path`, `original_name`, `mime_type`, `size_bytes` | disco `public`, propiedad verificada por Policy. Se sube **antes** de añadir la línea al carrito |
| `events` | + `status` enum(`requested`,`quoted`,`accepted`,`confirmed`,`rejected`,`completed`,`cancelled`), `quoted_amount`, `deposit_amount`, `quoted_at`, `quote_expires_at` | índices `(status,event_date)` y `(user_id)` |
| `payments` | polimórfica sobre pedido o evento: `provider`, `provider_payment_intent_id` único, `amount`, `currency`, `status`, `kind` (`full`/`deposit`), `idempotency_key` único | separa estado de pago del estado de negocio |
| `webhook_events` | `provider`, `provider_event_id` **único**, `payload`, `processed_at` | garantiza idempotencia ante reenvíos de Stripe |
| `notifications` | tabla nativa de Laravel (`notifications:table`) | no se inventa una propia |

Los flags `requires_reference_image` y `requires_notes` recogen lo que hoy está cableado en los formularios: **ramo de flores** y **dibujo por encargo** exigen imagen y descripción; **letras infantiles** no pide ninguna de las dos. Así puede cambiarse por producto desde el backoffice sin tocar código.

Se mantienen `sessions`, `cache`, `jobs`, `failed_jobs`, `password_reset_tokens`. **Se elimina** `personal_access_tokens`: el modo cookie de Sanctum no lo usa.

**Estados de pedido:** `cart → pending_payment → paid → in_progress → shipped → completed`, más `cancelled`. Cada transición permitida se declara en el enum y se comprueba en `Services`, nunca en el controller.

---

## 5. API

REST bajo `/api`, **sin prefijo de versión** (D13). Sustantivos en plural, sub-recursos para transiciones de estado (`POST /orders/{id}/cancel` en vez de `PATCH status`), 422 con mapa `errors`, distinción real entre 403 y 404, paginación con `meta` uniforme.

| Área | Endpoints |
|---|---|
| Auth | `GET /sanctum/csrf-cookie` · `POST /api/auth/register` · `/login` · `/logout` · `GET /api/auth/me` |
| Catálogo (público) | `GET /api/catalog/products` · `GET /api/catalog/products/{slug}` |
| Media | `POST /api/media` · `DELETE /api/media/{id}` — se sube primero y devuelve el id que luego se adjunta a la línea |
| Carrito | `GET /api/cart` · `POST /api/cart/items` · `PATCH /api/cart/items/{id}` · `DELETE /api/cart/items/{id}` · `POST /api/cart/checkout` |
| Pedidos | `GET /api/orders` · `GET /api/orders/{id}` · `POST /api/orders/{id}/cancel` |
| Eventos | `POST /api/events` · `GET /api/events` · `GET /api/events/{id}` · `POST /api/events/{id}/accept-quote` |
| Notificaciones | `GET /api/notifications` · `PATCH /api/notifications/{id}/read` |
| Admin | `/api/admin/products` (CRUD + variantes) · `GET /api/admin/orders` · `POST /api/admin/orders/{id}/status` · `GET /api/admin/events` · `POST /api/admin/events/{id}/quote` · `POST /api/admin/events/{id}/status` · `GET /api/admin/metrics` |
| Webhooks | `POST /api/webhooks/stripe` (sin auth, firma verificada) |

El cuerpo de `POST /api/cart/items` lleva `product_id`, `variant_id`, `shipping_method_id`, `quantity`, `customer_notes` y `reference_media_id` — **nunca el precio**. El servidor lo resuelve vía `PricingService`. Si el producto tiene `requires_reference_image`, la petición se rechaza sin una `reference_media_id` válida **y propiedad del usuario**.

**Comparación con la API de v1:** de sus 40 rutas, 3 están rotas (BUG-001/002/003) y 5 tienen fallos de autorización críticos. No hay compatibilidad que preservar: la API antigua se retira entera cuando la SPA cubra su superficie.

---

## 6. Estrategia de migración

**Estrategia elegida: backend nuevo + frontend React progresivo** (combinación de las opciones C y D evaluadas).

No se hace un Big Bang. Tampoco se parchea la API de v1: con 3 rutas rotas y 5 fallos críticos de autorización, endurecerla cuesta más que rehacerla, y el modelo de datos tiene que cambiar de todos modos (no existe catálogo).

- **Base de datos:** esquema limpio con migraciones nuevas. No hay backfill porque no hay datos reales (D4, DB-008).
- **Frontend:** el HTML legacy sigue accesible en local mientras se construye la SPA. El rollback durante el desarrollo es usar la vista legacy.
- **Retirada del legacy:** cuando la SPA cubra toda la superficie funcional (Fase 7).

---

## 7. Estrategia Git

```
master            v1 congelado (referencia)
  └── develop     integración de v2
        ├── feature/…
        ├── fix/…
        ├── refactor/…
        └── docs/…
```

- `develop` ya existía y se sincronizó con `master` por fast-forward el 2026-08-11.
- `autotest` se conserva intacta, etiquetada como `archive/v2-autonomous-attempt`.
- Commits pequeños, de un solo tema. No se mezcla refactor con funcionalidad ni con cambios de esquema.
- Nunca `reset --hard`, ni borrado de ramas, ni force push.

---

## 8. Estrategia de testing

**Principio rector: cada hallazgo de la auditoría se cierra con un test que lo reproduce.** Es lo que evita que la auditoría haya que repetirla dentro de un año.

**Backend (Pest) — prioridad P0**
- El registro ignora `role` enviado por el cliente → SEC-001
- Un test por cada IDOR: pedido, línea de pedido, usuario, media → SEC-003, SEC-004, SEC-008, SEC-009
- El precio enviado por el cliente se descarta → SEC-006
- Throttle en login → SEC-007
- Un producto con `requires_reference_image` rechaza la línea sin imagen, y rechaza una `reference_media_id` de otro usuario
- Transiciones de estado inválidas rechazadas
- Webhooks de Stripe: firma inválida rechazada; evento duplicado procesado una sola vez

**Backend unitario:** `PricingService` (las 3 fórmulas, incluida la incoherencia de orden detectada en v1) y `QuoteService`.

**Frontend:** Vitest + Testing Library para lógica de componentes y formularios.

**E2E (Playwright), 4 flujos:** registro/login · catálogo → carrito → pago con tarjeta de test · solicitud de LiveArt → presupuesto → señal · gestión desde el backoffice.

**Objetivo realista:** cobertura alta en Services, Policies y endpoints. No se persigue un porcentaje global.

---

## 9. Fases

Prioridad: **P0** crítico · **P1** importante · **P2** mejora · **P3** opcional.

### Fase 0 — Contención y preparación · P0 · 🔄 en curso

| # | Tarea | Estado |
|---|---|---|
| 0.1 | Impedir que Apache sirva el árbol del proyecto (SEC-002) | ✅ `2fda29c` |
| 0.2 | Rotar `APP_KEY` y `JWT_SECRET` | ✅ 2026-08-11 |
| 0.3 | No devolver la excepción de login al cliente (SEC-012) | ✅ `b0eac92` |
| 0.4 | Sincronizar `develop` con `master` | ✅ fast-forward |
| 0.5 | Backup de la BD local y esquema limpio (DB-001) | ⏸️ pendiente de decisión P1 |
| 0.6 | Etiquetar `autotest` como archivo | ✅ `archive/v2-autonomous-attempt` |
| 0.7 | Crear `docs/AUDIT.md` y `docs/V2-ROADMAP.md` | ✅ este documento |

### Fase 1 — Núcleo del backend · P0
Sanctum en modo SPA y retirada de `jwt-auth` · CORS con orígenes explícitos y `supports_credentials` · registro seguro y throttle · Policies y `EnsureIsAdmin` · Enums de estado · esqueleto de `/api` con Form Requests, Resources y formato de error único · tests de autenticación y autorización.
**Cierra:** SEC-001, SEC-003, SEC-004, SEC-007, SEC-008, SEC-009, SEC-011, SEC-013, ARCH-001, ARCH-002, ARCH-004.
*Depende de: Fase 0.*

### Fase 2 — Catálogo y esquema · P0
Migraciones de `products`, `product_variants`, `shipping_methods`, pivot y `media_assets` · `PricingService` con las 3 fórmulas · seeder del catálogo real · endpoints públicos de catálogo · subida de imágenes de referencia con Policy de propiedad · CRUD de administración · índices de DB-003.
**Cierra:** SEC-006, SEC-014, DB-002, DB-003.
*Depende de: Fase 1.*

### Fase 3 — Base de la SPA · P0
Vite + React + TypeScript + Tailwind con la paleta extraída de v1 · proxy hacia la API · cliente axios con CSRF · AuthProvider y rutas protegidas · layout y navegación · páginas de login y registro.
**Cierra:** SEC-005 (por construcción).
*Depende de: Fase 1.*

### Fase 4 — Flujos funcionales · P1
Catálogo y ficha de producto · **formulario de encargo a medida** (descripción + imagen de referencia + variante + envío) · carrito con precios de servidor · pedidos del cliente · solicitud de LiveArt · backoffice de pedidos, eventos y catálogo, con la imagen de referencia visible en cada línea.
**Cierra:** SEC-010, BUG-001 a BUG-008, PERF-001, PERF-002, PERF-004.
*Depende de: Fases 2 y 3.*

### Fase 5 — Pagos · P1 · ⚠️ riesgo alto
Tablas `payments` y `webhook_events` · `StripePaymentService` · checkout con PaymentIntent · webhook con verificación de firma e idempotencia · flujo de presupuesto y señal para eventos (D6) · reconciliación de estados.
*Depende de: Fase 4.* Es la parte más delicada del proyecto.

### Fase 6 — Notificaciones · P1
Mailpit en local · notificaciones de Laravel en cola · email + centro de avisos en la app · `QuoteReady`, `PaymentConfirmed`, `OrderStatusChanged`, `EventConfirmed`.
*Depende de: Fase 5.*

### Fase 7 — Testing, hardening y retirada del legacy · P1
E2E de los 4 flujos · repaso de seguridad completo sobre v2 · CSP · `composer audit` y `npm audit` · retirada de `app/Client/views` y del frontend legacy · documentación final.
*Depende de: Fase 6.*

### Fase 8 — Despliegue · pospuesta
Staging, producción, CI/CD, infraestructura, backups, monitorización, dominio, HTTPS, variables de entorno y rollback.
**Fuera del alcance actual.** Incluye pasar el DocumentRoot a `app/Server/public` por VirtualHost, `APP_DEBUG=false` y la revisión de CORS y cookies para dominio real.

---

## 10. Criterios de aceptación por fase

Una fase no se da por terminada hasta que:

1. `php artisan test` pasa en verde, incluidos los tests de regresión de los hallazgos que la fase cierra.
2. Los hallazgos listados en «Cierra» están marcados como corregidos en [AUDIT.md](AUDIT.md).
3. El flujo afectado se ha probado a mano en local.
4. Los commits son pequeños, de un solo tema y revisables.
5. Este documento refleja cualquier decisión nueva que haya surgido.
