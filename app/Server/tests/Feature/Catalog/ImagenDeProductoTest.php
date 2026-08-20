<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * La foto del producto en el catalogo.
 *
 * La auditoria de UX del 2026-08-20 encontro que la tienda vendia dibujos sin
 * enseñar ninguno: tres fichas de texto con precio. Nadie paga 40 € por un
 * retrato que no ha visto, y el precio aparecia antes que el producto, asi que
 * se juzgaba el coste sin nada con lo que compararlo.
 *
 * Va en `products` y no se toma de la galeria: son cosas distintas (D33). La
 * galeria es obra suya, seleccionada por ella y sin relacion con lo que se
 * vende; esto es el escaparate de un articulo concreto.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->withHeader('Referer', config('app.url'));

    $this->artista = User::factory()->admin()->create();
    $this->producto = Product::factory()->create(['slug' => 'dibujo-por-encargo']);
});

it('sube la foto del producto y la enseña en el catalogo publico', function () {
    $this->actingAs($this->artista)
        ->postJson("/api/admin/products/{$this->producto->id}/image", [
            'file' => UploadedFile::fake()->image('retrato.jpg', 2000, 1500),
        ])
        ->assertOk();

    // Sin sesion: el catalogo es publico y es donde tiene que verse.
    $respuesta = $this->getJson('/api/catalog/products')->assertOk();

    expect($respuesta->json('data.0.image.url'))->toBeString();
    Storage::disk('public')->assertExists($this->producto->fresh()->image->path);
});

it('cierra la subida a los clientes', function () {
    $cliente = User::factory()->create();

    $this->actingAs($cliente)
        ->postJson("/api/admin/products/{$this->producto->id}/image", [
            'file' => UploadedFile::fake()->image('retrato.jpg'),
        ])
        ->assertForbidden();
});

/** Sustituir no puede dejar la anterior tirada en el disco. */
it('borra la foto anterior al sustituirla', function () {
    $this->actingAs($this->artista);

    $this->postJson("/api/admin/products/{$this->producto->id}/image", [
        'file' => UploadedFile::fake()->image('primera.jpg'),
    ])->assertOk();

    $primera = $this->producto->fresh()->image;

    $this->postJson("/api/admin/products/{$this->producto->id}/image", [
        'file' => UploadedFile::fake()->image('segunda.jpg'),
    ])->assertOk();

    Storage::disk('public')->assertMissing($primera->path);
});

/** SEC-014 — se re-encodifica como cualquier otra imagen que entre. */
it('rechaza un fichero que no es una imagen', function () {
    $this->actingAs($this->artista);

    $ruta = tempnam(sys_get_temp_dir(), 'prod').'.jpg';
    file_put_contents($ruta, '<html><script>alert(1)</script></html>');

    $this->postJson("/api/admin/products/{$this->producto->id}/image", [
        'file' => new UploadedFile($ruta, 'retrato.jpg', 'image/jpeg', null, test: true),
    ])->assertStatus(422);

    expect($this->producto->fresh()->image_media_id)->toBeNull();

    @unlink($ruta);
});

/** Un producto sin foto sigue saliendo: la tienda no se cae por eso. */
it('devuelve el producto aunque no tenga foto', function () {
    $this->getJson('/api/catalog/products')
        ->assertOk()
        ->assertJsonPath('data.0.image', null);
});
