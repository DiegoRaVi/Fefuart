<?php

use App\Enums\OrderStatus;
use App\Models\MediaAsset;
use App\Models\Order;
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

/**
 * Un producto de catalogo listo para encargar. `$digital` decide si la
 * variante admite ademas entrega digital (N7).
 */
function producto(array $atributos = [], bool $admiteDigital = false): array
{
    $product = Product::factory()->create($atributos);
    $variant = ProductVariant::factory()->for($product)->create([
        'price' => '40.00',
        'additional_copy_price' => '10.00',
    ]);

    $variant->shippingMethods()->attach(
        $admiteDigital
            ? [test()->fisico->id, test()->digital->id]
            : [test()->fisico->id]
    );

    return [$product, $variant];
}

it('exige sesion para ver el carrito', function () {
    $this->getJson(route('cart.show'))->assertUnauthorized();
});

/**
 * Consultar el carrito no puede crear nada: un GET no tiene efectos, y con
 * creacion perezosa cualquier prefetch del navegador dejaria una fila por
 * visita. El carrito nace con la primera linea.
 */
it('devuelve un carrito vacio sin crear ninguno', function () {
    $this->actingAs($this->user)->getJson(route('cart.show'))
        ->assertOk()
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.total', '0.00');

    expect(Order::query()->count())->toBe(0);
});

/**
 * SEC-006 — la regresion que da sentido a toda la fase.
 *
 * En v1 el navegador calculaba el precio y `ProductController` lo aceptaba
 * como `'price' => 'required|numeric'`, asi que interceptar el POST y mandar
 * `price=0.01` bastaba para encargar un dibujo por un centimo. Aqui el
 * cuerpo trae los mismos campos envenenados y el servidor los ignora: el
 * precio sale del catalogo.
 */
it('ignora el precio que manda el cliente y cobra el del catalogo', function () {
    [$product, $variant] = producto();

    $respuesta = $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
            // Lo que mandaba v1, y algo mas por si acaso.
            'price' => '0.01',
            'unit_price' => '0.01',
            'line_total' => '0.00',
            'total' => '0.00',
            'shipping_total' => '0.00',
        ])
        ->assertCreated();

    expect($respuesta->json('data.items.0.unit_price'))->toBe('40.00')
        ->and($respuesta->json('data.items.0.line_total'))->toBe('40.00')
        ->and($respuesta->json('data.subtotal'))->toBe('40.00')
        ->and($respuesta->json('data.shipping_total'))->toBe('5.00')
        ->and($respuesta->json('data.total'))->toBe('45.00');
});

/**
 * N4 — la primera copia paga el trabajo; las siguientes, la impresion.
 * En v1 esto era `40 x cantidad`.
 */
it('cobra las copias adicionales solo a precio de impresion', function () {
    [$product, $variant] = producto();

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 3,
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.line_total', '60.00')
        ->assertJsonPath('data.total', '65.00');
});

/**
 * N5 — el envio se cobra una vez por pedido. En v1 tres articulos eran 15
 * EUR de envio.
 */
it('cobra un solo envio con varias lineas', function () {
    $this->actingAs($this->user);

    foreach (range(1, 3) as $i) {
        [$product, $variant] = producto();

        $this->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
        ])->assertCreated();
    }

    $this->getJson(route('cart.show'))
        ->assertOk()
        ->assertJsonPath('data.subtotal', '120.00')
        ->assertJsonPath('data.shipping_total', '5.00')
        ->assertJsonPath('data.total', '125.00');
});

/**
 * N7 — solo el estilo «Digital» admite entrega digital. En v1 era un `if`
 * dentro del `<script>`, asi que el servidor no lo comprobaba en absoluto.
 */
it('rechaza una entrega que la variante no admite', function () {
    [$product, $variant] = producto();

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->digital->id,
            'quantity' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('shipping_method_id');
});

/**
 * D26/N3 — la cantidad son copias de la misma lamina. En entrega digital es
 * el mismo fichero, asi que no hay copias que cobrar.
 */
it('no admite varias copias en entrega digital', function () {
    [$product, $variant] = producto(admiteDigital: true);

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->digital->id,
            'quantity' => 2,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('quantity');
});

it('no cobra envio si toda la linea es digital', function () {
    [$product, $variant] = producto(admiteDigital: true);

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->digital->id,
            'quantity' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.shipping_total', '0.00')
        ->assertJsonPath('data.total', '40.00');
});

/**
 * N9 — la foto no es un adjunto opcional: es el material de partida.
 */
it('rechaza el encargo sin foto si el producto la exige', function () {
    [$product, $variant] = producto(['requires_reference_image' => true]);

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reference_media_id');
});

it('acepta el encargo con su foto de partida', function () {
    [$product, $variant] = producto(['requires_reference_image' => true]);
    $foto = MediaAsset::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
            'reference_media_id' => $foto->id,
            'customer_notes' => 'En blanco y negro.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.customer_notes', 'En blanco y negro.')
        ->assertJsonPath('data.items.0.reference_media.id', $foto->id);
});

/**
 * SEC-004 / SEC-008 — el IDOR de la foto. Sin esta comprobacion, un
 * `reference_media_id` ajeno mete la foto de otra persona en el pedido
 * propio, que es exactamente lo que permitia v1 al aceptar un `order_id`
 * ajeno en `POST /products`.
 */
it('rechaza una foto de referencia de otro usuario', function () {
    [$product, $variant] = producto(['requires_reference_image' => true]);
    $ajena = MediaAsset::factory()->create();

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
            'reference_media_id' => $ajena->id,
        ])
        ->assertForbidden();

    expect(Order::query()->count())->toBe(0);
});

it('rechaza una cantidad por encima del maximo del producto', function () {
    [$product, $variant] = producto(['max_quantity' => 3]);

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 4,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('quantity');
});

it('rechaza una variante que no es de ese producto', function () {
    [$product] = producto();
    [, $otraVariante] = producto();

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $otraVariante->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('variant_id');
});

it('rechaza un producto desactivado', function () {
    [$product, $variant] = producto(['is_active' => false]);

    $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('product_id');
});

it('cambia la cantidad y recalcula el pedido', function () {
    [$product, $variant] = producto();

    $itemId = $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
        ])->json('data.items.0.id');

    $this->patchJson(route('cart.items.update', $itemId), ['quantity' => 3])
        ->assertOk()
        ->assertJsonPath('data.items.0.line_total', '60.00')
        ->assertJsonPath('data.total', '65.00');
});

it('quita una linea y recalcula el pedido', function () {
    [$product, $variant] = producto();

    $itemId = $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 2,
        ])->json('data.items.0.id');

    $this->deleteJson(route('cart.items.destroy', $itemId))
        ->assertOk()
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.subtotal', '0.00')
        // Sin lineas no hay envio que cobrar.
        ->assertJsonPath('data.shipping_total', '0.00')
        ->assertJsonPath('data.total', '0.00');
});

/**
 * SEC-003 / SEC-004 — el IDOR de la linea. En v1 `DELETE /products/{id}`
 * borraba la linea de cualquiera y `POST /products` con un `order_id` ajeno
 * inyectaba lineas en el pedido de otro.
 */
it('no deja tocar la linea del carrito de otro', function () {
    [$product, $variant] = producto();

    $itemId = $this->actingAs($this->user)
        ->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
        ])->json('data.items.0.id');

    $intruso = User::factory()->create();

    $this->actingAs($intruso)
        ->patchJson(route('cart.items.update', $itemId), ['quantity' => 9])
        ->assertForbidden();

    $this->actingAs($intruso)
        ->deleteJson(route('cart.items.destroy', $itemId))
        ->assertForbidden();
});

/**
 * DB-006 — v1 no impedia varias ordenes en estado `cart` por usuario y
 * `getCartOrder` hacia un `->first()`, asi que el carrito que veias dependia
 * del orden de insercion.
 */
it('mantiene un solo carrito por usuario', function () {
    $this->actingAs($this->user);

    foreach (range(1, 3) as $i) {
        [$product, $variant] = producto();

        $this->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shipping_method_id' => $this->fisico->id,
            'quantity' => 1,
        ])->assertCreated();
    }

    expect(Order::query()->where('user_id', $this->user->id)
        ->where('status', OrderStatus::Cart)->count())->toBe(1);
});

/**
 * El carrito no es un pedido todavia: no aparece en «mis pedidos» ni tiene
 * fecha de compra.
 */
it('deja el carrito sin fecha de compra', function () {
    [$product, $variant] = producto();

    $this->actingAs($this->user)->postJson(route('cart.items.store'), [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'shipping_method_id' => $this->fisico->id,
        'quantity' => 1,
    ])->assertCreated();

    $order = Order::query()->sole();

    expect($order->status)->toBe(OrderStatus::Cart)
        ->and($order->placed_at)->toBeNull();
});
