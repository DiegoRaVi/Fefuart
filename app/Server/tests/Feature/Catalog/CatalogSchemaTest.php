<?php

use App\Enums\DeliveryType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * DB-002 — regresion.
 *
 * En v1 no existia catalogo. `products` tenia `order_id` y `price`, o sea
 * que era en realidad la tabla de lineas de pedido: ninguna tabla definia
 * que se podia encargar ni a que precio, y por eso el precio solo podia
 * venir del navegador (SEC-006).
 *
 * En v2 el catalogo define el tipo de encargo y el precio vive en la
 * variante. Si alguien vuelve a colgar un precio del producto, o a atar un
 * producto a un pedido, este test cae.
 */
it('separa el catalogo de la linea de pedido', function () {
    expect(Schema::hasColumn('products', 'price'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'order_id'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'stock'))->toBeFalse();
});

it('guarda el precio en la variante y no en el producto', function () {
    $product = Product::factory()->create();

    ProductVariant::factory()->for($product)->create([
        'name' => 'Acuarela',
        'price' => '40.00',
        'additional_copy_price' => '10.00',
    ]);

    $variant = $product->variants()->sole();

    expect($variant->price)->toBe('40.00')
        ->and($variant->additional_copy_price)->toBe('10.00');
});

/**
 * N7 — el tipo de entrega esta limitado por la variante: solo el estilo
 * «Digital» admite entrega digital. Lo modela el pivot, no un `if` en el
 * controller.
 */
it('declara por variante que entregas admite', function () {
    $fisico = ShippingMethod::factory()->physical()->create();
    $digital = ShippingMethod::factory()->digital()->create();

    $acuarela = ProductVariant::factory()->create();
    $acuarela->shippingMethods()->attach($fisico);

    $enDigital = ProductVariant::factory()->create();
    $enDigital->shippingMethods()->attach([$fisico->id, $digital->id]);

    $codigos = fn (ProductVariant $v) => $v->shippingMethods
        ->map(fn (ShippingMethod $m) => $m->code->value)
        ->sort()->values()->all();

    expect($codigos($acuarela))->toBe(['physical'])
        ->and($codigos($enDigital))->toBe(['digital', 'physical'])
        // El cast tiene que devolver el enum, no la cadena suelta.
        ->and($fisico->code)->toBe(DeliveryType::Physical);
});

it('no admite dos productos con el mismo slug', function () {
    Product::factory()->create(['slug' => 'dibujo-por-encargo']);

    expect(fn () => Product::factory()->create(['slug' => 'dibujo-por-encargo']))
        ->toThrow(QueryException::class);
});

/**
 * DB-003 — el backoffice y el catalogo publico filtran por estos campos y en
 * v1 no habia mas indices que la PK, el email unico y las claves foraneas.
 */
it('indexa el catalogo por los campos que se filtran', function () {
    $columnas = collect(Schema::getIndexes('products'))->pluck('columns');

    expect($columnas)->toContain(['is_active', 'category']);
});
