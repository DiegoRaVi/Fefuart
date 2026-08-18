<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\NewLiveArtRequest;
use App\Notifications\OrderPaid;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * D10 — los dos avisos que van hacia Felicitas, no hacia el cliente.
 *
 * No estaban en la lista original del roadmap y entran porque sin ellos hay
 * trabajo que nadie sabe que ha llegado: una solicitud de Live Art se queda
 * esperando un presupuesto que solo puede emitir ella, y un encargo pagado
 * un domingo no existe hasta que alguien abra el backoffice.
 */
beforeEach(function () {
    Notification::fake();

    $this->withHeader('Referer', config('app.url'));

    $this->cliente = User::factory()->create();
    $this->artista = User::factory()->admin()->create();
});

describe('una solicitud de Live Art', function () {
    it('avisa a la artista de que hay una solicitud nueva', function () {
        $this->actingAs($this->cliente)
            ->postJson('/api/events', [
                'title' => 'Boda de Marta y Luis',
                'description' => 'Retratos en directo durante el cocktail.',
                'phone' => '600123456',
                'event_date' => now()->addMonths(8)->toDateString(),
                'schedule' => 'evening',
                'location' => 'Finca El Olivar, Toledo',
                'guest_count' => 120,
                'duration_hours' => 3,
                'event_type' => 'boda',
            ])
            ->assertCreated();

        Notification::assertSentTo($this->artista, NewLiveArtRequest::class);
    });

    /** El aviso es para quien tiene que presupuestar, no para quien pide. */
    it('no le manda a el cliente su propia solicitud', function () {
        $this->actingAs($this->cliente)
            ->postJson('/api/events', [
                'title' => 'Comunion de Julia',
                'description' => 'Letras ilustradas en directo.',
                'phone' => '600123456',
                'event_date' => now()->addMonths(4)->toDateString(),
                'schedule' => 'morning',
                'location' => 'Madrid',
                'guest_count' => 40,
                'duration_hours' => 2,
                'event_type' => 'comunion',
            ])
            ->assertCreated();

        Notification::assertNotSentTo($this->cliente, NewLiveArtRequest::class);
    });

    /**
     * Con dos administradoras el aviso va a las dos. Hoy es una sola persona;
     * la consulta se escribe por rol y no por un id fijo para que siga siendo
     * cierto el dia que no lo sea.
     */
    it('avisa a todas las administradoras que haya', function () {
        $segunda = User::factory()->admin()->create();

        $this->actingAs($this->cliente)
            ->postJson('/api/events', [
                'title' => 'Cena de empresa',
                'description' => 'Caricaturas para los asistentes.',
                'phone' => '600123456',
                'event_date' => now()->addMonths(2)->toDateString(),
                'schedule' => 'evening',
                'location' => 'Bilbao',
                'guest_count' => 80,
                'duration_hours' => 4,
                'event_type' => 'empresa',
            ])
            ->assertCreated();

        Notification::assertSentTo($this->artista, NewLiveArtRequest::class);
        Notification::assertSentTo($segunda, NewLiveArtRequest::class);
    });
});

describe('un pedido pagado', function () {
    beforeEach(function () {
        $this->pedido = Order::factory()->for($this->cliente)
            ->status(OrderStatus::PendingPayment)
            ->create(['total' => '45.00']);

        Payment::factory()->create([
            'payable_type' => Order::class,
            'payable_id' => $this->pedido->id,
            'provider_session_id' => 'cs_test_artista',
            'amount' => '45.00',
            'status' => PaymentStatus::Pending,
        ]);

        $this->secreto = 'whsec_de_prueba';
        config(['services.stripe.webhook.secret' => $this->secreto]);
    });

    it('avisa a la artista de que hay un encargo que empezar', function () {
        cobrar('evt_artista_1')->assertNoContent();

        Notification::assertSentTo($this->artista, OrderPaid::class);
    });

    /** Misma guarda que el aviso del cliente: un reenvio no repite nada. */
    it('no repite el aviso cuando Stripe reenvia el mismo evento', function () {
        cobrar('evt_artista_1')->assertNoContent();
        cobrar('evt_artista_1')->assertNoContent();

        Notification::assertSentToTimes($this->artista, OrderPaid::class, 1);
    });

    /**
     * La señal de un evento no es un encargo que empezar: la fecha ya estaba
     * en la agenda desde que se presupuesto. Este aviso es solo de pedidos.
     */
    it('no avisa de un pedido cuando lo que se cobra es una señal', function () {
        $evento = Event::factory()->accepted()->create([
            'event_date' => now()->addMonths(6)->toDateString(),
            'schedule' => 'evening',
        ]);

        // El cobro del pedido se aparta primero: `provider_session_id` es
        // unico, y el webhook tiene que resolver la sesion del evento.
        Payment::query()
            ->where('payable_type', Order::class)
            ->update(['provider_session_id' => 'cs_test_apartada']);

        Payment::factory()->create([
            'payable_type' => Event::class,
            'payable_id' => $evento->id,
            'provider_session_id' => 'cs_test_artista',
            'amount' => '360.00',
            'kind' => PaymentKind::Deposit,
            'status' => PaymentStatus::Pending,
        ]);

        cobrar('evt_artista_senal', 36000)->assertNoContent();

        Notification::assertNotSentTo($this->artista, OrderPaid::class);
    });
});

/** Un `checkout.session.completed` firmado sobre la sesion del pedido. */
function cobrar(string $id, int $centimos = 4500): TestResponse
{
    $cuerpo = json_encode([
        'id' => $id,
        'object' => 'event',
        'api_version' => '2026-07-29.dahlia',
        'created' => time(),
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_artista',
            'object' => 'checkout.session',
            'status' => 'complete',
            'payment_status' => 'paid',
            'amount_total' => $centimos,
            'currency' => 'eur',
            'payment_intent' => 'pi_test_artista',
            'mode' => 'payment',
        ]],
    ], JSON_THROW_ON_ERROR);

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
