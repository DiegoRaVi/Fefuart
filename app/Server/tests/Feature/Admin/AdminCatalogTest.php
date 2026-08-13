<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->admin = User::factory()->admin()->create();
    $this->cliente = User::factory()->create();
});

it('exige sesion', function () {
    $this->getJson(route('admin.products.index'))->assertUnauthorized();
});

/**
 * N20 — la promocion a admin nunca ocurre por peticion HTTP, asi que un
 * cliente no llega aqui de ninguna manera. El middleware distingue el 401
 * de quien no tiene sesion del 403 de quien la tiene pero no manda; el
 * `IsAdmin` de v1 devolvia 403 en los dos casos.
 */
it('cierra el backoffice a un cliente', function (string $metodo, string $ruta) {
    $product = Product::factory()->create();

    $this->actingAs($this->cliente)
        ->json($metodo, str_replace('{id}', (string) $product->id, $ruta))
        ->assertForbidden();
})->with([
    ['GET', '/api/admin/products'],
    ['POST', '/api/admin/products'],
    ['PATCH', '/api/admin/products/{id}'],
    ['DELETE', '/api/admin/products/{id}'],
]);

it('lista tambien los productos desactivados', function () {
    Product::factory()->create(['name' => 'Publicado']);
    Product::factory()->inactive()->create(['name' => 'Retirado']);

    $nombres = $this->actingAs($this->admin)
        ->getJson(route('admin.products.index'))
        ->assertOk()->json('data.*.name');

    expect($nombres)->toHaveCount(2);
});

it('crea un producto con su variante', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), [
            'slug' => 'caricaturas',
            'name' => 'Caricaturas',
            'description' => 'Retrato en clave de humor.',
            'category' => 'dibujo',
            'requires_reference_image' => true,
            'max_quantity' => 5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'caricaturas');

    expect(Product::query()->where('slug', 'caricaturas')->exists())->toBeTrue();
});

it('rechaza un slug repetido', function () {
    Product::factory()->create(['slug' => 'caricaturas']);

    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), [
            'slug' => 'caricaturas',
            'name' => 'Otra cosa',
            'category' => 'dibujo',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('slug');
});

/**
 * D5 — el motivo de que esto exista: Felicitas cambia precios sin tocar
 * codigo. En v1 estaban escritos en el `<script>` de cada formulario.
 */
it('cambia el precio de una variante y el catalogo publico lo refleja', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create(['price' => '40.00']);

    $this->actingAs($this->admin)
        ->patchJson(route('admin.variants.update', $variant), ['price' => '45.00'])
        ->assertOk();

    $this->getJson(route('catalog.products.show', $product))
        ->assertOk()
        ->assertJsonPath('data.variants.0.price', '45.00');
});

/**
 * El snapshot cumpliendo su funcion: subir un precio no puede reescribir lo
 * que alguien ya compro.
 */
it('no reescribe el historico al cambiar un precio', function () {
    $variant = ProductVariant::factory()->create(['price' => '40.00']);
    $order = Order::factory()->placed()->create();
    $item = OrderItem::factory()->for($order)->fromVariant($variant)->create();

    $this->actingAs($this->admin)
        ->patchJson(route('admin.variants.update', $variant), ['price' => '99.00'])
        ->assertOk();

    expect($item->fresh()->unit_price)->toBe('40.00');
});

it('anade una variante a un producto existente', function () {
    $product = Product::factory()->create();
    $fisico = ShippingMethod::factory()->physical()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.products.variants.store', $product), [
            'name' => 'Carboncillo',
            'price' => '35.00',
            'additional_copy_price' => '10.00',
            'shipping_method_ids' => [$fisico->id],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Carboncillo');

    expect($product->variants()->sole()->shippingMethods)->toHaveCount(1);
});

/**
 * DB-004 — v1 borraba de forma irreversible y sin traza. Un producto
 * retirado desaparece del catalogo publico, pero el pedido que lo compro
 * sigue mostrando su nombre gracias al snapshot.
 */
it('retira un producto sin romper el historico', function () {
    $variant = ProductVariant::factory()->create();
    $product = $variant->product;

    $order = Order::factory()->placed()->create();
    $item = OrderItem::factory()->for($order)->fromVariant($variant)->create();

    $this->actingAs($this->admin)
        ->deleteJson(route('admin.products.destroy', $product))
        ->assertNoContent();

    $this->getJson(route('catalog.products.index'))
        ->assertOk()
        ->assertJsonPath('data', []);

    expect(Product::withTrashed()->find($product->id))->not->toBeNull()
        ->and($item->fresh()->product_name)->toBe($product->name);
});

/**
 * SEC-001 por otra via: el backoffice gestiona el catalogo, no las cuentas.
 * Ninguna ruta de aqui toca `role_id`.
 */
it('no permite tocar el rol desde el backoffice de catalogo', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)
        ->patchJson(route('admin.products.update', $product), [
            'name' => 'Renombrado',
            'role_id' => 2,
        ])
        ->assertOk();

    expect($this->cliente->fresh()->isAdmin())->toBeFalse();
});
