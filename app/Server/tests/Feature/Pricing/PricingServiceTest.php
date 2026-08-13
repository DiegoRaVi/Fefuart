<?php

use App\Enums\DeliveryType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\PricingService;

beforeEach(function () {
    $this->pricing = app(PricingService::class);
});

/**
 * N4 — precio de linea = `unit_price` + `additional_copy_price` x (q - 1).
 * La primera copia paga el trabajo artistico; las siguientes, la impresion.
 */
it('cobra el trabajo una vez y las copias siguientes solo la impresion', function (int $cantidad, string $esperado) {
    $variant = new ProductVariant(['price' => '40.00', 'additional_copy_price' => '10.00']);

    expect($this->pricing->priceLine($variant, $cantidad)->lineTotal)->toBe($esperado);
})->with([
    [1, '40.00'],
    [2, '50.00'],
    [3, '60.00'],
]);

/**
 * Las tres tarifas reales de v1, que vivian en el `<script>` de cada
 * formulario: 30 EUR diseno de moda, 40 EUR acuarela, 20 EUR digital.
 */
it('aplica la tarifa de cada variante', function (string $precio, string $esperado) {
    $variant = new ProductVariant(['price' => $precio, 'additional_copy_price' => '10.00']);

    expect($this->pricing->priceLine($variant, 1)->lineTotal)->toBe($esperado);
})->with([
    ['30.00', '30.00'],
    ['40.00', '40.00'],
    ['20.00', '20.00'],
]);

/**
 * La incoherencia de v1, que es el motivo por el que este calculo tenia que
 * salir del navegador.
 *
 * `ramo-flores.html:112-116` hacia `40 x cantidad` y luego `+5`; para dos
 * unidades daba 85 EUR. `letras-infantiles.html:127-132` hacia `+5` y luego
 * `x cantidad`, y daba 90 EUR. Mismo caso, dos precios.
 *
 * Con N4 y N5 el resultado es uno solo: 40 + 10 de la segunda copia, mas 5
 * de envio cobrados una unica vez.
 */
it('da el mismo precio para el ramo y para las letras', function () {
    ShippingMethod::factory()->physical()->create();

    $precios = collect(['ramo', 'letras'])->map(function () {
        $variant = ProductVariant::factory()->create([
            'price' => '40.00',
            'additional_copy_price' => '10.00',
        ]);

        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->fromVariant($variant)->create([
            'quantity' => 2,
            'line_total' => $this->pricing->priceLine($variant, 2)->lineTotal,
        ]);

        return $this->pricing->totalsFor($order->fresh())->total;
    });

    expect($precios->unique()->values()->all())->toBe(['55.00']);
});

/**
 * N5 — el envio se cobra una sola vez por pedido. En v1 se sumaba por linea:
 * tres articulos eran 15 EUR de envio.
 */
it('cobra el envio una sola vez aunque haya varias lineas', function () {
    ShippingMethod::factory()->physical()->create();

    $order = Order::factory()->create();
    OrderItem::factory()->count(3)->for($order)->create([
        'delivery_type' => DeliveryType::Physical,
        'line_total' => '40.00',
    ]);

    $totales = $this->pricing->totalsFor($order->fresh());

    expect($totales->subtotal)->toBe('120.00')
        ->and($totales->shippingTotal)->toBe('5.00')
        ->and($totales->total)->toBe('125.00');
});

/**
 * N6 — si todas las lineas son digitales el envio es 0. Si hay al menos una
 * fisica, el pedido entero paga envio fisico.
 */
it('no cobra envio si todo el pedido es digital', function () {
    ShippingMethod::factory()->physical()->create();
    ShippingMethod::factory()->digital()->create();

    $order = Order::factory()->create();
    OrderItem::factory()->count(2)->for($order)->digital()->create(['line_total' => '20.00']);

    $totales = $this->pricing->totalsFor($order->fresh());

    expect($totales->shippingTotal)->toBe('0.00')
        ->and($totales->total)->toBe('40.00');
});

it('cobra envio fisico si una sola linea lo es', function () {
    ShippingMethod::factory()->physical()->create();
    ShippingMethod::factory()->digital()->create();

    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->digital()->create(['line_total' => '20.00']);
    OrderItem::factory()->for($order)->create([
        'delivery_type' => DeliveryType::Physical,
        'line_total' => '40.00',
    ]);

    $totales = $this->pricing->totalsFor($order->fresh());

    expect($totales->shippingTotal)->toBe('5.00')
        ->and($totales->total)->toBe('65.00');
});

it('deja a cero un carrito vacio', function () {
    $totales = $this->pricing->totalsFor(Order::factory()->create());

    expect($totales->subtotal)->toBe('0.00')
        ->and($totales->shippingTotal)->toBe('0.00')
        ->and($totales->total)->toBe('0.00')
        ->and($totales->shippingMethodId)->toBeNull();
});

/**
 * El calculo va en centimos enteros. Con floats, 0.1 + 0.2 no es 0.3, y un
 * pedido de muchas lineas acumula el error hasta descuadrar el cobro.
 */
it('no arrastra error de coma flotante', function () {
    $variant = new ProductVariant(['price' => '0.10', 'additional_copy_price' => '0.20']);

    expect($this->pricing->priceLine($variant, 2)->lineTotal)->toBe('0.30');
});

it('rechaza una cantidad menor que uno', function () {
    $variant = new ProductVariant(['price' => '40.00', 'additional_copy_price' => '10.00']);

    expect(fn () => $this->pricing->priceLine($variant, 0))
        ->toThrow(InvalidArgumentException::class);
});
