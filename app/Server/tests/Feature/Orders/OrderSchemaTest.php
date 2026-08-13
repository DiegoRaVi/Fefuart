<?php

use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * El snapshot es lo que separa el catalogo del historico: cambiar el precio
 * de una variante no puede reescribir lo que alguien ya compro. En v1 el
 * precio de la linea lo mandaba el navegador, asi que ni siquiera habia un
 * precio de catalogo con el que contrastarlo.
 */
it('congela en la linea el precio con el que se compro', function () {
    $variant = ProductVariant::factory()->create([
        'price' => '40.00',
        'additional_copy_price' => '10.00',
    ]);

    $item = OrderItem::factory()->for(Order::factory())->fromVariant($variant)->create();

    // La artista sube los precios despues de la compra.
    $variant->update(['price' => '99.00', 'additional_copy_price' => '25.00']);

    $item->refresh()->load('variant');

    expect($item->unit_price)->toBe('40.00')
        ->and($item->additional_copy_price)->toBe('10.00')
        ->and($item->variant->price)->toBe('99.00');
});

/**
 * D14/N9 — el encargo concreto es de primera clase: lleva su descripcion y
 * la foto a partir de la cual se dibuja.
 */
it('guarda en la linea la foto de partida y las notas del cliente', function () {
    $foto = MediaAsset::factory()->create();

    $item = OrderItem::factory()->for(Order::factory())->create([
        'reference_media_id' => $foto->id,
        'customer_notes' => 'La foto de la boda, en blanco y negro.',
    ]);

    expect($item->referenceMedia->is($foto))->toBeTrue()
        ->and($item->customer_notes)->toBe('La foto de la boda, en blanco y negro.');
});

/**
 * D17/N5 — el envio sube de la linea al pedido. En v1 se cobraba por
 * producto: tres articulos eran 15 EUR en vez de 5.
 */
it('cuelga el envio del pedido y no de la linea', function () {
    expect(Schema::hasColumn('orders', 'shipping_method_id'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'shipping_total'))->toBeTrue()
        ->and(Schema::hasColumn('order_items', 'shipping_method_id'))->toBeFalse()
        ->and(Schema::hasColumn('order_items', 'shipping_total'))->toBeFalse();
});

/**
 * DB-004 — v1 borraba el pedido y sus productos de forma irreversible y sin
 * traza de auditoria.
 */
it('borra los pedidos de forma reversible', function () {
    $order = Order::factory()->create();

    $order->delete();

    expect(Order::query()->find($order->id))->toBeNull()
        ->and(Order::withTrashed()->find($order->id))->not->toBeNull();
});

/**
 * DB-003 — son los campos por los que filtra y ordena el backoffice, y en v1
 * no habia mas indices que la PK y las claves foraneas.
 */
it('indexa los pedidos por los campos que filtra el backoffice', function () {
    $columnas = collect(Schema::getIndexes('orders'))->pluck('columns');

    expect($columnas)->toContain(['user_id', 'status'])
        ->and($columnas)->toContain(['status', 'placed_at']);
});

/**
 * ARCH-004 — sanctum estaba instalado y su tabla creada, todo sin usar. El
 * modo cookie de D2 no emite tokens personales, asi que la tabla sobra.
 */
it('no arrastra la tabla de tokens que el modo cookie no usa', function () {
    expect(Schema::hasTable('personal_access_tokens'))->toBeFalse();
});

it('relaciona el pedido con su duenno y sus lineas', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->count(2)->for($order)->create();

    expect($order->user->is($user))->toBeTrue()
        ->and($order->items)->toHaveCount(2);
});
