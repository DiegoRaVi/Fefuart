<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\EventCancelled;
use App\Notifications\OrderStatusChanged;
use App\Notifications\QuoteRejected;
use App\Notifications\SlotFreed;
use Illuminate\Support\Facades\Notification;

/**
 * Lo que la Fase 6 se dejo mudo.
 *
 * El lado de pedidos avisaba de cada transicion que hace la artista; el de
 * eventos solo del presupuesto y de la confirmacion. Quedaban dos silencios,
 * y el segundo es grave: **N21 devuelve la señal por codigo**, asi que se le
 * cancelaba la boda a alguien y se le devolvian 360 € sin un solo correo.
 */
beforeEach(function () {
    Notification::fake();

    $this->withHeader('Referer', config('app.url'));

    $this->cliente = User::factory()->create();
    $this->artista = User::factory()->admin()->create();
});

describe('la artista rechaza una solicitud', function () {
    it('se lo dice al cliente', function () {
        $evento = Event::factory()->for($this->cliente)->create();

        $this->actingAs($this->artista)
            ->postJson("/api/admin/events/{$evento->id}/status", ['status' => 'rejected'])
            ->assertOk();

        Notification::assertSentTo($this->cliente, QuoteRejected::class);
    });

    /**
     * Sin este aviso el cliente espera un presupuesto que no va a llegar
     * nunca: N13 dice que no hay tarifas publicadas que consultar.
     */
    it('no avisa si la transicion no es posible', function () {
        $evento = Event::factory()->for($this->cliente)->create([
            'status' => EventStatus::Completed,
        ]);

        $this->actingAs($this->artista)
            ->postJson("/api/admin/events/{$evento->id}/status", ['status' => 'rejected'])
            ->assertStatus(422);

        Notification::assertNothingSent();
    });

    /** Completar no avisa: el cliente estuvo en su propio evento. */
    it('no avisa al completar', function () {
        $evento = Event::factory()->for($this->cliente)->create([
            'status' => EventStatus::Confirmed,
        ]);

        $this->actingAs($this->artista)
            ->postJson("/api/admin/events/{$evento->id}/status", ['status' => 'completed'])
            ->assertOk();

        Notification::assertNothingSent();
    });
});

describe('la artista cancela un evento con señal cobrada', function () {
    beforeEach(function () {
        $this->evento = Event::factory()->accepted()->for($this->cliente)->create([
            'event_date' => now()->addMonths(6)->toDateString(),
            'schedule' => 'evening',
            'status' => EventStatus::Confirmed,
        ]);

        Payment::factory()->create([
            'payable_type' => Event::class,
            'payable_id' => $this->evento->id,
            'amount' => '360.00',
            'kind' => PaymentKind::Deposit,
            'status' => PaymentStatus::Succeeded,
            'provider_payment_intent_id' => 'pi_test_devolucion',
        ]);

        // N21 — cancelar siendo la artista devuelve la señal, asi que hay
        // que preparar la respuesta o el doble de Stripe corta la peticion.
        $this->stripe->responde([
            'id' => 're_test_aviso',
            'object' => 'refund',
            'status' => 'succeeded',
            'amount' => 36000,
            'currency' => 'eur',
        ]);
    });

    /** El caso grave: se mueve dinero de vuelta y hay que contarlo. */
    it('avisa al cliente de que se le devuelve la señal', function () {
        $this->actingAs($this->artista)
            ->postJson("/api/admin/events/{$this->evento->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        Notification::assertSentTo(
            $this->cliente,
            EventCancelled::class,
            function (EventCancelled $aviso) {
                $datos = $aviso->toArray($this->cliente);

                return str_contains($datos['cuerpo'], '360,00');
            },
        );
    });

    /** Ella cancelo: no hace falta contarle a nadie que su agenda cambio. */
    it('no le avisa a ella de su propia cancelacion', function () {
        $this->actingAs($this->artista)
            ->postJson("/api/admin/events/{$this->evento->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        Notification::assertNotSentTo($this->artista, SlotFreed::class);
    });
});

describe('el cliente cancela su evento', function () {
    beforeEach(function () {
        $this->evento = Event::factory()->accepted()->for($this->cliente)->create([
            'event_date' => now()->addMonths(6)->toDateString(),
            'schedule' => 'evening',
            'status' => EventStatus::Confirmed,
        ]);
    });

    /**
     * D32 — la fecha vuelve a estar libre, y eso cambia el trabajo de la
     * artista. Es el aviso en sentido contrario.
     */
    it('le libera la fecha a la artista y se lo dice', function () {
        $this->actingAs($this->cliente)
            ->postJson("/api/events/{$this->evento->id}/cancel")
            ->assertOk();

        Notification::assertSentTo($this->artista, SlotFreed::class);
    });

    /** N21 — cancela el, asi que la señal no vuelve. El correo lo dice. */
    it('le confirma al cliente que pierde la señal', function () {
        Payment::factory()->create([
            'payable_type' => Event::class,
            'payable_id' => $this->evento->id,
            'amount' => '360.00',
            'kind' => PaymentKind::Deposit,
            'status' => PaymentStatus::Succeeded,
        ]);

        $this->actingAs($this->cliente)
            ->postJson("/api/events/{$this->evento->id}/cancel")
            ->assertOk();

        Notification::assertSentTo(
            $this->cliente,
            EventCancelled::class,
            function (EventCancelled $aviso) {
                return ! str_contains($aviso->toArray($this->cliente)['cuerpo'], 'devuel');
            },
        );
    });
});

/**
 * La otra deriva que quedaba: el cliente que cancela su pedido pasaba por
 * una comprobacion de transicion escrita a mano en el controller, no por
 * OrderService. Es lo que §4 del roadmap prohibe.
 */
describe('el cliente cancela su pedido', function () {
    it('lo cancela', function () {
        $pedido = Order::factory()->for($this->cliente)
            ->status(OrderStatus::PendingPayment)
            ->create();

        $this->actingAs($this->cliente)
            ->postJson(route('orders.cancel', $pedido))
            ->assertOk();

        expect($pedido->fresh()->status)->toBe(OrderStatus::Cancelled);
    });

    /**
     * Y **no** le manda un correo contandole lo que acaba de hacer. Es la
     * unica transicion de pedido que no avisa, y se suprime a proposito.
     */
    it('no le cuenta por correo lo que acaba de pulsar', function () {
        $pedido = Order::factory()->for($this->cliente)
            ->status(OrderStatus::PendingPayment)
            ->create();

        $this->actingAs($this->cliente)
            ->postJson(route('orders.cancel', $pedido))
            ->assertOk();

        Notification::assertNotSentTo($this->cliente, OrderStatusChanged::class);
    });
});
