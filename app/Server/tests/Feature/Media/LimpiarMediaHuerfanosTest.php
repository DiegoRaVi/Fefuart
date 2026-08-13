<?php

use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/** Un fichero subido de verdad, con su rastro en el disco. */
function unFicheroSubido(array $atributos = []): MediaAsset
{
    $media = MediaAsset::factory()->create($atributos);
    Storage::disk('public')->put($media->path, 'bytes-de-la-imagen');

    return $media;
}

/**
 * La foto se sube antes de anadir la linea al carrito, asi que quien elige
 * una imagen y se va deja el fichero sin dueno. Tambien lo deja quien borra
 * una linea despues. Sin esto, esos ficheros se quedan para siempre.
 */
it('borra un fichero que no llego a usarse', function () {
    $huerfano = unFicheroSubido(['created_at' => now()->subDays(2)]);

    $this->artisan('media:limpiar')->assertSuccessful();

    expect(MediaAsset::query()->find($huerfano->id))->toBeNull();
    Storage::disk('public')->assertMissing($huerfano->path);
});

/**
 * El margen importa: alguien puede tener el formulario abierto con la foto
 * ya subida y estar todavia escribiendo las notas.
 */
it('respeta los recien subidos', function () {
    $reciente = unFicheroSubido(['created_at' => now()->subHour()]);

    $this->artisan('media:limpiar')->assertSuccessful();

    expect(MediaAsset::query()->find($reciente->id))->not->toBeNull();
    Storage::disk('public')->assertExists($reciente->path);
});

it('deja borrar antes con un margen mas corto', function () {
    $reciente = unFicheroSubido(['created_at' => now()->subHours(3)]);

    $this->artisan('media:limpiar', ['--horas' => 2])->assertSuccessful();

    expect(MediaAsset::query()->find($reciente->id))->toBeNull();
});

it('no toca la foto de referencia de un pedido', function () {
    $foto = unFicheroSubido(['created_at' => now()->subMonth()]);
    OrderItem::factory()->for(Order::factory())->create(['reference_media_id' => $foto->id]);

    $this->artisan('media:limpiar')->assertSuccessful();

    expect(MediaAsset::query()->find($foto->id))->not->toBeNull();
    Storage::disk('public')->assertExists($foto->path);
});

/** D20/N11 — el archivo final que sube la artista tampoco es huerfano. */
it('no toca la entrega digital de un pedido', function () {
    $entrega = unFicheroSubido(['created_at' => now()->subMonth()]);
    OrderItem::factory()->for(Order::factory())->create(['delivered_media_id' => $entrega->id]);

    $this->artisan('media:limpiar')->assertSuccessful();

    expect(MediaAsset::query()->find($entrega->id))->not->toBeNull();
});

it('dice cuantos ha borrado', function () {
    unFicheroSubido(['created_at' => now()->subDays(2)]);
    unFicheroSubido(['created_at' => now()->subDays(2)]);
    unFicheroSubido(['created_at' => now()->subHour()]);

    $this->artisan('media:limpiar')
        ->expectsOutputToContain('2')
        ->assertSuccessful();

    expect(MediaAsset::query()->count())->toBe(1);
});

it('no se queja cuando no hay nada que limpiar', function () {
    $this->artisan('media:limpiar')->assertSuccessful();
});

/**
 * Si el fichero ya no esta en el disco —borrado a mano, o un despliegue que
 * no arrastro storage— la fila tiene que irse igual. Fallar aqui dejaria el
 * comando atascado para siempre en la misma fila.
 */
it('borra la fila aunque el fichero ya no este en el disco', function () {
    $huerfano = MediaAsset::factory()->create(['created_at' => now()->subDays(2)]);

    $this->artisan('media:limpiar')->assertSuccessful();

    expect(MediaAsset::query()->find($huerfano->id))->toBeNull();
});
