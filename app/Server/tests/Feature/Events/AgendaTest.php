<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * N16 — no puede haber dos eventos confirmados en la misma fecha y franja.
 *
 * Lo garantiza la base de datos, no la aplicacion: `confirmed_slot` es una
 * columna generada que vale NULL salvo que el evento este confirmado, con
 * indice unico encima. Los NULL no colisionan entre si, asi que las
 * solicitudes pueden solaparse cuanto quieran (N17) y solo lo confirmado
 * queda restringido.
 *
 * «La aplicacion no es la unica linea de defensa» esta escrito en la propia
 * regla, y este fichero es lo que lo hace cierto: si alguien anade manana un
 * camino que confirme un evento sin pasar por el servicio, la base de datos
 * lo para igual.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->fecha = now()->addMonths(6)->toDateString();
});

/** Confirma un evento sin pasar por ningun servicio: a pelo contra la tabla. */
function confirmar(Event $evento): void
{
    $evento->status = EventStatus::Confirmed;
    $evento->save();
}

it('deja confirmar un evento', function () {
    $evento = Event::factory()->for($this->user)->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
    ]);

    confirmar($evento);

    expect($evento->fresh()->status)->toBe(EventStatus::Confirmed);
});

/** N17 — las solicitudes si pueden solaparse; solo una llega a confirmarse. */
it('admite tantas solicitudes como quieran para la misma franja', function () {
    Event::factory()->count(3)->for($this->user)->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
    ]);

    expect(Event::query()->count())->toBe(3);
});

it('impide confirmar dos eventos en la misma fecha y franja', function () {
    $primero = Event::factory()->for($this->user)->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
    ]);
    $segundo = Event::factory()->for($this->user)->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
    ]);

    confirmar($primero);

    expect(fn () => confirmar($segundo))->toThrow(QueryException::class);
});

it('deja confirmar dos eventos el mismo dia en franjas distintas', function () {
    $manana = Event::factory()->for($this->user)->create([
        'event_date' => $this->fecha,
        'schedule' => 'morning',
    ]);
    $tarde = Event::factory()->for($this->user)->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
    ]);

    confirmar($manana);
    confirmar($tarde);

    expect(Event::query()->where('status', EventStatus::Confirmed)->count())->toBe(2);
});

it('libera la franja si el evento confirmado se cancela', function () {
    $primero = Event::factory()->for($this->user)->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
    ]);
    $segundo = Event::factory()->for($this->user)->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
    ]);

    confirmar($primero);

    $primero->status = EventStatus::Cancelled;
    $primero->save();

    // La franja vuelve a estar libre: `confirmed_slot` pasa a NULL.
    confirmar($segundo);

    expect($segundo->fresh()->status)->toBe(EventStatus::Confirmed);
});

/**
 * DB-006 — el mismo truco para el carrito. v1 no impedia varias ordenes en
 * estado `cart` por usuario, y `getCartOrder` resolvia el empate con un
 * `->first()`: el carrito que veias dependia del orden de insercion.
 */
describe('un solo carrito por usuario', function () {
    it('impide dos carritos abiertos del mismo usuario', function () {
        Order::factory()->for($this->user)->create(['status' => OrderStatus::Cart]);

        expect(fn () => Order::factory()->for($this->user)->create([
            'status' => OrderStatus::Cart,
        ]))->toThrow(QueryException::class);
    });

    it('deja a dos usuarios tener su carrito a la vez', function () {
        Order::factory()->for($this->user)->create(['status' => OrderStatus::Cart]);
        Order::factory()->for(User::factory())->create(['status' => OrderStatus::Cart]);

        expect(Order::query()->where('status', OrderStatus::Cart)->count())->toBe(2);
    });

    /** Un pedido ya hecho no ocupa el hueco del carrito. */
    it('deja tener carrito y varios pedidos a la vez', function () {
        Order::factory()->count(3)->for($this->user)->placed()->create();
        Order::factory()->for($this->user)->create(['status' => OrderStatus::Cart]);

        expect(Order::query()->count())->toBe(4);
    });

    it('libera el hueco al encargar el carrito', function () {
        $carrito = Order::factory()->for($this->user)->create(['status' => OrderStatus::Cart]);

        $carrito->status = OrderStatus::PendingPayment;
        $carrito->save();

        // Ya se puede empezar otro.
        Order::factory()->for($this->user)->create(['status' => OrderStatus::Cart]);

        expect(Order::query()->count())->toBe(2);
    });
});

/** Que las columnas sean generadas y no mantenidas a mano es lo que hace que
 *  la garantia no dependa de que nadie se olvide de actualizarlas. */
it('mantiene las dos columnas sola la base de datos', function () {
    expect(Schema::hasColumn('events', 'confirmed_slot'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'cart_slot'))->toBeTrue();

    $evento = Event::factory()->for($this->user)->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
    ]);

    // Nadie ha escrito `confirmed_slot`: lo calcula el motor.
    expect($evento->fresh()->confirmed_slot)->toBeNull();

    confirmar($evento);

    expect($evento->fresh()->confirmed_slot)->toContain($this->fecha);
});
