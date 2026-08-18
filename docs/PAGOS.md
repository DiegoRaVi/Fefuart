# Cómo funciona la pasarela de pagos

> **Documento vivo.** Explica el recorrido completo de un cobro: qué hace la SPA, qué hace Laravel, qué hace Stripe y cómo viajan los datos entre ellos.
> **Decisiones que lo sustentan:** D3, D7, D29 y D30 en [V2-ROADMAP.md](V2-ROADMAP.md). Reglas de negocio N4, N5, N6, N15, N16 y N21.
> **Última actualización:** 2026-08-18.

---

## 1. La idea en una frase

El cliente paga en la página de Stripe, no en la nuestra, y **el pedido no se da por pagado hasta que Stripe nos lo cuenta por un canal firmado**.

Esa segunda mitad es la importante y la que más cuesta creerse. Cuando el cliente termina de pagar, el navegador vuelve a nuestra web. Sería cómodo pensar «ha vuelto, luego ha pagado», pero eso es falso: la dirección a la que vuelve es una URL corriente que cualquiera puede escribir a mano en la barra del navegador sin haber pagado un euro. Por eso la vuelta del navegador **no decide nada**. Quien decide es un aviso que Stripe manda por su cuenta a nuestro servidor, firmado criptográficamente.

---

## 2. Las tres piezas

| Pieza | Dónde vive | De qué se encarga |
|---|---|---|
| **La SPA** (React) | `app/Client/spa`, puerto 5173 | Enseñar el botón, mandar al cliente a Stripe y esperar. No calcula importes ni decide estados. |
| **Laravel** | `app/Server`, puerto 8000 | Calcular cuánto se cobra, abrir la sesión de pago y recibir el aviso firmado. Es el único que escribe en la base de datos. |
| **Stripe** | En su servidor | Enseñar el formulario de tarjeta, cobrar y avisarnos. Ningún dato de tarjeta pasa por nosotros. |

La elección se llama **Checkout hospedado** (D7): el formulario de tarjeta lo sirve Stripe en `checkout.stripe.com`. La alternativa —incrustar el formulario en nuestra web— nos obligaría a cumplir requisitos de seguridad muchísimo más estrictos, porque el número de tarjeta pasaría por nuestro dominio.

---

## 3. El recorrido, paso a paso

Se sigue un caso real: el pedido **#2**, «Letras infantiles», **40 € + 5 € de envío = 45,00 €**.

### Paso 0 — Antes de pagar ya hay un pedido

El pago no es el principio de nada. Antes de llegar aquí el cliente ya pasó por el carrito y confirmó (`POST /api/cart/checkout`), y eso dejó un pedido en estado `pending_payment` con los importes **congelados y calculados en el servidor**.

```
app/Server/app/Services/CheckoutService.php   →  carrito  →  pedido (pending_payment)
```

En la base de datos, el pedido #2 quedó así:

| campo | valor |
|---|---|
| `status` | `pending_payment` |
| `subtotal` | `40.00` |
| `shipping_total` | `5.00` |
| `total` | `45.00` |

Ese `45.00` es el número que se va a cobrar. **El navegador nunca lo envía**; sale de aquí.

### Paso 1 — El cliente pulsa «Pagar 45,00 €»

```
app/Client/spa/src/features/orders/pages/DetalleDePedido.tsx
```

El botón solo aparece si el pedido está en `pending_payment`. Al pulsarlo llama a un *hook*:

```
app/Client/spa/src/features/pagos/api.ts   →  usePagarPedido(id)
                                           →  abrirPagoDePedido(id)
```

Esa función hace **una petición con el cuerpo vacío**:

```
POST /api/orders/2/pay
(sin cuerpo)
```

Vacío a propósito. Lo único que viaja es el número del pedido, y va en la dirección. Ni importe, ni moneda, ni método de pago. En la versión antigua de la web, pagar era mandar `{"total": 45, "status": "paid"}` desde el navegador y el servidor se lo creía; era el fallo **SEC-006** y también el **SEC-003**.

La petición sale por el cliente HTTP compartido, que adjunta la cookie de sesión:

```
app/Client/spa/src/shared/api/client.ts   (baseURL '/api', withCredentials)
        ↓  el proxy de Vite reenvía /api a Laravel
app/Client/spa/vite.config.ts
```

### Paso 2 — Laravel abre la sesión de pago

```
app/Server/routes/api.php
    Route::post('{order}/pay', [PaymentController::class, 'store'])
        ↓
app/Server/app/Http/Controllers/Api/PaymentController.php
```

Lo primero que hace el controlador es preguntar si esta persona puede pagar **este** pedido:

```
app/Server/app/Policies/OrderPolicy.php   →  pay()
```

La regla es: solo el dueño, y solo si el pedido está en `pending_payment`. Ni la administradora paga en nombre de nadie, ni se puede pagar dos veces lo mismo. Si el pedido es de otra persona, la respuesta es `403` y no se llega a hablar con Stripe.

Si pasa el filtro, el trabajo real ocurre aquí:

```
app/Server/app/Services/StripePaymentService.php   →  cobrarPedido($order)
```

Ese método:

1. **Comprueba que no haya ya una sesión de pago abierta** (`reutilizar()`). Si el cliente pulsó «Pagar» dos veces, se le devuelve la sesión que ya existía en vez de crear otra. Dos sesiones vivas por el mismo pedido serían dos oportunidades de cobrar.
2. **Traduce el pedido a líneas para Stripe** (`lineasDe()`). Aquí hay un detalle que se puede hacer mal muy fácilmente: cada línea se manda con **cantidad 1 y el total de la línea ya calculado**, no con la cantidad real. El motivo es la regla **N4**: la primera copia de un dibujo cuesta 40 € porque paga el trabajo artístico, y las siguientes cuestan 10 € porque solo pagan la impresión. Si le mandáramos a Stripe «cantidad 3, precio 40», cobraría 120 € donde el pedido dice 60.
3. **Añade el envío aparte** (`envioDe()`), como tarifa de envío y no como un producto más, porque el envío se cobra **una vez por pedido** (N5). Si todo el pedido es digital, no se manda nada (N6).
4. **Convierte los importes a céntimos enteros** pidiéndoselo a `PricingService->toCents()`. Nunca se usan decimales de coma flotante: con ellos, `0.1 + 0.2` no da exactamente `0.3`, y en un pedido de varias líneas ese error acaba descuadrando el cobro.
5. **Llama a Stripe** y guarda el resultado (`guardar()`).

Lo que sale hacia Stripe es, en esencia:

```
POST https://api.stripe.com/v1/checkout/sessions

  mode                  payment
  line_items[0]         cantidad 1, 4000 céntimos, "Letras infantiles · Lamina ilustrada"
  shipping_options[0]   500 céntimos, "Envio a domicilio"
  customer_email        cliente@fefuart.test
  success_url           http://localhost:5173/pedidos/2/pago?sesion={CHECKOUT_SESSION_ID}
  cancel_url            http://localhost:5173/pedidos/2
  metadata              payable_type, payable_id, kind

  cabecera Idempotency-Key: App\Models\Order:2:full:1
```

Dos cosas que **no** van ahí y son deliberadas:

- **No se manda `payment_method_types`.** Omitirlo es lo que hace que Stripe ofrezca los métodos de pago configurados en el panel y los que encajen con cada cliente. En la prueba real aparecieron ocho: tarjeta, Bancontact, EPS, Klarna, Link, MB Way, Amazon Pay y Satispay. Fijarlos en el código obligaría a tocar el servidor para aceptar uno nuevo.
- **No se manda el importe total.** Stripe lo suma de las líneas y el envío. Si nuestra suma y la suya no coincidieran, se detectaría después (paso 4).

La `Idempotency-Key` merece una explicación. Es una etiqueta que dice «esta petición es esta y no otra». Si por lo que sea la misma petición llega dos veces —se cortó la red y se reintentó, el cliente pulsó dos veces—, Stripe devuelve **la sesión que ya creó** en vez de crear otra. La nuestra es determinista (`App\Models\Order:2:full:1`) y no un número al azar, justamente para que dos peticiones simultáneas calculen la misma etiqueta y choquen.

Stripe responde con una sesión, y se guarda una fila:

```
app/Server/app/Models/Payment.php   →  tabla `payments`
```

| campo | valor real |
|---|---|
| `payable_type` / `payable_id` | `App\Models\Order` / `2` |
| `provider_session_id` | `cs_test_a1PzKTopia4EjNxrOxziS6v6BCEbx…` |
| `provider_payment_intent_id` | `NULL` *(todavía no existe)* |
| `amount` / `currency` | `45.00` / `EUR` |
| `status` | `pending` |
| `kind` | `full` |
| `idempotency_key` | `App\Models\Order:2:full:1` |

Fíjate en que **el pedido sigue en `pending_payment`**. Abrir una sesión de pago no es cobrar.

La respuesta al navegador es corta:

```json
{ "url": "https://checkout.stripe.com/c/pay/cs_test_a1PzKT…", "payment_id": 2 }
```

Y la SPA se limita a obedecer:

```
app/Client/spa/src/features/pagos/api.ts   →  irALaPasarela()  →  window.location.assign(url)
```

Se abandona la SPA del todo. A partir de aquí el cliente está en el dominio de Stripe.

### Paso 3 — El cliente paga en Stripe

Aquí no hacemos nada. Stripe enseña el formulario, valida la tarjeta, cobra y decide. Nuestro servidor ni se entera todavía.

Cuando termina, Stripe manda el navegador a la `success_url` que le dimos.

### Paso 4 — Stripe nos avisa (esto es lo que importa)

Por su cuenta y por otro camino, Stripe hace una petición a nuestro servidor:

```
POST /api/webhooks/stripe
```

Es **la única ruta de toda la API sin sesión**. Stripe no tiene cuenta en Fefuart, así que no puede identificarse con una cookie. Lo que la protege es una **firma**: Stripe calcula un código a partir del contenido del mensaje y de un secreto que solo tenemos los dos, y lo manda en la cabecera `Stripe-Signature`.

```
app/Server/app/Http/Controllers/Api/StripeWebhookController.php
```

El controlador verifica esa firma **antes de mirar nada más**. Dos detalles:

- Se verifica sobre el **cuerpo crudo**, byte a byte (`$request->getContent()`). Si alguien decodificara el JSON y lo volviera a serializar antes de comprobar, cambiaría el orden de las claves y la firma dejaría de cuadrar.
- La firma incluye una **marca de tiempo**, y solo vale cinco minutos. Así, capturar un aviso legítimo y reenviarlo mañana tampoco sirve.

Si la firma no cuadra: `400` y no se guarda absolutamente nada. Sin esto, cualquiera con `curl` podría declarar pagado cualquier pedido.

Si cuadra, el trabajo pasa a:

```
app/Server/app/Services/StripeWebhookService.php   →  procesar()
```

**Primero se guarda el aviso tal cual llegó** (`registrar()`), en la tabla `webhook_events`. Existe por un motivo concreto: la garantía de Stripe es «al menos una vez». El mismo aviso puede llegar varias veces, y llega seguro si tardamos en contestar. El índice único sobre `provider_event_id` es lo que impide atender dos veces lo mismo.

**Después se decide qué hacer** (`despachar()`), según el tipo:

| Tipo de aviso | Qué hacemos |
|---|---|
| `checkout.session.completed` | Si `payment_status` **no** es `unpaid`, se entrega. |
| `checkout.session.async_payment_succeeded` | Se entrega. |
| `checkout.session.async_payment_failed` | El cobro se marca fallido. |
| `checkout.session.expired` | El cobro se marca cancelado. |
| `charge.refunded` | El cobro se marca devuelto. |
| cualquier otro | Se guarda y se da por atendido, para que no vuelva. |

Los dos primeros son un par, y separarlos es un error clásico. Una sesión puede «completarse» con el pago todavía en camino: pasa con transferencias y domiciliaciones, que tardan días. Si solo atendiéramos `completed`, entregaríamos encargos cuyo pago acabará fallando; si solo atendiéramos el asíncrono, no entregaríamos nunca los pagos con tarjeta. Por eso se atienden los dos, mirando `payment_status`.

Entregar (`cumplir()`) es esto, y en este orden:

1. Buscar el cobro por `provider_session_id`.
2. **Comprobar que lo cobrado es lo que guardamos** (`guardImporte()`). Que Stripe diga «han pagado» no basta: tienen que haber pagado 45,00 € y en euros. Si no cuadra, no se entrega nada.
3. Marcar el cobro como `succeeded`, anotar el `payment_intent` y la hora.
4. Mover el pedido a `paid` (`entregar()`).

Los pasos 3 y 4 **no van en la misma transacción**, y es deliberado (D30). Si al mover el estado saltara un error, meterlos juntos haría que se deshiciera también el «cobrado» — cuando el dinero ya se ha movido de verdad. Es preferible que quede constancia del cobro y que el fallo aparezca escrito en `webhook_events.error`.

En el pedido #2, esto es lo que quedó registrado:

```
payments
  status                      succeeded
  provider_payment_intent_id  pi_3U5p0V06xaYHYp2F1QiOqyWB
  paid_at                     2026-08-18 15:32:56

orders
  status                      paid

webhook_events   (5 avisos, todos atendidos, ninguno con error)
  charge.succeeded · payment_intent.succeeded · payment_intent.created
  checkout.session.completed  ← este es el que hizo el trabajo
  charge.updated
```

Los otros cuatro no hacen nada: se guardan, se marcan atendidos y no vuelven.

### Paso 5 — La pantalla de vuelta se entera

Mientras tanto, el navegador del cliente ha aterrizado en:

```
app/Client/spa/src/features/pagos/pages/VueltaDelPago.tsx
```

Esta pantalla **no da nada por pagado**. Lo único que hace es preguntarle al servidor cada dos segundos:

```
GET /api/orders/2     →  ¿en qué estado está?
```

Mientras la respuesta sea `pending_payment` enseña «Confirmando tu pago» con un indicador girando. En cuanto llega `paid`, cambia sola a «Pago recibido». A los 45 segundos deja de preguntar y dice que el cobro sigue en curso, en vez de insistir para siempre.

Y aquí está la demostración de que todo esto no era paranoia: en la dirección de vuelta viene un `?sesion=cs_test_…`, y **no se usa para nada**. No se manda al servidor ni se comprueba. Si abrieras esa misma URL a mano, con el identificador que se te ocurriera, la pantalla se quedaría girando hasta rendirse, porque el servidor diría `pending_payment`.

---

## 4. El recorrido en un dibujo

```
   NAVEGADOR                    LARAVEL                     STRIPE
       │                           │                           │
  1.   │──POST /orders/2/pay──────▶│                           │
       │      (cuerpo vacío)       │                           │
       │                           │ Policy: ¿es suyo?         │
       │                           │ Precio: 45,00 € (servidor)│
       │                           │──crear sesión────────────▶│
       │                           │◀──cs_test_… + url─────────│
       │                           │ guarda payments(pending)  │
       │◀──{ url }─────────────────│                           │
       │                           │                           │
  2.   │═══ el cliente se va a checkout.stripe.com ═══════════▶│
       │                           │                       (paga)
       │                           │                           │
  3.   │◀══ vuelve a /pedidos/2/pago ══════════════════════════│
       │                           │                           │
       │                           │◀══POST /webhooks/stripe═══│   ← canal aparte,
       │                           │    (firmado)              │     firmado
       │                           │ verifica firma            │
       │                           │ comprueba importe         │
       │                           │ payments → succeeded      │
       │                           │ orders   → paid           │
       │                           │───204────────────────────▶│
  4.   │──GET /orders/2───────────▶│                           │
       │◀──"paid"──────────────────│                           │
       │  «Pago recibido»          │                           │
```

Lo esencial del dibujo: **la flecha 3 de vuelta al navegador y la flecha del webhook son caminos distintos**. El estado del pedido lo cambia el segundo, nunca el primero.

---

## 5. Qué datos viajan y cuáles no

| Dato | ¿Sale del navegador? | ¿De dónde sale entonces? |
|---|---|---|
| Qué pedido se paga | **Sí**, en la URL | — |
| Importe | No | `orders.total`, calculado por `PricingService` |
| Moneda | No | Fijada en `StripePaymentService` |
| Método de pago | No | Lo decide Stripe según su configuración |
| Estado del pedido | No | Solo lo escribe el webhook |
| Número de tarjeta | No, **nunca pasa por nosotros** | El cliente lo teclea en Stripe |
| Correo del cliente | No | `users.email`, para que Stripe le mande el recibo |

Si mandas `{"total": "0.01"}` en el cuerpo de `POST /orders/2/pay`, se ignora por completo y se cobran los 45,00 €. Hay un test que lo comprueba, precisamente para que nadie lo rompa por descuido más adelante.

---

## 6. Qué pasa en cada caso

| Situación | Qué ocurre |
|---|---|
| **El cliente abandona la página de Stripe** | El pedido se queda en `pending_payment` y puede volver a intentarlo. Cuando la sesión caduca, Stripe manda `checkout.session.expired` y el cobro pasa a `cancelled`. |
| **Pulsa «Pagar» dos veces** | La segunda vez se le devuelve la misma sesión abierta. Solo hay una fila en `payments`. |
| **Tarjeta rechazada** | Stripe se lo dice en su página y no manda nada. El pedido no se mueve. |
| **Pago diferido que después falla** | Llega `async_payment_failed`, el cobro pasa a `failed` y el pedido se queda en `pending_payment`. |
| **Pago diferido que después se confirma** | Llega `async_payment_succeeded` días después y se entrega entonces. |
| **El precio cambió desde el backoffice mientras tenía la pestaña abierta** | Al volver a pedir el pago, la sesión antigua se caduca en Stripe y se abre otra con el importe nuevo. No se cobra un precio que ya no existe. |
| **El navegador vuelve antes que el webhook** | La pantalla de vuelta espera y cambia sola. Si el cliente vuelve a darle a pagar en ese hueco, recibe un `409` con «ya hemos recibido tu pago», no una segunda sesión. |
| **El webhook llega dos veces** | El índice único sobre `provider_event_id` hace que el segundo se descarte sin tocar nada. |
| **Lo cobrado no coincide con lo guardado** | No se entrega. Se responde `500`, el motivo queda en `webhook_events.error` y Stripe lo reintenta. |
| **Llega un aviso de una sesión que no conocemos** | Igual: no se entrega, queda el motivo escrito. Pasa si el mismo `stripe listen` sirve a dos máquinas. |
| **La firma no es válida** | `400` y no se guarda nada. No se distingue entre «firma mala» y «cuerpo ilegible», para no dar pistas. |
| **Falta el secreto del webhook** | `500` con un aviso en el log. Procesar sin verificar sería peor que no procesar. |

Cuando algo falla se responde `500` **a propósito**, no `200`. Un `200` le diría a Stripe «recibido, todo bien» y el aviso moriría ahí con un error que nadie ha visto. Con `500`, Stripe reintenta con esperas crecientes hasta tres días.

---

## 7. El caso de los eventos: la señal

Los eventos de Live Art no tienen precio de catálogo (N13): cada boda es distinta. El recorrido tiene dos pasos más al principio, pero **el cobro es exactamente el mismo mecanismo**.

```
1. El cliente pide presupuesto        POST /api/events
2. La artista presupuesta             POST /api/admin/events/{id}/quote   {quoted_amount}
3. El cliente acepta y paga la señal  POST /api/events/{id}/accept-quote
4. Stripe avisa                       POST /api/webhooks/stripe
```

En el paso 2, del cuerpo llega **solo el importe total**. La señal la calcula el servidor:

```
app/Server/app/Services/QuoteService.php   →  presupuestar()
        ↓
app/Server/app/Services/PricingService.php →  deposit()    (un % del presupuesto)
        ↓
app/Server/app/Services/SettingsService.php →  el % es configurable, 30 por defecto
```

Los dos importes se **guardan** en el evento, no se recalculan luego. Si mañana la artista cambia el porcentaje, este evento conserva la señal que se le dijo al cliente.

El paso 3 junta aceptar y pagar porque para el cliente son un solo gesto: aceptar es reservar y reservar es pagar. Aceptar deja el evento en `accepted`; a `confirmed` solo se llega con la señal cobrada, y eso lo hace el webhook.

Hay una comprobación extra que no existe en los pedidos: **N16**, dos eventos no pueden estar confirmados el mismo día y franja. Se comprueba dos veces a propósito:

- Al aceptar, para enterarse **antes** de cobrar.
- En la base de datos, con un índice único sobre una columna generada (`confirmed_slot`), que es la que no se puede saltar.

Si las dos se cruzan —dos clientes pagando la misma fecha a la vez—, el cobro queda guardado, el evento no avanza, y en `webhook_events.error` queda escrito qué pasó y qué número de cobro hay que devolver a mano.

---

## 8. Devoluciones

Ningún endpoint devuelve dinero por su cuenta. Se llega ahí como consecuencia declarada de cancelar, y la regla es **N21**:

| Quién cancela | Qué pasa con la señal |
|---|---|
| El cliente | **No se devuelve.** La fecha llevaba bloqueada para él desde que la pagó. |
| La artista | **Se devuelve entera.** El hueco lo libera ella. |

La devolución la hace el código y no la artista a mano en el panel, porque la regla es determinista y olvidarse de aplicarla deja al cliente sin su dinero. Lo que sí es explícito es el gesto: en el backoffice el botón dice «Cancelar y devolver la señal (360,00 €)» en vez de «Cancelado», y en la pantalla del cliente el aviso aparece **antes** de que pulse.

```
app/Server/app/Services/QuoteService.php        →  cancelar($event, $quien)
app/Server/app/Services/StripePaymentService.php →  devolver($payment, $motivo)
```

Cuando Stripe procesa la devolución manda `charge.refunded`, y el cobro queda como `refunded`. Eso vale también para las devoluciones hechas a mano desde el panel de Stripe: nuestra tabla nunca se queda diciendo «cobrado» cuando ya no lo está.

---

## 9. Las tres reglas que no se pueden romper

1. **El precio nunca llega del cliente.** Ni en el carrito, ni al pagar, ni al presupuestar un evento. Sale siempre de `PricingService`.
2. **El estado del pedido solo lo mueve el webhook.** Ninguna pantalla, ninguna URL de vuelta y ningún endpoint de cliente pasan un pedido a `paid`.
3. **Todo aviso se verifica antes de mirarlo.** Firma correcta o `400`, sin excepciones.

Cada una tiene tests que la reproducen, para que romperla haga fallar la batería en vez de pasar desapercibida:

```
app/Server/tests/Feature/Pagos/SesionDePagoTest.php     el cuerpo vacío, N4, N5, N6, permisos
app/Server/tests/Feature/Pagos/WebhookDeStripeTest.php  firma, idempotencia, importes
app/Server/tests/Feature/Pagos/SenalDeEventoTest.php    la señal confirma el evento
app/Server/tests/Feature/Events/PresupuestoTest.php     presupuestar y aceptar
app/Server/tests/Feature/Events/CancelacionTest.php     N21, devoluciones
app/Client/spa/src/features/pagos/pages/VueltaDelPago.test.tsx   la URL de vuelta no prueba nada
```

Los tests del webhook calculan la firma **de verdad**, con el mismo algoritmo que Stripe, así que ejercitan la verificación real y no una imitación. Y `Tests\TestCase` sustituye el cliente HTTP de Stripe en **todos** los tests, de modo que ninguna prueba puede salir a internet por descuido.

---

## 10. Cómo probarlo en local

Hacen falta cuatro cosas a la vez:

```bash
# 1 · Backend
cd app/Server && php artisan serve --host=127.0.0.1 --port=8000

# 2 · SPA (tiene que ser el 5173: lo exige SANCTUM_STATEFUL_DOMAINS)
cd app/Client/spa && npm run dev

# 3 · El túnel de Stripe, en una ventana que se queda abierta
stripe login                                                    # solo la primera vez
stripe listen --forward-to localhost:8000/api/webhooks/stripe
```

`stripe listen` imprime un secreto `whsec_…` que hay que poner en `STRIPE_WEBHOOK_SECRET` del `.env`. Sin él, cada aviso se estrella contra un `400`.

La cuarta cosa es pagar: entra con `cliente@fefuart.test` / `password`, encarga unas «Letras infantiles» (es el único producto que no pide foto de referencia) y paga con la tarjeta de prueba **`4242 4242 4242 4242`**, cualquier fecha futura y cualquier CVC.

Para ver el rastro por dentro:

```sql
SELECT id, status, total FROM orders;
SELECT provider_session_id, amount, status, kind, paid_at FROM payments;
SELECT type, processed_at, error FROM webhook_events ORDER BY id;
```

> **Aviso:** el CLI de Stripe no queda en el `PATH` al instalarlo con winget. La ruta completa es
> `%LOCALAPPDATA%\Microsoft\WinGet\Packages\Stripe.StripeCli_Microsoft.Winget.Source_8wekyb3d8bbwe\stripe.exe`.

---

## 11. Mapa de ficheros

**Navegador**

| Fichero | Papel |
|---|---|
| `features/orders/pages/DetalleDePedido.tsx` | El botón «Pagar». |
| `features/eventos/pages/LiveArt.tsx` | El presupuesto y «Aceptar y pagar la señal». |
| `features/pagos/api.ts` | Las dos llamadas y el salto a Stripe. |
| `features/pagos/pages/VueltaDelPago.tsx` | La espera al webhook. Sirve para pedidos y eventos. |
| `shared/api/client.ts` | Cliente HTTP con la cookie de sesión. |
| `app/App.tsx` | Rutas `/pedidos/:id/pago` y `/live-art/:id/pago`. |

**Servidor**

| Fichero | Papel |
|---|---|
| `routes/api.php` | Dónde vive cada endpoint. |
| `Http/Controllers/Api/PaymentController.php` | Recibe «quiero pagar». |
| `Http/Controllers/Api/StripeWebhookController.php` | Verifica la firma. |
| `Policies/OrderPolicy.php` | Quién puede pagar qué. |
| `Services/StripePaymentService.php` | Habla con Stripe. El único sitio que lo hace. |
| `Services/StripeWebhookService.php` | Convierte avisos en estado. El único que marca `paid`. |
| `Services/PricingService.php` | Decide cuánto cuesta. Incluye la señal. |
| `Services/QuoteService.php` | Presupuesto, aceptación, confirmación y cancelación de eventos. |
| `Services/SettingsService.php` | El % de señal y la validez del presupuesto. |
| `Models/Payment.php` | Un cobro. Sin nada asignable en masa. |
| `Models/WebhookEvent.php` | Un aviso recibido, tal cual llegó. |
| `Providers/AppServiceProvider.php` | Construye el cliente de Stripe con la clave y la versión de API. |
| `config/services.php` | De dónde salen las claves (del `.env`, nunca del código). |

---

## 12. Las claves

Tres, y viven en `app/Server/.env`, que **no está en el repositorio**:

| Clave | Quién la ve | Para qué |
|---|---|---|
| `STRIPE_KEY` (`pk_…`) | Puede ir al navegador | Identificar la cuenta. Es pública por diseño. |
| `STRIPE_SECRET` (`sk_…`) | Solo el servidor | Crear sesiones y devoluciones. |
| `STRIPE_WEBHOOK_SECRET` (`whsec_…`) | Solo el servidor | Verificar que un aviso viene de Stripe. |

Todo el desarrollo va en **modo test** (`pk_test_`, `sk_test_`), con tarjetas de prueba y sin contrato bancario (D7).

Para producción conviene cambiar `sk_` por una **clave restringida** (`rk_`) con permiso únicamente sobre sesiones de Checkout y devoluciones. Si se filtrara, lo que se podría hacer con ella es mucho menos.
