<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->user = User::factory()->create();
    $this->intruso = User::factory()->create();
});

it('exige sesion para listar pedidos', function () {
    $this->getJson(route('orders.index'))->assertUnauthorized();
});

/**
 * BUG-003 — en v1 `GET /user-orders` apuntaba a `getOrdersByUserId($id)`
 * pero la ruta no definia `{id}`, asi que respondia 500. El pedido de cada
 * cual se deduce de la sesion, no de un id en la URL.
 */
it('lista mis pedidos sin pedirme ningun id', function () {
    Order::factory()->for($this->user)->placed()->create();
    Order::factory()->for($this->intruso)->placed()->create();

    $this->actingAs($this->user)->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

/**
 * El carrito no es un pedido: no aparece en «mis pedidos» hasta que se
 * encarga.
 */
it('no lista el carrito entre los pedidos', function () {
    Order::factory()->for($this->user)->create(['status' => OrderStatus::Cart]);

    $this->actingAs($this->user)->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('pagina el listado con meta', function () {
    Order::factory()->count(3)->for($this->user)->placed()->create();

    $this->actingAs($this->user)->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonPath('meta.total', 3);
});

/**
 * BUG-004 — regresion.
 *
 * `OrderController.php:59` comparaba `$user->role !== $order->user_id`, o
 * sea un rol contra un id, de modo que un cliente normal **nunca** podia ver
 * su propio pedido: siempre 403.
 */
it('deja al cliente ver su propio pedido', function () {
    $order = Order::factory()->for($this->user)->placed()->create();

    $this->actingAs($this->user)->getJson(route('orders.show', $order))
        ->assertOk()
        ->assertJsonPath('data.id', $order->id);
});

/**
 * SEC-003 / SEC-008 — el IDOR de pedidos.
 *
 * En v1 `GET /api/products/1..N` enumeraba las lineas de todos los pedidos
 * del sistema, y `PATCH /api/orders/{id}` movia el estado, el total y la
 * direccion de cualquiera: la autorizacion estaba comentada.
 */
it('no deja ver el pedido de otro', function () {
    $ajeno = Order::factory()->for($this->intruso)->placed()->create();
    OrderItem::factory()->for($ajeno)->create();

    $this->actingAs($this->user)->getJson(route('orders.show', $ajeno))
        ->assertForbidden();
});

it('deja a la administradora ver cualquier pedido', function () {
    $order = Order::factory()->for($this->user)->placed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('orders.show', $order))
        ->assertOk()
        ->assertJsonPath('data.id', $order->id);
});

it('devuelve las lineas con el precio que se cobro', function () {
    $order = Order::factory()->for($this->user)->placed()->create();
    OrderItem::factory()->for($order)->create([
        'product_name' => 'Ramos dibujados',
        'unit_price' => '40.00',
        'line_total' => '50.00',
        'quantity' => 2,
    ]);

    $this->actingAs($this->user)->getJson(route('orders.show', $order))
        ->assertOk()
        ->assertJsonPath('data.items.0.product_name', 'Ramos dibujados')
        ->assertJsonPath('data.items.0.line_total', '50.00');
});

/**
 * N12 — el cliente cancela solo antes de pagar.
 */
it('deja cancelar un pedido sin pagar', function () {
    $order = Order::factory()->for($this->user)->status(OrderStatus::PendingPayment)->create();

    $this->actingAs($this->user)->postJson(route('orders.cancel', $order))
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('no deja al cliente cancelar un pedido ya pagado', function () {
    $order = Order::factory()->for($this->user)->status(OrderStatus::Paid)->create();

    $this->actingAs($this->user)->postJson(route('orders.cancel', $order))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('deja a la administradora cancelar un pedido pagado', function () {
    $order = Order::factory()->for($this->user)->status(OrderStatus::Paid)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('orders.cancel', $order))
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

/**
 * SEC-003 — el fraude concreto que permitia v1: `PATCH /api/orders/7
 * {"status":"paid"}` desde cualquier cuenta autenticada.
 */
it('no deja cancelar el pedido de otro', function () {
    $ajeno = Order::factory()->for($this->intruso)->status(OrderStatus::PendingPayment)->create();

    $this->actingAs($this->user)->postJson(route('orders.cancel', $ajeno))
        ->assertForbidden();

    expect($ajeno->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('rechaza cancelar un pedido ya completado', function () {
    $order = Order::factory()->for($this->user)->status(OrderStatus::Completed)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('orders.cancel', $order))
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

/**
 * SEC-003 — la via de v1 ya no existe. No hay ningun endpoint que acepte un
 * estado o un total en el cuerpo; el estado se mueve por sub-recursos.
 */
it('no expone ninguna ruta que acepte el estado o el total de un pedido', function () {
    $rutas = collect(Route::getRoutes())->map(
        fn ($r) => implode('|', $r->methods()).' '.$r->uri()
    );

    expect($rutas)->not->toContain('PATCH api/orders/{order}')
        ->and($rutas)->not->toContain('PUT api/orders/{order}');
});

/**
 * SEC-009 — regresion por ausencia.
 *
 * En v1 `GET /api/user/{id}` iba bajo `IsUserAuth` en vez de `IsAdmin` y
 * devolvia el modelo User completo de cualquiera: nombre, email, rol y
 * fechas. En v2 esa ruta sencillamente no existe, y el perfil solo devuelve
 * la cuenta propia.
 */
it('no expone ninguna ruta que devuelva otro usuario por id', function () {
    $uris = collect(Route::getRoutes())->map(fn ($r) => $r->uri());

    expect($uris)->not->toContain('api/user/{id}')
        ->and($uris)->not->toContain('api/users/{id}')
        ->and($uris)->not->toContain('api/users/{user}');
});

it('devuelve solo la cuenta propia en el perfil', function () {
    $this->actingAs($this->user)->getJson(route('profile.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);
});
