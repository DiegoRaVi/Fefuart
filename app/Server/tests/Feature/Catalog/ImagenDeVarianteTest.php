<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * La foto de cada estilo.
 *
 * Hermana de `ImagenDeProductoTest` y por un motivo propio: «Acuarela» y
 * «Diseño de moda» son dos dibujos que no se parecen en nada, y con una sola
 * foto por producto la ficha pedia elegir entre tres nombres y tres precios
 * sin enseñar la diferencia entre ellos.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->withHeader('Referer', config('app.url'));

    $this->artista = User::factory()->admin()->create();
    $this->producto = Product::factory()->create(['slug' => 'dibujo-por-encargo']);
    $this->variante = ProductVariant::factory()->for($this->producto)->create([
        'name' => 'Acuarela',
    ]);
});

it('sube la foto del estilo y la enseña en la ficha publica', function () {
    $this->actingAs($this->artista)
        ->postJson("/api/admin/variants/{$this->variante->id}/image", [
            'file' => UploadedFile::fake()->image('acuarela.jpg', 2000, 1500),
        ])
        ->assertOk();

    // Sin sesion: la ficha es publica y es donde tiene que verse.
    $respuesta = $this->getJson('/api/catalog/products/dibujo-por-encargo')->assertOk();

    expect($respuesta->json('data.variants.0.image.url'))->toBeString();
    Storage::disk('public')->assertExists($this->variante->fresh()->image->path);
});

it('cierra la subida a los clientes', function () {
    $cliente = User::factory()->create();

    $this->actingAs($cliente)
        ->postJson("/api/admin/variants/{$this->variante->id}/image", [
            'file' => UploadedFile::fake()->image('acuarela.jpg'),
        ])
        ->assertForbidden();
});

/** Sustituir no puede dejar la anterior tirada en el disco. */
it('borra la foto anterior al sustituirla', function () {
    $this->actingAs($this->artista);

    $this->postJson("/api/admin/variants/{$this->variante->id}/image", [
        'file' => UploadedFile::fake()->image('primera.jpg'),
    ])->assertOk();

    $primera = $this->variante->fresh()->image;

    $this->postJson("/api/admin/variants/{$this->variante->id}/image", [
        'file' => UploadedFile::fake()->image('segunda.jpg'),
    ])->assertOk();

    Storage::disk('public')->assertMissing($primera->path);
});

/**
 * SEC-014 — se mira lo que traen los bytes, no lo que declara el cliente.
 *
 * `UploadedFile::fake()` no vale aqui: declara el MIME que le pidas sin
 * mirar el contenido, y con el la comprobacion pasaria sin comprobar nada.
 */
it('rechaza un fichero que no es una imagen', function () {
    $this->actingAs($this->artista);

    $ruta = tempnam(sys_get_temp_dir(), 'variante').'.jpg';
    file_put_contents($ruta, '<html><script>alert(1)</script></html>');

    $this->postJson("/api/admin/variants/{$this->variante->id}/image", [
        'file' => new UploadedFile($ruta, 'acuarela.jpg', 'image/jpeg', null, test: true),
    ])->assertStatus(422);

    expect($this->variante->fresh()->image_media_id)->toBeNull();

    @unlink($ruta);
});

/**
 * La foto es por estilo, no por producto: subir la de uno no puede aparecer
 * en el de al lado. Es la regresion que rompe si alguien vuelve a colgar la
 * relacion del producto en vez de la variante.
 */
it('no contagia la foto de un estilo a los demas', function () {
    $otra = ProductVariant::factory()->for($this->producto)->create(['name' => 'Digital']);

    $this->actingAs($this->artista)
        ->postJson("/api/admin/variants/{$this->variante->id}/image", [
            'file' => UploadedFile::fake()->image('acuarela.jpg'),
        ])
        ->assertOk();

    expect($this->variante->fresh()->image_media_id)->not->toBeNull()
        ->and($otra->fresh()->image_media_id)->toBeNull();
});

/** Un estilo sin foto sigue saliendo: la ficha se cae a la del producto. */
it('devuelve el estilo aunque no tenga foto', function () {
    $this->getJson('/api/catalog/products/dibujo-por-encargo')
        ->assertOk()
        ->assertJsonPath('data.variants.0.image', null);
});
