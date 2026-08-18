<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\EventConfirmed;
use App\Notifications\OrderStatusChanged;
use App\Notifications\PaymentConfirmed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * D10 + D29 — los avisos que nacen del webhook, y por que no se duplican.
 *
 * Es el punto delicado de la fase. La garantia de Stripe es «al menos una
 * vez»: el mismo evento llega varias veces, y llega seguro si tardamos en
 * responder 2xx. Un aviso encolado en el sitio equivocado se convierte en un
 * segundo correo cada vez que Stripe reintenta.
 *
 * `StripeWebhookService` tiene tres guardas encadenadas y **solo dos sirven
 * de gancho**:
 *
 *   1. `registrar()` corta si el evento ya se atendio entero. No basta:
 *      por D30 una entrega puede fallar despues de guardar el cobro, y
 *      entonces el evento se reentrega con `processed_at` a null.
 *   2. `cumplir()`, en `if ($payment->status !== Succeeded)`. Corre una vez
 *      por cobro, jamas dos. Es donde va `PaymentConfirmed`.
 *   3. `entregar()`, en la comprobacion de estado del payable. Una vez por
 *      pedido o evento. Es donde va `EventConfirmed`.
 */
beforeEach(function () {
    Notification::fake();

    $this->secreto = 'whsec_de_prueba';
    config(['services.stripe.webhook.secret' => $this->secreto]);
});

/** @param array<string, mixed> $objeto */
function avisoEvento(string $sesion, int $centimos, string $id, array $objeto = []): array
{
    return [
        'id' => $id,
        'object' => 'event',
        'api_version' => '2026-07-29.dahlia',
        'created' => time(),
        'type' => 'checkout.session.completed',
        'data' => ['object' => array_merge([
            'id' => $sesion,
            'object' => 'checkout.session',
            'status' => 'complete',
            'payment_status' => 'paid',
            'amount_total' => $centimos,
            'currency' => 'eur',
            'payment_intent' => 'pi_test_aviso',
            'mode' => 'payment',
        ], $objeto)],
    ];
}

/** @param array<string, mixed> $evento */
function entregarAviso(array $evento): TestResponse
{
    $cuerpo = json_encode($evento, JSON_THROW_ON_ERROR);
    $momento = time();

    return test()->call(
        'POST',
        '/api/webhooks/stripe',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => sprintf(
                't=%d,v1=%s',
                $momento,
                hash_hmac('sha256', "{$momento}.{$cuerpo}", test()->secreto)
            ),
        ],
        content: $cuerpo,
    );
}

describe('el pago de un pedido', function () {
    beforeEach(function () {
        $this->pedido = Order::factory()->status(OrderStatus::PendingPayment)->create([
            'total' => '45.00',
        ]);

        $this->pago = Payment::factory()->create([
            'payable_type' => Order::class,
            'payable_id' => $this->pedido->id,
            'provider_session_id' => 'cs_test_aviso',
            'amount' => '45.00',
            'status' => PaymentStatus::Pending,
        ]);
    });

    it('avisa al cliente de que el pago ha entrado', function () {
        entregarAviso(avisoEvento('cs_test_aviso', 4500, 'evt_aviso_1'))->assertNoContent();

        Notification::assertSentTo($this->pedido->user, PaymentConfirmed::class);
    });

    /**
     * La regla que sostiene toda la fase: Stripe reenvia y solo sale un
     * correo. Si el aviso se encolara al principio de `procesar()` en vez de
     * dentro del `if` del cobro, aqui saldrian dos.
     */
    it('no manda un segundo correo cuando Stripe reenvia el mismo evento', function () {
        $aviso = avisoEvento('cs_test_aviso', 4500, 'evt_aviso_1');

        entregarAviso($aviso)->assertNoContent();
        entregarAviso($aviso)->assertNoContent();

        Notification::assertSentToTimes($this->pedido->user, PaymentConfirmed::class, 1);
    });

    /**
     * D30 — el caso que la primera guarda no cubre.
     *
     * Con el pedido cancelado, el cobro se guarda pero `entregar()` no puede
     * moverlo a `paid`: revienta, el webhook responde 500 y el evento queda
     * **sin** `processed_at`. Stripe lo reintenta, `registrar()` lo deja
     * pasar y `despachar()` se ejecuta entero otra vez. Lo unico que impide
     * el segundo correo es que el cobro ya conste como `succeeded`.
     */
    it('no repite el aviso cuando la entrega falla y Stripe reintenta', function () {
        $this->pedido->status = OrderStatus::Cancelled;
        $this->pedido->save();

        $aviso = avisoEvento('cs_test_aviso', 4500, 'evt_aviso_1');

        entregarAviso($aviso)->assertStatus(500);
        entregarAviso($aviso)->assertStatus(500);

        expect($this->pago->fresh()->status)->toBe(PaymentStatus::Succeeded);

        Notification::assertSentToTimes($this->pedido->user, PaymentConfirmed::class, 1);
    });

    /**
     * Un hecho, un aviso. El paso a `paid` lo cuenta `PaymentConfirmed`; si
     * ademas saliera `OrderStatusChanged`, el cliente recibiria dos correos
     * seguidos diciendo lo mismo.
     */
    it('no manda ademas el aviso de cambio de estado', function () {
        entregarAviso(avisoEvento('cs_test_aviso', 4500, 'evt_aviso_1'))->assertNoContent();

        Notification::assertNotSentTo($this->pedido->user, OrderStatusChanged::class);
    });

    /** Si el importe no cuadra no se entrega nada, y tampoco se avisa. */
    it('no avisa si lo cobrado no es lo que guardamos', function () {
        entregarAviso(avisoEvento('cs_test_aviso', 100, 'evt_aviso_1'))->assertStatus(500);

        Notification::assertNothingSent();
    });
});

describe('la señal de un evento', function () {
    beforeEach(function () {
        $this->fecha = now()->addMonths(6)->toDateString();

        $this->evento = Event::factory()->accepted()->create([
            'event_date' => $this->fecha,
            'schedule' => 'evening',
        ]);

        $this->senal = Payment::factory()->create([
            'payable_type' => Event::class,
            'payable_id' => $this->evento->id,
            'provider_session_id' => 'cs_test_senal_aviso',
            'amount' => '360.00',
            'kind' => PaymentKind::Deposit,
            'status' => PaymentStatus::Pending,
        ]);
    });

    it('avisa de que la fecha queda reservada', function () {
        entregarAviso(avisoEvento('cs_test_senal_aviso', 36000, 'evt_senal_aviso'))->assertNoContent();

        expect($this->evento->fresh()->status)->toBe(EventStatus::Confirmed);

        Notification::assertSentTo($this->evento->user, EventConfirmed::class);
    });

    /**
     * Aceptar, pagar y reservar es un solo gesto para el cliente, asi que es
     * un solo correo: el de la reserva, que ya dice que la señal se cobro.
     */
    it('no manda ademas el resguardo del cobro', function () {
        entregarAviso(avisoEvento('cs_test_senal_aviso', 36000, 'evt_senal_aviso'))->assertNoContent();

        Notification::assertNotSentTo($this->evento->user, PaymentConfirmed::class);
    });

    it('no repite el aviso cuando Stripe reenvia el mismo evento', function () {
        $aviso = avisoEvento('cs_test_senal_aviso', 36000, 'evt_senal_aviso');

        entregarAviso($aviso)->assertNoContent();
        entregarAviso($aviso)->assertNoContent();

        Notification::assertSentToTimes($this->evento->user, EventConfirmed::class, 1);
    });

    /**
     * N16 por la via de la base de datos — el caso que justifica que el
     * aviso vaya **fuera** de la transaccion de `confirmarPorSenal()`.
     *
     * Dos clientes pagando la misma franja a la vez: el segundo cobra, pero
     * el indice unico sobre `confirmed_slot` impide confirmarlo. El dinero
     * se ha movido y la fecha no es suya. Lo que no puede pasar es que
     * reciba un «tu fecha del 12/09 queda reservada» de un evento que nunca
     * llego a confirmarse.
     */
    it('no avisa de una reserva que la agenda rechaza', function () {
        Event::factory()->accepted()->create([
            'event_date' => $this->fecha,
            'schedule' => 'evening',
            'status' => EventStatus::Confirmed,
        ]);

        entregarAviso(avisoEvento('cs_test_senal_aviso', 36000, 'evt_senal_aviso'))
            ->assertStatus(500);

        expect($this->evento->fresh()->status)->toBe(EventStatus::Accepted)
            ->and($this->senal->fresh()->status)->toBe(PaymentStatus::Succeeded);

        Notification::assertNotSentTo($this->evento->user, EventConfirmed::class);
    });
});
