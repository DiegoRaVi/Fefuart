<?php

use App\Enums\EventStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Testing\TestResponse;

/**
 * N15 — la señal cobrada es lo que confirma la reserva.
 *
 * Igual que con los pedidos, quien mueve el estado es el webhook con firma
 * verificada y nunca la vuelta del navegador. Aceptar el presupuesto deja el
 * evento en `accepted`; `confirmed` solo se alcanza con el dinero cobrado.
 */
beforeEach(function () {
    $this->secreto = 'whsec_de_prueba';
    config(['services.stripe.webhook.secret' => $this->secreto]);

    $this->fecha = now()->addMonths(6)->toDateString();

    $this->evento = Event::factory()->accepted()->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
    ]);

    $this->pago = Payment::factory()->create([
        'payable_type' => Event::class,
        'payable_id' => $this->evento->id,
        'provider_session_id' => 'cs_test_senal',
        'amount' => '360.00',
        'kind' => PaymentKind::Deposit,
        'status' => PaymentStatus::Pending,
    ]);
});

function eventoDeSenal(array $objeto = [], string $id = 'evt_senal_1'): array
{
    return [
        'id' => $id,
        'object' => 'event',
        'api_version' => '2026-07-29.dahlia',
        'created' => time(),
        'type' => 'checkout.session.completed',
        'data' => ['object' => array_merge([
            'id' => 'cs_test_senal',
            'object' => 'checkout.session',
            'status' => 'complete',
            'payment_status' => 'paid',
            'amount_total' => 36000,
            'currency' => 'eur',
            'payment_intent' => 'pi_test_senal',
            'mode' => 'payment',
        ], $objeto)],
    ];
}

function entregarSenal(array $evento): TestResponse
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

it('confirma el evento cuando la señal se cobra', function () {
    entregarSenal(eventoDeSenal())->assertNoContent();

    expect($this->pago->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->evento->fresh()->status)->toBe(EventStatus::Confirmed)
        // N16 — la franja queda ocupada por la base de datos, no por nosotros.
        ->and($this->evento->fresh()->confirmed_slot)->toContain($this->fecha);
});

it('no confirma si lo cobrado no es la señal', function () {
    entregarSenal(eventoDeSenal(['amount_total' => 100]))->assertStatus(500);

    expect($this->evento->fresh()->status)->toBe(EventStatus::Accepted)
        ->and($this->pago->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('no vuelve a confirmar con una segunda entrega', function () {
    entregarSenal(eventoDeSenal(id: 'evt_1'))->assertNoContent();
    entregarSenal(eventoDeSenal(id: 'evt_1'))->assertNoContent();

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and($this->evento->fresh()->status)->toBe(EventStatus::Confirmed);
});

/**
 * N16, el caso feo: dos clientes pagando la misma franja a la vez.
 *
 * QuoteService lo comprueba al aceptar, asi que para llegar aqui hacen falta
 * dos pagos cruzados. Lo que **no** puede pasar es que el rollback borre el
 * «cobrado»: el dinero se movio de verdad, y perder ese rastro dejaria a la
 * artista sin saber a quien devolver.
 */
it('guarda el cobro aunque la franja se haya ocupado mientras pagaba', function () {
    Event::factory()->create([
        'event_date' => $this->fecha,
        'schedule' => 'evening',
        'status' => EventStatus::Confirmed,
    ]);

    entregarSenal(eventoDeSenal())->assertStatus(500);

    expect($this->pago->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->pago->fresh()->paid_at)->not->toBeNull()
        ->and($this->evento->fresh()->status)->toBe(EventStatus::Accepted);

    // Y el motivo queda escrito, con el numero de cobro que hay que devolver.
    expect(WebhookEvent::query()->sole()->error)
        ->toContain('ya estaba ocupada')
        ->toContain((string) $this->pago->id);
});

/**
 * Un evento cancelado mientras el cliente pagaba no se confirma por la
 * puerta de atras: la maquina de estados no lo admite.
 */
it('no confirma un evento cancelado', function () {
    $this->evento->status = EventStatus::Cancelled;
    $this->evento->save();

    entregarSenal(eventoDeSenal())->assertStatus(500);

    expect($this->evento->fresh()->status)->toBe(EventStatus::Cancelled)
        // El cobro se guarda igual: hubo dinero de por medio.
        ->and($this->pago->fresh()->status)->toBe(PaymentStatus::Succeeded);
});
