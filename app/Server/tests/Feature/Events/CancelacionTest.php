<?php

use App\Enums\EventStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;

/**
 * N21 — que pasa con la señal cuando se cancela un evento ya confirmado.
 *
 * La señal reserva la fecha y bloquea la agenda. Si quien se echa atras es
 * el cliente, compensa el hueco y no se devuelve; si quien cancela es la
 * artista, se devuelve entera, porque el hueco lo libera ella.
 *
 * La devolucion se hace desde el codigo y no a mano en el panel a proposito:
 * la regla es deterministica y olvidarse de aplicarla deja al cliente sin su
 * dinero. Lo que ningun endpoint hace es devolver por su cuenta: se llega
 * ahi como consecuencia declarada de cancelar.
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->cliente = User::factory()->create();
    $this->admin = User::factory()->admin()->create();

    $this->evento = Event::factory()->for($this->cliente)->accepted()->create([
        'event_date' => now()->addMonths(6)->toDateString(),
        'schedule' => 'evening',
        'status' => EventStatus::Confirmed,
    ]);

    $this->senal = Payment::factory()->succeeded()->create([
        'payable_type' => Event::class,
        'payable_id' => $this->evento->id,
        'amount' => '360.00',
        'kind' => PaymentKind::Deposit,
    ]);
});

/** Una devolucion como la devuelve Stripe. */
function unaDevolucion(): array
{
    return [
        'id' => 're_test_1',
        'object' => 'refund',
        'status' => 'succeeded',
        'amount' => 36000,
        'currency' => 'eur',
    ];
}

it('no devuelve la señal si cancela el cliente', function () {
    $this->actingAs($this->cliente)
        ->postJson("/api/events/{$this->evento->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($this->senal->fresh()->status)->toBe(PaymentStatus::Succeeded)
        // Ni siquiera se le pregunta a Stripe.
        ->and($this->stripe->peticiones)->toBeEmpty();
});

it('devuelve la señal entera si cancela la artista', function () {
    $this->stripe->responde(unaDevolucion());

    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$this->evento->id}/status", ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($this->senal->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($this->senal->fresh()->failure_reason)->toContain('artista');

    $peticion = $this->stripe->ultima();

    expect($peticion['url'])->toContain('/refunds')
        ->and($peticion['params']['payment_intent'])->toBe($this->senal->provider_payment_intent_id)
        // Sin importe: se devuelve entera, no una parte.
        ->and($peticion['params'])->not->toHaveKey('amount');
});

/** La clave de idempotencia impide que un reintento devuelva dos veces. */
it('manda una clave de idempotencia atada al cobro', function () {
    $this->stripe->responde(unaDevolucion());

    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$this->evento->id}/status", ['status' => 'cancelled']);

    expect($this->stripe->cabecerasDe()['idempotency-key'] ?? null)
        ->toBe("refund:{$this->senal->idempotency_key}");
});

it('no vuelve a devolver un cobro ya devuelto', function () {
    $this->senal->status = PaymentStatus::Refunded;
    $this->senal->save();

    // El evento sigue confirmado, asi que la cancelacion es valida.
    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$this->evento->id}/status", ['status' => 'cancelled'])
        ->assertOk();

    expect($this->stripe->peticiones)->toBeEmpty();
});

it('no llama a la pasarela si no hay señal cobrada', function () {
    $evento = Event::factory()->for($this->cliente)->create();

    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$evento->id}/status", ['status' => 'cancelled'])
        ->assertOk();

    expect($evento->fresh()->status)->toBe(EventStatus::Cancelled)
        ->and($this->stripe->peticiones)->toBeEmpty();
});

/** N16 — cancelar libera la franja para otra reserva. */
it('libera la fecha al cancelar', function () {
    $this->stripe->responde(unaDevolucion());

    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$this->evento->id}/status", ['status' => 'cancelled']);

    $otro = Event::factory()->accepted()->create([
        'event_date' => $this->evento->event_date->toDateString(),
        'schedule' => 'evening',
    ]);

    $otro->status = EventStatus::Confirmed;
    $otro->save();

    expect($otro->fresh()->status)->toBe(EventStatus::Confirmed);
});

it('no deja cancelar el evento de otro', function () {
    $this->actingAs(User::factory()->create())
        ->postJson("/api/events/{$this->evento->id}/cancel")
        ->assertForbidden();

    expect($this->evento->fresh()->status)->toBe(EventStatus::Confirmed);
});

it('no deja cancelar un evento ya celebrado', function () {
    $this->evento->status = EventStatus::Completed;
    $this->evento->save();

    $this->actingAs($this->cliente)
        ->postJson("/api/events/{$this->evento->id}/cancel")
        ->assertJsonValidationErrors('status');

    expect($this->stripe->peticiones)->toBeEmpty();
});

/**
 * Las pantallas tienen que poder avisar antes de que nadie pulse, asi que el
 * servidor dice si hay señal cobrada en vez de dejar que se deduzca.
 */
it('dice si la señal esta cobrada', function () {
    $this->actingAs($this->cliente)
        ->getJson("/api/events/{$this->evento->id}")
        ->assertOk()
        ->assertJsonPath('data.deposit_paid', true);

    $sinPagar = Event::factory()->for($this->cliente)->quoted()->create();

    $this->actingAs($this->cliente)
        ->getJson("/api/events/{$sinPagar->id}")
        ->assertJsonPath('data.deposit_paid', false);
});
