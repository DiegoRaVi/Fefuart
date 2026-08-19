# Fefuart v2 — Roadmap

> **Documento vivo.** Se actualiza cuando una decisión importante cambia la arquitectura o el plan.
> **Punto de partida:** [AUDIT.md](AUDIT.md) — auditoría de v1, 2026-08-11.
> **Cómo funciona el cobro:** [PAGOS.md](PAGOS.md) — el recorrido completo de un pago, paso a paso.
> **Última actualización:** 2026-08-19.

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
| **D15** | **El frontend legacy no se mantiene vivo** | En cuanto la Fase 1 sustituya JWT por Sanctum y renombre las rutas, el frontend de `app/Client/views/` dejará de funcionar por completo: llama a endpoints que ya no existirán. **No se intenta evitarlo.** Mantener la API de v1 en paralelo obligaría a conservar vivas sus cinco vulnerabilidades críticas durante meses. Sin usuarios ni producción, ese «fallback» no protege de nada. El legacy permanece en el repositorio como referencia de flujos y diseño mientras se construyen las pantallas React, y se retira en la Fase 7. |
| **D21** | **Desactivación y supresión son cosas distintas** | `users.deactivated_at` (timestamp nullable, `NULL` = activa) deshabilita la cuenta de forma **reversible** con los datos intactos: sirve para suspender a alguien o para que el cliente aparque su cuenta. **No cubre el RGPD.** Un borrado lógico conserva el dato personal y lo mantiene tratable, así que el derecho de supresión (art. 17) exige además el mecanismo de D22. Se eligió timestamp sobre booleano: da lo mismo (`IS NULL` = activa) y además cuándo ocurrió. |
| **D22** | **Supresión RGPD por anonimización: el pedido sobrevive, la identidad no** | Ante una petición del art. 17 se anonimiza el usuario —nombre, email, teléfono, dirección y las imágenes de referencia que subió— y **la fila del pedido se conserva** con su importe y su fecha. Lo ampara el art. 17(3)(b): conservar lo que exige una obligación legal. En España la documentación contable debe guardarse varios años; **el plazo exacto aplicable es cuestión para la gestoría, no para este documento**. Irreversible por definición: si se pudiera deshacer, no sería supresión. |
| **D23** | **Tabla `roles` con `1 = cliente`, `2 = admin`** | Sustituye a la columna de texto. Los ids se eligieron así a propósito: el rol al que se llega por accidente —un `role_id` sin fijar, un `1` usado como valor por defecto— debe ser el que menos puede hacer. Es la misma familia de fallo que produjo SEC-001, donde el valor por defecto acababa siendo el privilegiado. La columna va `NOT NULL` **sin `DEFAULT`**, de modo que un insert incompleto falla en vez de conceder nada, y el código nunca escribe el número: siempre el enum `UserRole`, que pasa a estar respaldado por entero. |
| **D16** | **`quantity` son copias, no encargos** | Cada línea del carrito es un dibujo con su foto. La cantidad son láminas de esa misma obra, y por eso **no** se multiplica el precio completo: ver la regla N4. |
| **D17** | **El envío se cobra por pedido, no por línea** | v1 lo sumaba por producto (tres artículos = 15 €). Sube de `order_items` a `orders`. Reglas N5–N7. |
| **D18** | **Precios con IVA incluido, sin facturación automática** | El precio mostrado es el final; las facturas se gestionan fuera del sistema. Se evita así toda la complejidad de numeración correlativa, datos fiscales y desglose. Revisar si el negocio pasa a necesitar facturación desde la web. |
| **D19** | **Sin fase de aprobación de boceto** | La máquina de estados de pedido se mantiene simple: no hay ida y vuelta de propuestas dentro del sistema. |
| **D20** | **Entrega digital desde el detalle del pedido** | La artista sube el archivo final y el cliente lo descarga con el acceso verificado por Policy. Cierra una funcionalidad que en v1 se cobraba (estilo «Digital», 20 €) pero no existía. |
| **D24** | **El carrito y la consulta de pedidos entran en la Fase 2** | La Fase 2 se comprometía a cerrar SEC-003, SEC-004, SEC-006 y SEC-008 sin construir ninguna ruta que los tocara. Pero el principio de testing del proyecto es que **cada hallazgo se cierra con el test que lo reproduce**, y el test que reproduce SEC-006 es «mando `price` en el cuerpo y compruebo que se ignora»: eso exige `POST /api/cart/items`. Una Policy sin ruta solo se prueba a nivel unitario (`$user->can(…)`), que no es el IDOR. Se traen desde la Fase 4 el carrito, el listado y el detalle de pedido y la cancelación. La Fase 4 queda como frontend, backoffice y eventos. |
| **D25** | **Migraciones reescritas en sitio, no apiladas** | Consecuencia directa de D4: sin datos reales ni producción, el árbol de migraciones debe leerse como el esquema objetivo, no como su historia. Se reescriben las de v1 (`orders`, `products`, `events`) en lugar de encadenar `ALTER`s encima, y `users` pasa a crear también `roles`. El coste es un `migrate:fresh` local, con backup previo fuera del repositorio. |
| **D26** | **Copia adicional: 10 €. El límite de una copia lo pone la *entrega*, no el producto** | N4 exigía un `additional_copy_price` que ningún documento fijaba: son 10 € en **todas** las variantes. La primera copia paga el trabajo artístico; las siguientes, solo la impresión. *Afinado al implementarlo:* «Digital» es una **variante**, no un tipo de entrega, y esa variante también se puede imprimir — así que también cobra la copia. Lo que no admite copias es la **entrega digital**, porque es el mismo fichero, y eso depende del `delivery_type` de la línea, no del producto: lo aplica `CartService`. `products.max_quantity` queda como el tope general del producto. Ambos importes son semilla editable desde el backoffice (D5). Resuelve P4. |
| **D28** | **Dos búsquedas en el backoffice: una caja ancha y un modal por campo** | La caja mira en todo a la vez —número, nombre y email de la cuenta, nombre del envío, teléfono; en eventos también título y lugar— y sirve para el caso de todos los días: alguien escribe y se pega lo que haya dado. Es cómoda pero imprecisa: «600» mezcla teléfonos, números de pedido y nombres con esas cifras. El modal acota a **un solo campo**, y con eso lo que sale es exactamente lo que se pidió. Solo un campo activo a la vez, y no por limitación técnica: dos rellenos obligarían a decidir si se combinan con `Y` o con `O`, y ninguna respuesta es evidente para quien busca con prisa. El campo elegido sí se cruza con estado y fechas. **«Nombre» mira en el de la cuenta y en el del envío**: pueden no coincidir —un regalo, alguien que pide para otra persona— y quien busca no sabe cuál le han dado. Detalles que salieron al implementarlo: los `OR` van agrupados o el filtro de estado deja de aplicarse; un número de menos de cuatro cifras se busca **solo** como número de pedido, porque buscar «1» también por teléfono devolvía casi todo; y los campos inactivos del modal van `readOnly` y no `disabled`, porque un input deshabilitado no recibe clics ni foco y hay lectores de pantalla que se lo saltan. |
| **D27** | **`events` en la Fase 2 solo con el esquema base** | Se rehace la tabla con los campos que N14 necesita para presupuestar (`guest_count`, `duration_hours`, `event_type`) y se escribe `EventPolicy`. Las columnas de presupuesto y señal, y el `confirmed_slot` de N16, esperan a la Fase 5: es donde nace el flujo y donde la colisión de fechas se puede probar de verdad. Además `confirmed_slot` es una columna generada con sintaxis de MariaDB y los tests corren en SQLite; esa portabilidad se resuelve cuando la columna tenga uso, no antes. **Cumplida a medias, y para bien:** `confirmed_slot` se adelantó a la Fase 2 porque la portabilidad resultó ser una sola diferencia entre motores —cómo se pegan dos cadenas— y no justificaba dejar la agenda sin proteger. Las columnas de presupuesto y señal sí esperaron, y llegaron en la Fase 5 con el flujo que las usa. |
| **D29** | **Checkout hospedado de Stripe, y el webhook como única fuente de verdad del cobro** | El formulario de tarjeta lo sirve Stripe en su dominio: ningún dato de pago pasa por nuestro origen y las obligaciones de cumplimiento se reducen al mínimo. La consecuencia importante es la otra mitad: **la página de vuelta no vale como prueba de pago** —es una URL que el cliente puede abrir a mano— así que `paid` y `confirmed` solo los alcanza `POST /api/webhooks/stripe` con la firma verificada sobre los bytes crudos. Cinco detalles que cambian el resultado y salieron al implementarlo: (1) se entrega en `checkout.session.completed` **y** en `async_payment_succeeded`, mirando `payment_status`, porque con el primero solo, un pago diferido no se entregaría nunca y uno que acabe fallando se entregaría igual; (2) cada línea va a Stripe como **una unidad con el total ya calculado**, porque N4 dice que la línea no es unitario × cantidad y dejar multiplicar a Stripe cobraría de más; (3) se comprueba que el importe cobrado sea el guardado **antes** de entregar; (4) no se manda `payment_method_types` —omitirlo es lo que deja a Stripe ofrecer los métodos del panel y los que encajen con cada cliente—; (5) la clave de idempotencia es determinista y no un UUID, de modo que Stripe devuelve la misma sesión ante una petición repetida y el índice único de `payments` para al perdedor de dos peticiones simultáneas. |
| **D31** | **El aviso se encola dentro del mismo `if` que hace el cambio de estado** | Es la regla que sostiene toda la Fase 6, y nace de que la garantía de Stripe sea «al menos una vez». `StripeWebhookService` tiene tres guardas encadenadas y **solo dos sirven de gancho**: `registrar()` corta cuando el evento ya se atendió entero, pero no basta —por D30 una entrega puede fallar *después* de guardar el cobro, y entonces el evento se reentrega con `processed_at` a `null` y `despachar()` corre otra vez de arriba abajo—; las que sí valen son el `if ($payment->status !== Succeeded)` de `cumplir()`, que corre una vez por cobro y jamás dos, y la comprobación de estado del payable en `entregar()`. Encolar un nivel más arriba produce un correo por reintento. **Consecuencias que salieron al implementarlo:** (1) no hace falta `after_commit` en la cola —la excepción de un `save()` que choca contra un índice único sale antes de llegar a ninguna línea de aviso, así que la garantía la da la posición y no un flag—; (2) Laravel encola **un job por canal**, de modo que `mail` y `database` no comparten job y un fallo de SMTP no puede reescribir la fila del centro de avisos; (3) los avisos se disparan desde Services y no desde controllers, lo que obligó a extraer `OrderService::cambiarEstado()` —la transición de pedido estaba en el controller, contra lo que dice §4. |
| **D32** | **Un hecho, un aviso: la artista también recibe los suyos** | Las cuatro notificaciones que listaba D10 miraban solo al cliente, y así el flujo se atasca en dos sitios: N13 dice que el precio de un evento es siempre a medida, así que una solicitud espera un presupuesto que solo puede emitir Felicitas; y un encargo pagado un domingo no existe hasta que alguien abra el backoffice. Entran `NewLiveArtRequest`, `OrderPaid` y `SlotFreed` —esta ultima cuando el cliente cancela y la franja que N16 bloqueaba vuelve a la agenda—, con destinatarios resueltos por rol (`User::admins()`) y no por un id fijo. La otra mitad de la decisión es la contraria: **no se avisa dos veces del mismo hecho**. La señal de un evento manda solo `EventConfirmed` —con el importe dentro, porque aceptar, pagar y reservar fueron un solo gesto—, y el paso a `paid` del webhook no manda además `OrderStatusChanged`. El riesgo aceptado es que en la colisión de N16 el cliente pague y no reciba nada; ese caso ya exige devolución a mano. |
| **D30** | **El cobro se guarda aunque la entrega falle** | Marcar el pago y entregar lo que desbloquea **no** van en la misma transacción. El caso real es la colisión de franja de N16: dos clientes pagando la misma fecha a la vez. Si fueran una sola transacción, el rollback borraría el «cobrado» cuando el dinero se ha movido de verdad, y la artista se quedaría sin saber a quién devolver. Así el cobro queda guardado, el evento no avanza, y el motivo —con el número de cobro a devolver— queda en `webhook_events.error`. El webhook responde 500 a propósito para que Stripe reintente: responder 2xx dejaría el evento muerto con un error que nadie ha visto. |

**Decisiones de bajo impacto adoptadas sin consulta:** React Router para rutas · React Hook Form + Zod para formularios · Pest para backend · Vitest + Testing Library + Playwright para frontend.

### Decisiones pendientes

| # | Decisión | Cuándo hay que resolverla |
|---|---|---|
| ~~P1~~ | **Resuelta:** el presupuesto caduca, y el plazo es **configurable** (`settings.quote_validity_days`, 14 días por defecto) en vez de estar fijado en código. Se congela al presupuestar: cambiar el ajuste no mueve la caducidad de los ya emitidos. Un presupuesto caducado no se puede aceptar —ni desde la SPA, que ni ofrece el botón, ni desde la API, que responde 422— y la artista puede emitir uno nuevo. Sin esto, un presupuesto de hace ocho meses se podría aceptar hoy a aquel precio. | Fase 5 |
| ~~P2~~ | **Resuelta → N21:** la señal no se devuelve si cancela el cliente; se devuelve entera si cancela la artista. La devolución la hace el código y no la artista a mano en el panel, porque la regla es determinista y olvidarse de aplicarla deja al cliente sin su dinero. Lo que sí es explícito es el gesto: el botón del backoffice dice «Cancelar y devolver la señal (360,00 €)» y la pantalla del cliente avisa de que la perderá **antes** de que pulse, no después. Ningún endpoint devuelve dinero por su cuenta: se llega ahí como consecuencia declarada de cancelar. | Fase 5 |
| ~~P3~~ | **Resuelta:** eliminación de cuenta → D21 (desactivación reversible) + D22 (supresión por anonimización). **Se implementa al final de la Fase 4 y antes de la Fase 5.** El motivo es técnico: hoy suprimir una cuenta sería `$user->delete()`, pero en cuanto existan pedidos pagados y eventos confirmados hay que anonimizar en vez de borrar. Escribirlo antes significa escribirlo dos veces. Antes de la Fase 5 sí es exigible: con pagos reales entran datos de terceros y el margen se estrecha. | Fin de Fase 4 |
| ~~P5~~ | **Resuelta** en `b0c24d9` (Fase 4a). El backend responde en español, nombres de campo incluidos, y los asuntos de las notificaciones también. `APP_LOCALE` se fija además en `phpunit.xml` para que los tests no dependan del `.env` de quien los ejecute. El fallback se queda en inglés a propósito: si algún día falta una clave, es preferible ver el texto original que la clave cruda. | Fase 4 |
| ~~P4~~ | **Resuelta:** los precios (30/40/20 € por variante, 40 € ramo y letras, 5 € de envío) se siembran tal cual, y el hueco que faltaba —el precio de la copia adicional— lo fija **D26**. Todos son semilla editable desde el backoffice, así que ninguno queda congelado en código. | Fase 2 |

*Resueltas:* destino de las tablas huérfanas de la BD local (DB-001, Fase 0) · continuidad del frontend legacy (D15) · semántica de `quantity` (D16) · cálculo del envío (D17) · IVA y facturación (D18) · fase de boceto (D19) · entrega digital (D20) · cálculo de la señal (N15) · política de cancelación (N12) · precios de semilla y copia adicional (D26).

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

`app/Client/spa/` — Vite + React + TypeScript. El frontend legacy de `app/Client/views/` se conserva únicamente como referencia de flujos y diseño (D15): dejará de funcionar en la Fase 1 y no se mantiene.

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
| `roles` | `id`, `name`. Dos filas: **1 = cliente, 2 = admin** (D23) | Semilla fija; no se gestiona desde el backoffice |
| `users` | + `role_id` FK **`NOT NULL` sin `DEFAULT`** · `deactivated_at` (nullable) · `phone` · dirección predeterminada | `role_id` fuera de `$fillable` (SEC-001). `email_verified_at` pasa a usarse de verdad. `deactivated_at` es desactivación reversible (D21), no supresión (D22) |
| `products` | **tipo de encargo:** `slug` único, `name`, `description`, `category`, `is_active`, `sort_order`, `requires_reference_image`, `requires_notes`, `max_quantity`, `delivery_days` | El precio **no** vive aquí, sino en las variantes. softDeletes; índice `(is_active, category)` |
| `product_variants` | `name`, `price` (primera copia, IVA incluido), `additional_copy_price`, `is_active` | «Diseño de moda» 30 €, «Acuarela» 40 €, «Digital» 20 €. Ramo y letras tienen una variante única. Todo producto tiene al menos una |
| `shipping_methods` | `code` (`physical`/`digital`), `name`, `price` | 5 € físico, 0 € digital. Se aplica **una vez por pedido** |
| `variant_shipping_method` | pivot: qué tipos de entrega admite cada variante | modela «envío digital solo si el estilo es digital» |
| `orders` | `status`, `subtotal`, `shipping_method_id`, `shipping_total`, `total`, `placed_at`, dirección de envío | importes **calculados en servidor**; softDeletes; índices `(user_id,status)` y `(status,placed_at)` |
| **`order_items`** | **el encargo concreto:** `customer_notes` · `reference_media_id` (la foto de partida) · `delivered_media_id` (el archivo final que sube la artista) · `product_id`, `variant_id`, `delivery_type`, `quantity` · **snapshot** de `product_name`, `variant_name`, `unit_price`, `additional_copy_price`, `line_total` | El snapshot congela el precio de compra: cambiar el catálogo no reescribe el histórico. Índice `(order_id)` |
| `media_assets` | `user_id` (quien sube), `path`, `original_name`, `mime_type`, `size_bytes`, `visibility` | disco `public` para referencias, `private` para entregas digitales. Propiedad verificada por Policy |
| `events` | `title`, `description`, `phone`, `event_date`, `schedule`, `location` · **nuevos:** `guest_count`, `duration_hours`, `event_type` · **presupuesto:** `status`, `quoted_amount`, `deposit_amount`, `quoted_at`, `quote_expires_at` · `confirmed_slot` (columna generada) | índices `(status,event_date)` y `(user_id)`; **índice único sobre `confirmed_slot`** |
| `settings` | clave-valor para parámetros de negocio editables | `deposit_percentage` (30) y `quote_validity_days` (14). **Qué claves existen lo declara `SettingsService`, no la petición**: sin eso, `PATCH /api/admin/settings` sería un almacén de claves arbitrarias escribible desde fuera |
| `payments` | polimórfica sobre pedido o evento: `provider`, `provider_session_id` **único y obligatorio**, `provider_payment_intent_id` único y nullable, `amount`, `currency`, `status`, `kind` (`full`/`deposit`), `idempotency_key` único, `paid_at`, `failure_reason` | separa estado de pago del estado de negocio. Con Checkout hospedado la **sesión** es lo primero que existe y el PaymentIntent aparece después: un cobro abandonado tiene sesión y no tiene intent |
| `webhook_events` | `provider`, `provider_event_id` **único**, `payload`, `processed_at` | garantiza idempotencia ante reenvíos de Stripe |
| `notifications` | tabla nativa de Laravel (`notifications:table`) | no se inventa una propia |

Se mantienen `sessions`, `cache`, `jobs`, `failed_jobs`, `password_reset_tokens`. **Se eliminan** `personal_access_tokens` (el modo cookie de Sanctum no lo usa) y la columna `stock` (no tiene sentido en encargos a medida).

**Estados de pedido:** `cart → pending_payment → paid → in_progress → shipped → completed`, más `cancelled`. **No hay fase de boceto** (D19). Cada transición permitida se declara en el enum y se comprueba en `Services`, nunca en el controller.

**Estados de evento:** `requested → quoted → accepted → confirmed → completed`, más `rejected` y `cancelled`.

---

## 4.1 Reglas de negocio

Extraídas de la sesión de descubrimiento del 2026-08-11/12. **Ninguna de estas reglas puede vivir en el navegador.**

### Catálogo y precios

| # | Regla |
|---|---|
| **N1** | Los cuatro servicios son: **Live Art** (espectáculo en directo, presupuesto a medida), **Dibujo por encargo** (retratos a partir de fotos), **Letras infantiles** (letras ilustradas) y **Ramos dibujados** (el ramo de novia en lámina). Los tres últimos son productos de catálogo. |
| **N2** | **Todos los precios llevan IVA incluido.** El precio que ve el cliente es el final. El sistema **no** emite facturas: se gestionan por fuera. No hay columnas de base imponible ni cuota. |
| **N3** | `quantity` = **copias de la misma lámina**, no encargos distintos. Quien quiera dos dibujos diferentes añade dos líneas al carrito. |
| **N4** | **Precio de una línea = `unit_price` + `additional_copy_price` × (`quantity` − 1).** La primera copia paga el trabajo artístico; las siguientes solo la impresión. Ambos precios se configuran por variante desde el backoffice. |
| **N5** | **El envío se cobra una sola vez por pedido**, no por producto (v1 lo cobraba por línea: tres artículos eran 15 €). |
| **N6** | Un pedido paga envío físico **si contiene al menos una línea física**. Si todas sus líneas son digitales, el envío es 0. |
| **N7** | El tipo de entrega se elige por línea y está limitado por la variante: solo el estilo «Digital» admite entrega digital. |
| **N8** | Un pedido puede mezclar productos de tipos distintos (un ramo y unas letras en el mismo carrito). |

### Encargos

| # | Regla |
|---|---|
| **N9** | La imagen de referencia **no es un adjunto opcional: es el material de partida**. Los retratos y los ramos se dibujan a partir de la foto del cliente. `requires_reference_image` está activo en «Dibujo por encargo» y «Ramos dibujados», y desactivado en «Letras infantiles». |
| **N10** | **No existe fase de aprobación de boceto.** El cliente encarga, paga y recibe la obra terminada. |
| **N11** | Los productos digitales se entregan **descargándolos desde el detalle del pedido**. La artista sube el archivo final y el acceso se verifica por Policy: solo el dueño del pedido puede descargarlo. |
| **N12** | El cliente puede cancelar **solo antes de pagar**. Una vez pagado, la cancelación se acuerda directamente con la artista y la aplica ella desde el backoffice. **No hay reembolsos automáticos** en el alcance actual. |

### Eventos Live Art

| # | Regla |
|---|---|
| **N13** | El precio **siempre es a medida**: la artista revisa la solicitud y emite un presupuesto. No hay tarifas publicadas. |
| **N14** | Para presupuestar necesita, además de lo que ya se pide: **número aproximado de invitados**, **duración del servicio en horas** y **tipo de evento** (boda, comunión, empresa…). Los dos primeros son los que determinan la tarifa. |
| **N15** | La reserva se confirma con una **señal calculada como porcentaje fijo del presupuesto**, configurable desde el backoffice (`settings.deposit_percentage`). |
| **N16** | **No puede haber dos eventos confirmados en la misma fecha y franja.** Se aplica en la base de datos mediante una columna generada `confirmed_slot` con índice único: vale `NULL` salvo que el evento esté confirmado, y los `NULL` no colisionan entre sí en MariaDB. La aplicación no es la única línea de defensa. |
| **N17** | Las solicitudes sí pueden solaparse: varios clientes pueden pedir la misma fecha. Solo una llega a confirmarse. |

### Cuentas

| # | Regla |
|---|---|
| **N18** | **El registro es obligatorio** para encargar. No hay compra como invitado. |
| **N19** | v2 incorpora **recuperación de contraseña por email**, **verificación de email al registrarse** y **edición de perfil con cambio de contraseña** — las tres inexistentes en v1 pese a que sus tablas y columnas ya estaban creadas. |
| **N20** | Dos roles: `customer` y `admin`. La promoción a `admin` nunca ocurre por petición HTTP. |
| **N21** | La **señal no se devuelve si cancela el cliente**, y se devuelve entera si cancela la artista. La señal reserva la fecha y bloquea la agenda: quien se echa atrás compensa el hueco; si el hueco lo libera ella, no hay nada que compensar. Resuelve P2. |

---

## 5. API

REST bajo `/api`, **sin prefijo de versión** (D13). Sustantivos en plural, sub-recursos para transiciones de estado (`POST /orders/{id}/cancel` en vez de `PATCH status`), 422 con mapa `errors`, distinción real entre 403 y 404, paginación con `meta` uniforme.

| Área | Endpoints |
|---|---|
| Auth | `GET /sanctum/csrf-cookie` · `POST /api/auth/register` · `/login` · `/logout` · `GET /api/auth/me` |
| Catálogo (público) | `GET /api/catalog/products` · `GET /api/catalog/products/{slug}` |
| Media | `POST /api/media` · `DELETE /api/media/{id}` — se sube primero y devuelve el id que luego se adjunta a la línea |
| Carrito | `GET /api/cart` · `POST /api/cart/items` · `PATCH /api/cart/items/{id}` · `DELETE /api/cart/items/{id}` · `POST /api/cart/checkout` |
| Pedidos | `GET /api/orders` · `GET /api/orders/{id}` · `POST /api/orders/{id}/cancel` · `POST /api/orders/{id}/pay` (cuerpo vacío: abre la sesión de Stripe y devuelve su URL) |
| Eventos | `POST /api/events` · `GET /api/events` · `GET /api/events/{id}` · `POST /api/events/{id}/accept-quote` |
| Notificaciones | `GET /api/notifications` · `PATCH /api/notifications/{id}/read` |
| Admin | `/api/admin/products` (CRUD + variantes) · `GET /api/admin/orders` · `POST /api/admin/orders/{id}/status` · `GET /api/admin/events` · `POST /api/admin/events/{id}/quote` · `POST /api/admin/events/{id}/status` · `GET`/`PATCH /api/admin/settings` · `GET /api/admin/metrics` |
| Webhooks | `POST /api/webhooks/stripe` (sin auth, firma verificada) |

El cuerpo de `POST /api/cart/items` lleva `product_id`, `variant_id`, `shipping_method_id`, `quantity`, `customer_notes` y `reference_media_id` — **nunca el precio**. El servidor lo resuelve vía `PricingService`. Si el producto tiene `requires_reference_image`, la petición se rechaza sin una `reference_media_id` válida **y propiedad del usuario**.

**Comparación con la API de v1:** de sus 40 rutas, 3 están rotas (BUG-001/002/003) y 5 tienen fallos de autorización críticos. No hay compatibilidad que preservar: la API antigua se retira entera cuando la SPA cubra su superficie.

---

## 6. Estrategia de migración

**Estrategia elegida: backend nuevo + frontend React progresivo** (combinación de las opciones C y D evaluadas).

No se hace un Big Bang. Tampoco se parchea la API de v1: con 3 rutas rotas y 5 fallos críticos de autorización, endurecerla cuesta más que rehacerla, y el modelo de datos tiene que cambiar de todos modos (no existe catálogo).

- **Base de datos:** esquema limpio con migraciones nuevas. No hay backfill porque no hay datos reales (D4, DB-008).
- **Frontend:** el HTML legacy queda como referencia, no como fallback (D15). Deja de funcionar en la Fase 1 y no se mantiene.
- **Retirada del legacy:** cuando la SPA cubra toda la superficie funcional (Fase 7).

**Sobre el rollback:** durante el desarrollo no existe «volver a v1» — el rollback real es `git`. Es aceptable precisamente porque no hay usuarios ni producción; cuando los haya (Fase 8) el rollback será una preocupación de infraestructura, no de código.

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

## 8.1 Entorno local

Todo el desarrollo ocurre en local. Piezas necesarias para levantar el proyecto:

| Pieza | Cómo se arranca | Dónde |
|---|---|---|
| Backend Laravel | `cd app/Server && php artisan serve --host=127.0.0.1 --port=8000` | http://127.0.0.1:8000 |
| MySQL (MariaDB 10.4) | XAMPP Control Panel | BD `fefuart`, usuario `root` sin contraseña |
| **Mailpit** | `C:\xampp\mailpit\mailpit.exe --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025` | Bandeja en http://127.0.0.1:8025 |
| SPA React | `cd app/Client/spa && npm run dev` | http://localhost:5173 |
| **Worker de la cola** | `cd app/Server && php artisan queue:work --tries=3` | sin puerto; hace falta desde la Fase 6 |
| Apache | XAMPP Control Panel | solo sirve el frontend legacy; **no** sirve el backend (SEC-002) |

**Mailpit** (v1.30.7) captura todo el correo saliente sin enviarlo a ninguna parte: hace falta desde la Fase 1 para probar la recuperación de contraseña y la verificación de email. El binario se descargó de las releases oficiales de `axllent/mailpit` y su SHA-256 se verificó contra el digest publicado por la API de GitHub. Vive fuera del repositorio, en `C:\xampp\mailpit`.

La configuración de correo (`MAIL_MAILER=smtp`, puerto 1025) está en `.env` y replicada en `.env.example`.

---

## 9. Fases

Prioridad: **P0** crítico · **P1** importante · **P2** mejora · **P3** opcional.

### Fase 0 — Contención y preparación · P0 · ✅ completada

| # | Tarea | Estado |
|---|---|---|
| 0.1 | Impedir que Apache sirva el árbol del proyecto (SEC-002) | ✅ `2fda29c` |
| 0.2 | Rotar `APP_KEY` y `JWT_SECRET` | ✅ 2026-08-11 |
| 0.3 | No devolver la excepción de login al cliente (SEC-012) | ✅ `b0eac92` |
| 0.4 | Sincronizar `develop` con `master` | ✅ fast-forward |
| 0.5 | Backup de la BD local y limpieza de la deriva (DB-001) | ✅ 2026-08-11 |
| 0.6 | Etiquetar `autotest` como archivo | ✅ `archive/v2-autonomous-attempt` |
| 0.7 | Crear `docs/AUDIT.md` y `docs/V2-ROADMAP.md` | ✅ este documento |

**Fase 0 completada.** El backup del esquema previo (15 tablas) está fuera del repositorio, en el scratchpad de la sesión. La recreación del esquema limpio se hace en la Fase 2, cuando existan las migraciones de v2.

### Fase 1 — Núcleo del backend · P0 · ✅ completada (2026-08-12)

| Entregado | Estado |
|---|---|
| Sanctum en modo SPA; retirada de `jwt-auth`, `config/jwt.php` y `JWT_SECRET` | ✅ `2b67f1e` |
| CORS con orígenes explícitos y `supports_credentials` | ✅ cierra **SEC-013** |
| `EnsureIsAdmin` (sustituye a `IsAdmin`); `auth:sanctum` sustituye a `IsUserAuth` | ✅ cierra **ARCH-002** |
| Registro sin rol asignable + throttle por ruta y por email+IP | ✅ cierra **SEC-001**, **SEC-007** |
| Sesión por cookie HttpOnly, revocable | ✅ cierra **SEC-011** |
| Form Requests, API Resources, formato de error JSON único | ✅ cierra **ARCH-005**, parte de **ARCH-001** |
| Recuperación de contraseña, verificación de email, perfil (N19) | ✅ `a1a21f0` |
| 26 tests, con regresión explícita de SEC-001 y SEC-007 | ✅ |

**Movido a la Fase 2: las Policies** (SEC-003, SEC-004, SEC-008, SEC-009). Protegen `Order`, `OrderItem`, `Event` y `MediaAsset`, y los cuatro modelos se rehacen enteros en la Fase 2 (catálogo separado de línea de pedido, envío a nivel de pedido, snapshots de precio). Escribirlas antes significaría escribirlas dos veces. **Ningún endpoint de esos recursos existe hoy**, así que los hallazgos no son explotables mientras tanto — pero siguen abiertos y se cierran ahí.

> Mailpit hace falta desde esta fase, no desde la 6: sin él no se prueban ni la recuperación de contraseña ni la verificación de email.

### Fase 2 — Catálogo, precios de servidor y Policies · P0 · ✅ completada (2026-08-13)

| Entregado | Estado |
|---|---|
| Tabla `roles` (1 = cliente, 2 = admin) y `UserRole` respaldado por entero | ✅ `3e1f292` (D23) |
| Catálogo: `products`, `product_variants`, `shipping_methods`, pivot | ✅ `4ac9ae3` cierra **DB-002** |
| Pedido, línea de encargo y `media_assets`, con snapshot de precio | ✅ `3e14d0a` cierra **DB-004**, **DB-005**, **ARCH-004** |
| `PricingService` con N4, N5, N6 y N7, en céntimos enteros | ✅ `3669964` |
| Seeder del catálogo real y endpoints públicos | ✅ `d47cf29` cierra **BUG-001**, **BUG-007**, **BUG-008** |
| Subida de imágenes re-encodificadas y `MediaAssetPolicy` | ✅ `4d59836` cierra **SEC-014** |
| Carrito con precios de servidor (D24) | ✅ `d797fcb` cierra **SEC-006**, **BUG-005** |
| Consulta y cancelación de pedidos con `OrderPolicy` (D24) | ✅ `2501b84` cierra **SEC-003**, **SEC-008**, **SEC-009**, **BUG-003**, **BUG-004** |
| CRUD de catálogo para la administradora | ✅ `d03f156` (D5) cierra **BUG-006** |
| Esquema base de eventos y `EventPolicy` | ✅ `0915390` (D27) |
| Retirada del código de v1 | ✅ `7d9e795` |
| 151 tests, con regresión de cada hallazgo cerrado | ✅ |

**Índices de DB-003** repartidos entre `4ac9ae3`, `3e14d0a` y `0915390`, con test que los verifica contra el esquema real.

**Lo que no cierra y por qué:**

- **SEC-010** queda *desarmado*, no cerrado: `status` sale de `$fillable` y `EventPolicy` separa `quote` y `confirm` como abilities de la administradora, pero los endpoints de eventos son de la Fase 4 (D27), así que la regresión es todavía un test de Policy y no un IDOR sobre HTTP.
- **BUG-002** necesita el endpoint de edición de evento: Fase 4.
- **DB-006** queda garantizado también en base de datos, y no solo en `CartService`. Se dijo que exigía el mismo índice sobre columna generada que N16 y que iría con él en la Fase 5; resultó ser más fácil que eso —`cart_slot` es un `CASE` sin `ELSE` sobre un entero, sin concatenar nada, así que ni siquiera hay que distinguir motores— y no había razón para dejar el carrito sin proteger mientras tanto.
- **`POST /api/cart/checkout`** no entra aquí. Sin pasarela, un checkout sería mover el carrito a `pending_payment` y habría que reescribirlo entero al añadir el PaymentIntent. Va con la Fase 5.
- Los **nombres del catálogo van sin tildes**, como el resto del árbol. Son editables desde el backoffice y son los primeros candidatos a corregirse ahí.

*Dependía de: Fase 1.*

### Fase 3 — Base de la SPA · P0 · ✅ completada (2026-08-13)

| Entregado | Estado |
|---|---|
| `app/Client/spa/.htaccess` — Apache no sirve la SPA | ✅ `a012e20` amplía **SEC-002** |
| Vite 6 + React 19 + TypeScript strict, proxy `/api`, Vitest | ✅ `362c9e6` cierra **SEC-005** |
| Tema Tailwind 4 con la paleta y la tipografía de v1 | ✅ `d89ced7` (D9) |
| Cliente axios con cookie de sesión, CSRF y TanStack Query | ✅ `831c7fb` (D2, D12) |
| `AuthProvider` y rutas protegidas por sesión y por rol | ✅ `17c764b` |
| Layout, cabecera y pie con la identidad de v1 | ✅ `322a030` |
| Login y registro | ✅ `4ff4b54` |
| Recuperación, verificación de email y perfil (N19) | ✅ `5e62db1` |
| 56 tests de SPA (207 en total con el backend) | ✅ |

**Sobre el contraste.** v1 escribía en blanco sobre el rosa de la marca: 1,75:1 donde la AA pide 4,5:1. Se conservan los cuatro colores y cambia el reparto — sobre el rosa se escribe en verde (5,26:1) o en piedra (4,52:1). Las parejas válidas se declaran en el propio CSS y un test calcula el contraste real desde ahí, así que reintroducir el blanco rompe la suite.

**Ampliación de alcance sobre lo escrito:** entraron también las pantallas de recuperación, verificación y perfil. El motivo es concreto: el correo que ya enviaba la Fase 1 apuntaba a `{FRONTEND_URL}/restablecer-contrasena`, una ruta que no existía, así que ese flujo estaba roto de punta a punta. Probado entero con Mailpit.

*Dependía de: Fase 1.*

### Fase 4 — Flujos funcionales · P1 · ✅ completada (2026-08-13)

Es la fase más grande, así que fue por tandas.

| Tanda | Contenido | Estado |
|---|---|---|
| **4a** | Localización del backend (P5) · endpoints de eventos | ✅ `b0c24d9`, `34b5b47` — cierra **SEC-010** y **BUG-002** |
| **4b** | `POST /api/cart/checkout` · `/api/admin/orders` y `/api/admin/events` con sus transiciones | ✅ `bcddc41`, `43003d3`, `2dc0bc3` — cierra **PERF-001**, **PERF-004** |
| **4c** | Catálogo y ficha de producto en la SPA | ✅ `8c21a18` |
| **4d** | Formulario de encargo a medida y carrito en la SPA · comando de limpieza de ficheros huérfanos | ✅ `deb5480`, `6a0c87d`, `7f3fd92`, `c39e664` |
| **4e** | Checkout, mis pedidos y solicitud de LiveArt en la SPA | ✅ `88a959a`, `3c3db3c`, `755df9a` |
| **4f** | Backoffice: pedidos, eventos y catálogo · buscador único y rango de fechas | ✅ `296170e`, `4593245` |

**El checkout entra aquí, corrigiendo lo que se escribió al cerrar la Fase 2.** Allí se dijo que sin pasarela habría que reescribirlo entero al añadir el PaymentIntent, y no es cierto: `CheckoutService` valida el carrito, captura la dirección, congela los importes y pasa `cart → pending_payment`. La Fase 5 **inserta** el pago entre `pending_payment` y `paid`; no reescribe lo anterior. Sin checkout, además, ni «mis pedidos» ni el backoffice tienen nada real que mostrar.

**Alcance del backoffice:** lo que ya hacía v1, bien hecho — listar pedidos y eventos por estado, buscar pedidos por email, cambiar estados y ver la foto de referencia de cada línea. Con paginación, Policies y sin los N+1 de PERF-001. Las métricas de `GET /api/admin/metrics` se dejan fuera: sin saber qué números mira Felicitas de verdad, es fácil construir el panel equivocado.

**Lo que el backoffice de eventos todavía no podía hacer al cerrar esta fase:** presupuestar y confirmar. El endpoint de estado cortaba ahí explícitamente en vez de permitir un `quoted` sin importe, que sería un estado a medias y dejaría al cliente sin poder aceptar nada. *Resuelto en la Fase 5:* presupuestar tiene ruta propia —no pasa por `/status`, porque no es cambiar un estado sino fijar un importe, calcular la señal y arrancar un plazo— y confirmar no está en el backoffice en absoluto: lo hace el webhook cuando la señal se cobra.

**El buscador del backoffice** (D28) es una sola caja que mira número de pedido, nombre y email de la cuenta, nombre del envío y teléfono. El nombre del envío entra porque puede no ser el de la cuenta —un regalo, o alguien que pide para otra persona—, y ese es justo el caso en el que hace falta buscar. Con rango de fechas sobre `placed_at` y `event_date`.

**Deuda consciente:** el comodín por delante (`%marta%`) impide usar índice, así que la búsqueda es un escaneo. Con el volumen de este negocio es irrelevante; si deja de serlo, la salida es un índice de texto completo, no reordenar la consulta.

**Cierra:** SEC-010 ✅, BUG-002 ✅, P5 ✅, PERF-001, PERF-002, PERF-004.
*Depende de: Fases 2 y 3.*

### Fase 5 — Pagos · P1 · ✅ completada (2026-08-18)

| Entregado | Estado |
|---|---|
| `StripePaymentService` — sesiones de Checkout hospedado | ✅ `f023e44` (D3, D7, D29) |
| `POST /api/orders/{id}/pay` — cuerpo vacío, importe del servidor | ✅ `f023e44` **SEC-006** |
| `POST /api/webhooks/stripe` — firma verificada e idempotencia | ✅ `f023e44` (D29) |
| Columnas de presupuesto y señal en `events` + tabla `settings` | ✅ `784b4fd` (D6, N15) |
| `POST /api/admin/events/{id}/quote` y `POST /api/events/{id}/accept-quote` | ✅ `784b4fd` (D6, N13) |
| `GET`/`PATCH /api/admin/settings` — porcentaje y validez configurables | ✅ `784b4fd` (N15) |
| Pantallas de pago, vuelta de la pasarela y ajustes en la SPA | ✅ `b0721fb` |
| Cancelación con devolución de señal según quién cancele | ✅ (N21, resuelve P2) |
| [PAGOS.md](PAGOS.md) — el recorrido explicado de punta a punta | ✅ |

**Lo que se llevó por delante mientras se construía:**

- Las tablas `payments` y `webhook_events` y el `confirmed_slot` de N16 ya estaban desde la Fase 2, junto con **DB-006**. La portabilidad a SQLite que D27 aplazaba resultó ser una sola diferencia entre motores.
- `POST /api/cart/checkout` tampoco entra aquí: se adelantó a la Fase 4. Esta fase **inserta** el cobro entre `pending_payment` y `paid` sin tocar `CheckoutService`, que es exactamente lo que se esperaba de aquella decisión.
- **Es la única fase en la que la prueba de humo se hizo contra el servicio real.** Una sesión de Checkout de verdad en modo test —55,00 € con el envío como `shipping_cost` y no como línea— confirmó tres cosas que un doble no puede: que `integration_identifier` existe en la versión de API fijada, que `shipping_options` funciona sin `shipping_address_collection`, y que omitir `payment_method_types` activa de verdad los métodos dinámicos (Stripe devolvió ocho). La sesión se caducó al terminar.
- Los tests del webhook calculan el **HMAC real** con un secreto conocido, así que ejercitan la verificación de firma de Stripe y no una imitación. Cubren firma inválida, otro secreto, sin cabecera, marca de tiempo vieja y cuerpo reserializado.
- `Tests\TestCase` instala un doble del cliente HTTP de Stripe en **todos** los tests: ninguna prueba puede salir a internet por descuido, ni siquiera una que no hable de pagos.

- **P2 resuelta → N21**, con la devolución automatizada. La cancelación y la devolución **no** van en la misma transacción: si Stripe no responde, la cancelación se queda hecha, porque lo contrario dejaría la fecha ocupada por un evento que ya nadie va a celebrar.
- De paso salió un test inestable que llevaba tiempo apareciendo una vez cada muchas ejecuciones y no era N+1: la sesión vive en base de datos y Laravel la recolecta **por sorteo** (`session.lottery = [2, 100]`), lo que añade un `DELETE` a una de cada cincuenta peticiones. Medido: 3 consultas sin recolección, 4 con ella. El test que cuenta consultas comparaba a veces 3 con 4. Se apaga el sorteo en `Tests\TestCase`, no en el test que lo sufrió, porque el problema es de cualquiera que mida.

**Pendiente:** entrega real de un webhook en local con `stripe listen` (necesita `stripe login`, que es interactivo).

*Dependía de: Fase 4.* Era la parte más delicada del proyecto.

### Fase 6 — Notificaciones · P1 · ✅ completada (2026-08-19)

| Entregado | Estado |
|---|---|
| Tabla `notifications` nativa de Laravel | ✅ `2d4104d` (D10) |
| `OrderService::cambiarEstado()` — la transición sale del controller | ✅ `6407811` |
| `QuoteReady`, `PaymentConfirmed`, `OrderStatusChanged`, `EventConfirmed` | ✅ `bbab7cc` (D10) |
| `NewLiveArtRequest` y `OrderPaid` — los dos avisos hacia la artista | ✅ `fd2ca2b` (D32) |
| `GET /api/notifications` y `PATCH /api/notifications/{id}/read` | ✅ `698ca83` |
| Página `/avisos` y enlace con contador en la cabecera | ✅ `b1fb611` |
| `QuoteRejected`, `EventCancelled` y `SlotFreed` — los silencios que quedaban | ✅ `b3b10da` (D32) |
| 50 tests nuevos: 42 de backend y 8 de SPA (390 y 147 en total) | ✅ |

**Dónde se dispara cada aviso, y por qué ahí.** Es la decisión de la fase y está en **D31**: cada notificación se encola **dentro del mismo `if` que hace el cambio de estado**. `StripeWebhookService` tiene tres guardas encadenadas y solo dos sirven de gancho — `registrar()` no basta, porque por D30 una entrega puede fallar después de guardar el cobro y entonces el evento se reentrega con `processed_at` a `null` y `despachar()` se ejecuta entero otra vez. Lo único que impide el segundo correo es que el cobro ya conste como `succeeded`.

**La señal de un evento manda un solo aviso.** `EventConfirmed` lleva el importe dentro; no sale además `PaymentConfirmed`. Para el cliente, aceptar el presupuesto, pagar y reservar fueron un solo gesto. Por lo mismo, el paso a `paid` del webhook no manda además `OrderStatusChanged`: un hecho, un aviso. El riesgo asumido es que en la colisión de N16 el cliente pagó y no recibe nada — pero ese caso ya exige intervención manual y devolución.

**Lo que se descubrió al construirlo:**

- **El `after_commit` de la cola no hacía falta y no se puso.** El diseño lo daba por necesario para que un aviso encolado dentro de una transacción no sobreviviera al rollback. Al escribir el test se vio que en el caso real —la colisión de N16— el `UPDATE` revienta contra el índice único **antes** de llegar a ninguna línea de aviso, esté dentro o fuera de la transacción. La garantía la da la posición: el aviso va después del cambio de estado. Un flag de configuración que ningún test puede hacer fallar no entra.
- **Laravel encola un job por canal, no por notificación.** Un aviso con `mail` + `database` son dos filas en `jobs`. Se midió. Elimina de raíz el riesgo que el diseño anotaba —que un fallo de SMTP al reintentar escribiera una segunda fila en `notifications`—: los canales no comparten job.
- **Los avisos de evento apuntaban a `/live-art/{id}`, que no es una ruta de la SPA.** No existe detalle por evento: `/live-art` es una sola página con la lista en el ancla `#mias`. Corregido antes de cerrar.
- **`APP_NAME` seguía siendo `Laravel`**, así que cada correo iba firmado con el nombre de la plantilla. Se notaba poco con un solo correo; con seis, no.
- **La prueba a mano incluyó entregar el mismo webhook firmado dos veces** contra la base local: dos entregas, un correo al cliente y uno a la artista, cuatro jobs (dos avisos × dos canales) y ningún duplicado.

**Dos silencios que se cerraron al repasar la fase.** El lado de pedidos avisaba de cada transición que hace la artista, vía `OrderService`; el de eventos solo del presupuesto y de la confirmación. Faltaban:

- **`* → cancelled`** — y este era grave. N21 devuelve la señal **por código**, así que se cancelaba un evento confirmado y al cliente le aparecían 360 € en la tarjeta sin ninguna explicación. Lo cuenta `EventCancelled`, diciendo si la señal vuelve o no según quién canceló.
- **`requested → rejected`** — N13 dice que no hay tarifas publicadas que consultar, así que un rechazo mudo deja al cliente esperando indefinidamente. Lo cuenta `QuoteRejected`, sin inventar un motivo que el sistema no conoce.
- Y el aviso en sentido contrario, `SlotFreed`: si cancela el cliente, la franja que N16 tenía bloqueada vuelve a la agenda, y hasta que la artista no se entera no puede ofrecerla. `confirmed → completed` sigue sin avisar a propósito: el cliente estuvo en su propio evento.

Con eso salió de los controllers **la última transición escrita a mano**: `QuoteService::cambiarEstado()` para rechazar y completar, y `OrderController::cancel` pasa por `OrderService` con `avisar: false` — es la única transición de pedido que no manda correo, porque el cliente acaba de pulsar el botón.

**Deuda consciente:** no hay «marcar todos como leídos». Con el volumen de este negocio, la lista no crece lo bastante para que haga falta; si deja de ser cierto, es un endpoint más.

*Dependía de: Fase 5.*

### Fase 7 — Testing, hardening y retirada del legacy · P1 · ✅ completada (2026-08-19)

| Entregado | Estado |
|---|---|
| Entorno de E2E con Playwright, base `fefuart_e2e` aparte | ✅ `af13b5e` |
| E2E de los 4 flujos — 14 recorridos, todos offline | ✅ `b478412`, `883baa2`, `ef08234` |
| **CSP** en la SPA, comprobada en un navegador | ✅ `69221aa` — cierra el último hallazgo abierto |
| Retirada del frontend legacy | ✅ `c59d024` (D15) |
| `composer audit` y `npm audit` | ✅ los dos limpios |
| Cinco fallos reales encontrados por los E2E, con su regresión | ✅ |
| Repaso de seguridad sobre el código nuevo | ✅ sin hallazgos |
| 400 tests de backend · 147 de SPA · 14 recorridos E2E | ✅ |

**Los E2E no son una red de seguridad: son un hallador de fallos.** Encontraron cinco cosas rotas que seis fases de tests unitarios no vieron, y todas por el mismo motivo — nadie había recorrido el camino entero:

| Fallo | Vivo desde |
|---|---|
| Cerrar sesión dejaba la cabecera con la sesión anterior. `queryClient.clear()` deja huérfano el observador vivo del `AuthProvider`: el servidor sí cerraba la sesión, mentía la pantalla | Fase 3 |
| **El registro nunca enviaba el correo de verificación.** N19 lo exige; solo se mandaba al reenviarlo a mano o al cambiar de correo | Fase 1 |
| **El enlace de verificación daba 500 al abrirlo desde el correo.** La ruta se declaró sin `auth` a propósito, pero el controlador pedía un `EmailVerificationRequest`, que exige sesión. No tenía ni un test | Fase 1 |
| El checkout seguía prometiendo que «nos ponemos en contacto contigo para el pago» | Fase 5 |
| El correo escribía `1.200,00 €` y la pantalla `1200,00 €`. La pantalla tenía razón: en castellano cuatro cifras no llevan separador | Fase 6 |

**Decisiones de los E2E:**

- **No se pulsa «Pagar» en ningún recorrido.** Hacerlo haría que el servidor llamase a la API de Stripe, y una batería que depende de la red falla por motivos que no son el código. Se llega hasta el pedido en `pending_payment` con su botón, que es donde acaba lo que es nuestro. La vuelta de la pasarela sí se prueba entera: se entrega un webhook **firmado en el propio test** con el secreto de `.env.e2e`, y se comprueba que la pantalla cambia sola. También se demuestra D29 por el lado contrario — entrar a mano en la URL de vuelta con una sesión inventada no da nada por pagado.
- **Base `fefuart_e2e` aparte**, resembrada antes de cada tanda. `APP_ENV=e2e` es lo único que la separa de `fefuart`: sin esa variable, `migrate:fresh` vaciaría la base de desarrollo.
- **`.env.e2e` va versionado** porque no lleva ninguna clave real. Si algún día hiciera falta una, deja de poder estarlo.

**Sobre la CSP.** Va en un `meta` del `index.html` y no en el `.htaccess` de la SPA, porque ese fichero dice `Require all denied`: Apache no sirve nada de ahí. `script-src 'self'` sin `unsafe-inline` ni `unsafe-eval` — con Checkout hospedado la SPA no carga `Stripe.js`. La única concesión es `style-src 'unsafe-inline'`, que la pide Tailwind 4. **`frame-ancestors` no entra**: en un `meta` se ignora, así que lo sirve el VirtualHost de la Fase 8.

**Repaso de seguridad: sin hallazgos** en el código nuevo. Lo dudoso se verificó contra el framework en vez de suponerlo — la inyección de HTML en los correos de aviso, que es la superficie que abre la Fase 6, la corta Laravel con `'html_input' => 'escape'` y `'allow_unsafe_links' => false` en su renderizador de Markdown. Los endpoints de avisos resuelven por la relación del usuario y responden 404 ante un id ajeno; el `enlace` que viaja en cada aviso lo genera el servidor desde plantillas fijas, así que no es un campo libre que pueda convertirse en redirect abierto.

**Dos cosas que quedan dichas y no son fallos, sino decisiones:**

- **`.env.e2e` va versionado y lleva un `APP_KEY`.** Es deliberado: los recorridos son offline y no hay ninguna clave real dentro. Pero esa clave está en el histórico del repositorio y **no debe reutilizarse en ningún otro entorno**. Si algún día ese fichero necesitara una credencial de verdad, deja de poder estar versionado.
- **`style-src 'unsafe-inline'`** es la única concesión de la CSP, y la pide Tailwind 4 al inyectar sus estilos en tiempo de ejecución. Son estilos y no ejecución de código; es lo que habría que quitar si algún día se precompilan.

*Depende de: Fase 6.*

### Entrega digital · ✅ completada (2026-08-19)

No es una fase: es la pieza que **D20 y N11 daban por hecha** y que ninguna fase llegó a construir. Se destapó al repasar qué quedaba tras la Fase 7, y era el único hueco con consecuencia real — la variante «Digital» se vende a 20 € con entrega digital, así que **se podía cobrar por algo que el sistema no sabía dar**.

| Entregado | Estado |
|---|---|
| `POST /api/admin/orders/{order}/items/{item}/delivery` — sube la artista | ✅ `112770e` |
| `GET /api/orders/{order}/items/{item}/download` — descarga el cliente | ✅ `112770e` |
| `DigitalDeliveryReady` — el décimo aviso | ✅ `112770e` |
| Subida en el backoffice y descarga en «mis pedidos» | ✅ `a22c996` |
| 13 tests de backend y 2 recorridos E2E | ✅ `e46a002` |

**El archivo no se re-encodifica, y es lo contrario de lo que hace SEC-014.** Allí el peligro es una foto que sube un desconocido, y la defensa es tirar todo lo que no sean píxeles. Aquí sube la artista y el fichero es el que el cliente va a imprimir: pasarlo por una recompresión a 2400 px lo destruiría, y además dejaría fuera el PDF. Lo que protege son otras tres capas: **lista blanca por contenido real** (`finfo`, no la extensión), **disco privado** fuera del alcance de Apache, y **descarga siempre como adjunto** — nunca en línea, para que un fichero raro no se ejecute en nuestro origen aunque hubiera colado.

**La Policy va contra el pedido, no contra la propiedad del fichero.** Lo intuitivo sería preguntarle a `MediaAssetPolicy`, como con las fotos de referencia, y sería incorrecto: el fichero lo subió la artista y por tanto es suyo, así que esa pregunta le negaría la descarga al único que tiene derecho a ella.

**Subir no mueve el estado.** Un pedido de tres láminas necesita tres subidas, y completarlo en la primera sería mentir; a `completed` lo pasa ella.

**Dos cosas que salieron al construirlo:**

- Un invitado que pedía una ruta de API **sin `Accept: application/json`** recibía un **500** en vez de un 401: `Authenticate` construye la excepción llamando a `route('login')`, y aquí no hay ninguna ruta `login`. Se veía poco porque la SPA siempre manda JSON, y empieza a importar con esta descarga, que es la primera ruta pensada para abrirse en el navegador. Corregido con `redirectGuestsTo(fn () => null)`.
- **Una URL de descarga pegada en la barra responde 401, no el fichero.** Una navegación de primer nivel no manda `Referer`, así que Sanctum no la trata como de sesión. Falla cerrado, que es lo que importa, y desde el enlace de la SPA funciona porque ahí sí hay `Referer`. Queda escrito en el E2E para que nadie lo lea como un fallo.

**Deuda consciente:** el límite son **40 MB**, que es lo que hoy permite `upload_max_filesize`. Para una lámina a 300 ppp se queda cerca; subirlo es tocar `php.ini`, y eso ya no es código.

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
