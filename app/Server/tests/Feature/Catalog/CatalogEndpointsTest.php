<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Database\Seeders\CatalogSeeder;

/**
 * El catalogo es publico: en v1 `galeria.html` e `index.html` no llamaban a
 * la API porque no habia catalogo que consultar (DB-002). La galeria era
 * HTML estatico.
 */
it('deja consultar el catalogo sin sesion', function () {
    Product::factory()->has(ProductVariant::factory(), 'variants')->create();

    $this->getJson('/api/catalog/products')->assertOk();

    $this->assertGuest();
});

it('lista solo los productos activos', function () {
    Product::factory()->create(['name' => 'Ramos dibujados', 'is_active' => true]);
    Product::factory()->inactive()->create(['name' => 'Descatalogado']);

    $nombres = $this->getJson('/api/catalog/products')->assertOk()->json('data.*.name');

    expect($nombres)->toBe(['Ramos dibujados']);
});

it('no expone las variantes desactivadas', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product)->create(['name' => 'Acuarela']);
    ProductVariant::factory()->for($product)->inactive()->create(['name' => 'Retirada']);

    $variantes = $this->getJson("/api/catalog/products/{$product->slug}")
        ->assertOk()->json('data.variants.*.name');

    expect($variantes)->toBe(['Acuarela']);
});

/**
 * SEC-006 — el catalogo es de donde el cliente **lee** el precio; no es de
 * donde lo propone. Que el precio viaje aqui es justo lo que permite que el
 * carrito no lo acepte en el cuerpo de la peticion.
 */
it('publica el precio y el precio de copia de cada variante', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product)->create([
        'name' => 'Acuarela',
        'price' => '40.00',
        'additional_copy_price' => '10.00',
    ]);

    $this->getJson("/api/catalog/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.variants.0.price', '40.00')
        ->assertJsonPath('data.variants.0.additional_copy_price', '10.00');
});

/**
 * N7 — el cliente tiene que poder saber que entregas admite cada variante
 * antes de elegir, porque el servidor va a rechazar las que no.
 */
it('declara en cada variante que entregas admite', function () {
    $fisico = ShippingMethod::factory()->physical()->create();
    $digital = ShippingMethod::factory()->digital()->create();

    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $variant->shippingMethods()->attach([$fisico->id, $digital->id]);

    $this->getJson("/api/catalog/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.variants.0.shipping_methods.0.code', 'physical')
        ->assertJsonPath('data.variants.0.shipping_methods.0.price', '5.00')
        ->assertJsonPath('data.variants.0.shipping_methods.1.code', 'digital')
        ->assertJsonPath('data.variants.0.shipping_methods.1.price', '0.00');
});

/**
 * BUG-007/BUG-008 — v1 devolvia `response()->json(['message'=>'…', 404])`,
 * de modo que el 404 iba dentro del array y el status real era 200. Y una
 * coleccion vacia respondia 404 en vez de 200 con lista vacia.
 */
it('devuelve 404 de verdad si el producto no existe', function () {
    $this->getJson('/api/catalog/products/no-existe')->assertNotFound();
});

it('devuelve 200 con lista vacia si no hay catalogo', function () {
    $this->getJson('/api/catalog/products')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('no publica un producto desactivado por su slug', function () {
    $product = Product::factory()->inactive()->create();

    $this->getJson("/api/catalog/products/{$product->slug}")->assertNotFound();
});

/**
 * El catalogo real de N1. La semilla es lo que hace que la Fase 2 sea
 * probable a mano, y D5 exige que todo lo que hay aqui sea editable despues
 * desde el backoffice.
 */
it('siembra los cuatro precios reales del negocio', function () {
    $this->seed(CatalogSeeder::class);

    $data = collect($this->getJson('/api/catalog/products')->assertOk()->json('data'));

    $precios = $data->flatMap(
        fn (array $p) => collect($p['variants'])->map(
            fn (array $v) => "{$p['slug']}/{$v['name']}: {$v['price']}"
        )
    )->all();

    expect($data->pluck('slug')->all())
        ->toBe(['dibujo-por-encargo', 'letras-infantiles', 'ramos-dibujados'])
        ->and($precios)->toBe([
            'dibujo-por-encargo/Diseño de moda: 30.00',
            'dibujo-por-encargo/Acuarela: 40.00',
            'dibujo-por-encargo/Digital: 20.00',
            'letras-infantiles/Lámina ilustrada: 40.00',
            'ramos-dibujados/Lámina del ramo: 40.00',
        ]);
});

/**
 * N9 — la imagen de referencia no es un adjunto opcional: es el material de
 * partida. Activa en dibujo y ramos, desactivada en letras.
 */
it('siembra la exigencia de foto de referencia donde toca', function () {
    $this->seed(CatalogSeeder::class);

    $exigen = collect($this->getJson('/api/catalog/products')->json('data'))
        ->mapWithKeys(fn (array $p) => [$p['slug'] => $p['requires_reference_image']])
        ->all();

    expect($exigen)->toBe([
        'dibujo-por-encargo' => true,
        'letras-infantiles' => false,
        'ramos-dibujados' => true,
    ]);
});

/**
 * N7 — solo el estilo «Digital» admite entrega digital. En v1 esto era un
 * `if` dentro del `<script>` de dibujo-encargo.html.
 */
it('siembra la entrega digital solo en el estilo digital', function () {
    $this->seed(CatalogSeeder::class);

    $variantes = collect($this->getJson('/api/catalog/products/dibujo-por-encargo')->json('data.variants'))
        ->mapWithKeys(fn (array $v) => [
            $v['name'] => collect($v['shipping_methods'])->pluck('code')->sort()->values()->all(),
        ])->all();

    expect($variantes)->toBe([
        'Diseño de moda' => ['physical'],
        'Acuarela' => ['physical'],
        'Digital' => ['digital', 'physical'],
    ]);
});
