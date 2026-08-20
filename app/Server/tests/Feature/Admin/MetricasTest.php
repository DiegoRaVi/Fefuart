<?php

use App\Enums\DeliveryType;
use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

/**
 * `GET /api/admin/metrics` — cuatro numeros, y solo cuatro.
 *
 * Estuvo aparcado desde la Fase 4 con una razon buena: sin saber que mira
 * Felicitas de verdad, es facil construir el panel equivocado. Se construye
 * ahora con lo minimo que casi seguro sirve —cuanto ha entrado este mes y
 * que hay pendiente de hacer— y **queda pendiente preguntarle**: lo que ella
 * mire un lunes por la manana es lo que deberia estar aqui.
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->artista = User::factory()->admin()->create();
    $this->cliente = User::factory()->create();
});

it('cierra las metricas a los clientes', function () {
    $this->actingAs($this->cliente)->getJson('/api/admin/metrics')->assertForbidden();
});

it('exige sesion', function () {
    $this->getJson('/api/admin/metrics')->assertUnauthorized();
});

it('cuenta los pedidos y los ingresos del mes', function () {
    Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create([
        'total' => '45.00',
        'placed_at' => now(),
    ]);

    Order::factory()->for($this->cliente)->status(OrderStatus::Completed)->create([
        'total' => '20.00',
        'placed_at' => now(),
    ]);

    $this->actingAs($this->artista)
        ->getJson('/api/admin/metrics')
        ->assertOk()
        ->assertJsonPath('data.pedidos_del_mes', 2)
        ->assertJsonPath('data.ingresos_del_mes', '65.00');
});

/**
 * Un pedido sin cobrar no es un ingreso. Es la diferencia que hace que el
 * numero valga para algo: contar carritos abandonados como facturacion seria
 * peor que no dar el numero.
 */
it('no cuenta como ingreso lo que no se ha cobrado', function () {
    Order::factory()->for($this->cliente)->status(OrderStatus::PendingPayment)->create([
        'total' => '999.00',
        'placed_at' => now(),
    ]);

    $this->actingAs($this->artista)
        ->getJson('/api/admin/metrics')
        ->assertJsonPath('data.ingresos_del_mes', '0.00');
});

it('no mezcla el mes pasado', function () {
    Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create([
        'total' => '100.00',
        'placed_at' => now()->subMonths(2),
    ]);

    $this->actingAs($this->artista)
        ->getJson('/api/admin/metrics')
        ->assertJsonPath('data.pedidos_del_mes', 0)
        ->assertJsonPath('data.ingresos_del_mes', '0.00');
});

/** Lo que espera una respuesta suya: solicitudes sin presupuestar. */
it('cuenta los eventos pendientes de presupuestar', function () {
    Event::factory()->for($this->cliente)->create(['status' => EventStatus::Requested]);
    Event::factory()->for($this->cliente)->create(['status' => EventStatus::Confirmed]);

    $this->actingAs($this->artista)
        ->getJson('/api/admin/metrics')
        ->assertJsonPath('data.eventos_por_presupuestar', 1);
});

/** N11, D20 — encargos digitales cobrados y todavia sin entregar. */
it('cuenta los encargos digitales sin entregar', function () {
    $pagado = Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create();

    OrderItem::factory()->for($pagado)->create([
        'delivery_type' => DeliveryType::Digital,
        'delivered_media_id' => null,
    ]);

    // Uno fisico no cuenta: no se entrega por descarga.
    OrderItem::factory()->for($pagado)->create(['delivery_type' => DeliveryType::Physical]);

    $this->actingAs($this->artista)
        ->getJson('/api/admin/metrics')
        ->assertJsonPath('data.entregas_pendientes', 1);
});
