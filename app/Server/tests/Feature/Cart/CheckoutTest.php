<?php

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->fisico = ShippingMethod::factory()->physical()->create();
    $this->digital = ShippingMethod::factory()->digital()->create();
    $this->user = User::factory()->create();
});

function direccion(array $extra = []): array
{
    return array_merge([
        'shipping_name' => 'Marta Ruiz',
        'shipping_phone' => '600123456',
        'shipping_line1' => 'Calle Mayor 1',
        'shipping_city' => 'Toledo',
        'shipping_province' => 'Toledo',
        'shipping_postal_code' => '45001',
        'shipping_country' => 'ES',
    ], $extra);
}

/** Un carrito con una linea, del tipo de entrega que se pida. */
function carritoCon(DeliveryType $entrega = DeliveryType::Physical, string $precio = '40.00'): Order
{
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create([
        'price' => $precio,
        'additional_copy_price' => '10.00',
    ]);

    $metodo = $entrega === DeliveryType::Physical ? test()->fisico : test()->digital;
    $variant->shippingMethods()->attach($metodo->id);

    $order = Order::factory()->for(test()->user)->create([
        'status' => OrderStatus::Cart,
        'subtotal' => $precio,
        'shipping_total' => $entrega === DeliveryType::Physical ? '5.00' : '0.00',
        'total' => $entrega === DeliveryType::Physical ? '45.00' : $precio,
        'shipping_method_id' => $metodo->id,
    ]);

    OrderItem::factory()->for($order)->fromVariant($variant)->create([
        'delivery_type' => $entrega,
        'line_total' => $precio,
    ]);

    return $order;
}

it('exige sesion', function () {
    $this->postJson(route('cart.checkout'), direccion())->assertUnauthorized();
});

it('rechaza un carrito vacio', function () {
    $this->actingAs($this->user)
        ->postJson(route('cart.checkout'), direccion())
        ->assertStatus(422);

    expect(Order::query()->placed()->count())->toBe(0);
});

it('convierte el carrito en pedido', function () {
    $carrito = carritoCon();

    $this->actingAs($this->user)
        ->postJson(route('cart.checkout'), direccion())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.total', '45.00')
        ->assertJsonPath('data.shipping_address.city', 'Toledo');

    $pedido = $carrito->fresh();

    expect($pedido->status)->toBe(OrderStatus::PendingPayment)
        ->and($pedido->placed_at)->not->toBeNull();
});

it('deja el carrito vacio despues', function () {
    carritoCon();

    $this->actingAs($this->user)->postJson(route('cart.checkout'), direccion())->assertCreated();

    $this->getJson(route('cart.show'))
        ->assertOk()
        ->assertJsonPath('data.items', []);
});

it('hace que el pedido aparezca en mis pedidos', function () {
    carritoCon();

    $this->actingAs($this->user)->postJson(route('cart.checkout'), direccion())->assertCreated();

    $this->getJson(route('orders.index'))->assertOk()->assertJsonCount(1, 'data');
});

/**
 * N6 — un pedido con al menos una linea fisica hay que enviarlo a alguna
 * parte. En v1 la direccion era un string plano que se fijaba con un PATCH
 * suelto y nada obligaba a rellenarla.
 */
it('exige direccion si hay alguna linea fisica', function () {
    carritoCon(DeliveryType::Physical);

    $this->actingAs($this->user)
        ->postJson(route('cart.checkout'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_name', 'shipping_line1', 'shipping_city']);
});

it('no pide direccion si todo el pedido es digital', function () {
    carritoCon(DeliveryType::Digital, '20.00');

    $this->actingAs($this->user)
        ->postJson(route('cart.checkout'), [])
        ->assertCreated()
        ->assertJsonPath('data.total', '20.00')
        ->assertJsonPath('data.shipping_total', '0.00');
});

/**
 * SEC-006 — el ultimo sitio por el que el cliente podria colar un importe.
 * En v1 «Pagar» era un `PATCH /orders/{id} {status:'pending'}` que ademas
 * admitia `total`.
 */
it('ignora los importes que manda el cliente', function () {
    carritoCon();

    $this->actingAs($this->user)
        ->postJson(route('cart.checkout'), direccion([
            'total' => '0.00',
            'subtotal' => '0.00',
            'shipping_total' => '0.00',
            'status' => 'paid',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.total', '45.00')
        ->assertJsonPath('data.status', 'pending_payment');
});

/**
 * D5 tiene una consecuencia incomoda: Felicitas puede cambiar un precio
 * mientras alguien tiene el carrito abierto. Cobrar el nuevo en silencio no
 * vale, y cobrar el viejo tampoco.
 */
it('avisa si el precio ha cambiado desde que se lleno el carrito', function () {
    $carrito = carritoCon();
    $variante = $carrito->items()->sole()->variant;

    $variante->update(['price' => '55.00']);

    $this->actingAs($this->user)
        ->postJson(route('cart.checkout'), direccion())
        ->assertStatus(409)
        ->assertJsonPath('data.total', '60.00');

    // Y no se ha hecho el pedido: el cliente tiene que volver a confirmar.
    expect($carrito->fresh()->status)->toBe(OrderStatus::Cart);
});

it('deja hacer el pedido al reintentar con el precio nuevo ya visto', function () {
    $carrito = carritoCon();
    $carrito->items()->sole()->variant->update(['price' => '55.00']);

    $this->actingAs($this->user)->postJson(route('cart.checkout'), direccion())->assertStatus(409);

    $this->postJson(route('cart.checkout'), direccion())
        ->assertCreated()
        ->assertJsonPath('data.total', '60.00');
});

it('rechaza el pedido si un producto se ha retirado del catalogo', function () {
    $carrito = carritoCon();
    $carrito->items()->sole()->product->update(['is_active' => false]);

    $this->actingAs($this->user)
        ->postJson(route('cart.checkout'), direccion())
        ->assertStatus(422);

    expect($carrito->fresh()->status)->toBe(OrderStatus::Cart);
});

it('no deja hacer el pedido dos veces', function () {
    carritoCon();

    $this->actingAs($this->user)->postJson(route('cart.checkout'), direccion())->assertCreated();

    // Ya no queda carrito, asi que el segundo intento se queda sin nada.
    $this->postJson(route('cart.checkout'), direccion())->assertStatus(422);

    expect(Order::query()->placed()->count())->toBe(1);
});
