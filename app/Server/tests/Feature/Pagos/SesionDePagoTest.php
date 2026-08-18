<?php

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Tests\Support\StripeFalso;

/**
 * `POST /api/orders/{id}/pay` — abrir la sesion de pago.
 *
 * El cuerpo va **vacio**. En v1 pagar era un `PATCH /orders/{id}` que
 * aceptaba `total`, `address` y `status` del navegador, sin comprobar
 * propiedad ni rol (SEC-003, SEC-006). Aqui lo unico que llega es el id del
 * pedido en la URL; el importe sale del pedido y el pedido de la sesion.
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->fisico = ShippingMethod::factory()->physical()->create();
    $this->digital = ShippingMethod::factory()->digital()->create();
    $this->user = User::factory()->create();
});

/**
 * Un pedido ya encargado, listo para cobrar.
 */
function pedidoPorPagar(
    DeliveryType $entrega = DeliveryType::Physical,
    string $precio = '40.00',
    int $cantidad = 1,
): Order {
    $product = Product::factory()->create(['name' => 'Retrato']);
    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'A4',
        'price' => $precio,
        'additional_copy_price' => '10.00',
    ]);

    $fisico = $entrega === DeliveryType::Physical;
    $metodo = $fisico ? test()->fisico : test()->digital;
    $variant->shippingMethods()->attach($metodo->id);

    // N4 — la primera copia paga el trabajo; las siguientes, la impresion.
    $linea = number_format((float) $precio + 10 * ($cantidad - 1), 2, '.', '');
    $envio = $fisico ? '5.00' : '0.00';

    $order = Order::factory()->for(test()->user)->placed()->create([
        'subtotal' => $linea,
        'shipping_total' => $envio,
        'total' => number_format((float) $linea + (float) $envio, 2, '.', ''),
        'shipping_method_id' => $metodo->id,
    ]);

    OrderItem::factory()->for($order)->fromVariant($variant)->create([
        'delivery_type' => $entrega,
        'quantity' => $cantidad,
        'line_total' => $linea,
    ]);

    return $order;
}

it('abre la sesion y devuelve la URL de Stripe', function () {
    $order = pedidoPorPagar();

    $this->stripe->responde(StripeFalso::sesion([
        'id' => 'cs_test_abierta',
        'url' => 'https://checkout.stripe.com/c/pay/cs_test_abierta',
    ]));

    $respuesta = $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay");

    $respuesta->assertOk()
        ->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/cs_test_abierta');

    $pago = Payment::query()->sole();

    expect($pago->payable->is($order))->toBeTrue()
        ->and($pago->status)->toBe(PaymentStatus::Pending)
        ->and($pago->provider_session_id)->toBe('cs_test_abierta')
        ->and((string) $pago->amount)->toBe('45.00')
        // El pedido no se mueve al abrir la sesion: eso lo hara el webhook.
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

/**
 * SEC-006 — el importe que se le pide a Stripe sale del pedido. Da igual lo
 * que mande el cliente en el cuerpo.
 */
it('ignora cualquier importe que llegue en el cuerpo', function () {
    $order = pedidoPorPagar();

    $this->stripe->responde(StripeFalso::sesion());

    $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay", [
        'total' => '0.01',
        'amount' => 1,
        'currency' => 'usd',
    ])->assertOk();

    $enviado = $this->stripe->ultima()['params'];

    expect($enviado['line_items'][0]['price_data']['unit_amount'])->toBe(4000)
        ->and($enviado['line_items'][0]['price_data']['currency'])->toBe('eur')
        ->and((string) Payment::query()->sole()->amount)->toBe('45.00');
});

/**
 * N4 — la linea no es precio unitario por cantidad. Si se le mandara a Stripe
 * `quantity: 3`, cobraria 120 EUR donde el pedido dice 60.
 */
it('manda el total de la linea y no el unitario por la cantidad', function () {
    $order = pedidoPorPagar(cantidad: 3);

    // 40 de la primera copia + 10 + 10 de las otras dos, mas 5 de envio.
    expect((string) $order->total)->toBe('65.00');

    $this->stripe->responde(StripeFalso::sesion());

    $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertOk();

    $linea = $this->stripe->ultima()['params']['line_items'][0];

    expect($linea['quantity'])->toBe(1)
        ->and($linea['price_data']['unit_amount'])->toBe(6000)
        ->and($linea['price_data']['product_data']['description'])->toContain('3 copias');
});

/** N5 — el envio se cobra una vez por pedido, no por linea. */
it('manda el envio una sola vez y como tarifa de envio', function () {
    $order = pedidoPorPagar();
    OrderItem::factory()->for($order)->create(['line_total' => '40.00']);

    $this->stripe->responde(StripeFalso::sesion());

    $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertOk();

    $enviado = $this->stripe->ultima()['params'];

    expect($enviado['line_items'])->toHaveCount(2)
        ->and($enviado['shipping_options'])->toHaveCount(1)
        ->and($enviado['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'])->toBe(500);
});

/** N6 — un pedido digital no lleva envio, asi que no se manda tarifa alguna. */
it('no manda tarifa de envio en un pedido digital', function () {
    $order = pedidoPorPagar(DeliveryType::Digital);

    $this->stripe->responde(StripeFalso::sesion());

    $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertOk();

    expect($this->stripe->ultima()['params'])->not->toHaveKey('shipping_options');
});

/**
 * Omitir `payment_method_types` es lo que deja a Stripe ofrecer los metodos
 * configurados en el panel y los que encajen con cada cliente. Fijarlo en
 * codigo obligaria a tocar el servidor para aceptar un metodo nuevo.
 */
it('no fija los metodos de pago', function () {
    $order = pedidoPorPagar();

    $this->stripe->responde(StripeFalso::sesion());

    $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertOk();

    expect($this->stripe->ultima()['params'])->not->toHaveKey('payment_method_types');
});

it('manda la clave de idempotencia a Stripe', function () {
    $order = pedidoPorPagar();

    $this->stripe->responde(StripeFalso::sesion());

    $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertOk();

    $clave = Payment::query()->sole()->idempotency_key;

    expect($this->stripe->cabecerasDe()['idempotency-key'] ?? null)->toBe($clave)
        ->and($clave)->toContain((string) $order->id);
});

/**
 * Pulsar «Pagar» dos veces no puede dejar dos sesiones vivas por el mismo
 * pedido.
 */
it('reutiliza la sesion abierta en vez de crear otra', function () {
    $order = pedidoPorPagar();

    $this->stripe
        ->responde(StripeFalso::sesion(['id' => 'cs_test_1', 'amount_total' => 4500]))
        // La segunda peticion es el `retrieve` de la sesion que ya existe.
        ->responde(StripeFalso::sesion([
            'id' => 'cs_test_1',
            'status' => 'open',
            'amount_total' => 4500,
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_1',
        ]));

    $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertOk();
    $segunda = $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay");

    $segunda->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/cs_test_1');

    expect(Payment::query()->count())->toBe(1)
        ->and($this->stripe->peticiones)->toHaveCount(2)
        ->and($this->stripe->peticiones[1]['metodo'])->toBe('get');
});

/**
 * D5 — la artista puede cambiar un precio desde el backoffice mientras el
 * cliente tiene la pestana abierta. La sesion vieja cobraria un precio que ya
 * no existe, asi que se cierra en Stripe y no solo en nuestra tabla.
 */
it('caduca la sesion vieja si el importe ha cambiado', function () {
    $order = pedidoPorPagar();

    $Payment = Payment::factory()->create([
        'payable_type' => Order::class,
        'payable_id' => $order->id,
        'provider_session_id' => 'cs_test_vieja',
        'amount' => '30.00',
        'status' => PaymentStatus::Pending,
    ]);

    $this->stripe
        // El `retrieve`: sigue abierta, pero por 30 EUR y el pedido son 45.
        ->responde(StripeFalso::sesion(['id' => 'cs_test_vieja', 'amount_total' => 3000]))
        // El `expire`.
        ->responde(StripeFalso::sesion(['id' => 'cs_test_vieja', 'status' => 'expired']))
        // La sesion nueva.
        ->responde(StripeFalso::sesion(['id' => 'cs_test_nueva', 'amount_total' => 4500]));

    $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertOk();

    expect($Payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($this->stripe->peticiones[1]['url'])->toContain('/expire')
        ->and(Payment::query()->where('provider_session_id', 'cs_test_nueva')->exists())->toBeTrue();
});

/**
 * El navegador puede volver de Stripe antes que el webhook. Abrir otra sesion
 * ahi seria el camino directo a cobrar dos veces.
 */
it('no abre una sesion nueva si la anterior ya se pago', function () {
    $order = pedidoPorPagar();

    Payment::factory()->create([
        'payable_type' => Order::class,
        'payable_id' => $order->id,
        'provider_session_id' => 'cs_test_pagada',
        'amount' => '45.00',
        'status' => PaymentStatus::Pending,
    ]);

    $this->stripe->responde(StripeFalso::sesion([
        'id' => 'cs_test_pagada',
        'status' => 'complete',
        'payment_status' => 'paid',
    ]));

    $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertStatus(409);

    expect(Payment::query()->count())->toBe(1)
        ->and($this->stripe->peticiones)->toHaveCount(1);
});

describe('quien puede pagar', function () {
    /** SEC-003/SEC-008 — el IDOR de v1, ahora por Policy. */
    it('no deja pagar el pedido de otro', function () {
        $order = pedidoPorPagar();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertForbidden();

        expect(Payment::query()->count())->toBe(0)
            ->and($this->stripe->peticiones)->toBeEmpty();
    });

    it('exige sesion', function () {
        $order = pedidoPorPagar();

        $this->postJson("/api/orders/{$order->id}/pay")->assertUnauthorized();
    });

    it('no deja pagar un carrito que aun no se ha encargado', function () {
        $order = Order::factory()->for($this->user)->create(['status' => OrderStatus::Cart]);

        $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertForbidden();
    });

    /** Pagar dos veces el mismo pedido no es un caso de uso. */
    it('no deja volver a pagar un pedido ya pagado', function () {
        $order = pedidoPorPagar();
        $order->status = OrderStatus::Paid;
        $order->save();

        $this->actingAs($this->user)->postJson("/api/orders/{$order->id}/pay")->assertForbidden();

        expect($this->stripe->peticiones)->toBeEmpty();
    });

    /** La administradora gestiona, no paga en nombre de nadie. */
    it('no deja a la administradora pagar el pedido de un cliente', function () {
        $order = pedidoPorPagar();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertForbidden();
    });
});
