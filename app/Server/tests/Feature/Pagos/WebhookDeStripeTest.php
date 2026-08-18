<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Testing\TestResponse;

/**
 * `POST /api/webhooks/stripe` — la unica ruta de la API sin sesion, y el
 * unico sitio del sistema donde un pedido pasa a `paid`.
 *
 * Lo que la protege no es una cookie sino la firma del cuerpo. Sin
 * verificarla, este endpoint seria «cualquiera declara pagado cualquier
 * pedido» con un `curl`, que es exactamente el agujero que tenia v1 por otra
 * via: alli el propio navegador mandaba `{status:'paid'}` (SEC-003).
 */
beforeEach(function () {
    $this->secreto = 'whsec_de_prueba';
    config(['services.stripe.webhook.secret' => $this->secreto]);

    $this->order = Order::factory()->placed()->create(['total' => '45.00']);

    $this->pago = Payment::factory()->create([
        'payable_type' => Order::class,
        'payable_id' => $this->order->id,
        'provider_session_id' => 'cs_test_webhook',
        'amount' => '45.00',
        'status' => PaymentStatus::Pending,
    ]);
});

/** Un evento de Stripe con la forma que tiene de verdad. */
function evento(string $tipo, array $objeto = [], string $id = 'evt_test_1'): array
{
    return [
        'id' => $id,
        'object' => 'event',
        'api_version' => '2026-07-29.dahlia',
        'created' => time(),
        'type' => $tipo,
        'data' => ['object' => array_merge([
            'id' => 'cs_test_webhook',
            'object' => 'checkout.session',
            'status' => 'complete',
            'payment_status' => 'paid',
            'amount_total' => 4500,
            'currency' => 'eur',
            'payment_intent' => 'pi_test_webhook',
            'mode' => 'payment',
        ], $objeto)],
    ];
}

/** La cabecera `Stripe-Signature` tal y como la calcula Stripe. */
function firma(string $cuerpo, ?string $secreto = null, ?int $momento = null): string
{
    $momento ??= time();
    $secreto ??= test()->secreto;

    return sprintf(
        't=%d,v1=%s',
        $momento,
        hash_hmac('sha256', "{$momento}.{$cuerpo}", $secreto)
    );
}

/** @param array<string, mixed> $evento */
function entregar(array $evento, ?string $cabecera = null): TestResponse
{
    $cuerpo = json_encode($evento, JSON_THROW_ON_ERROR);

    return test()->call(
        'POST',
        '/api/webhooks/stripe',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $cabecera ?? firma($cuerpo),
        ],
        content: $cuerpo,
    );
}

it('marca el pedido como pagado con una firma valida', function () {
    entregar(evento('checkout.session.completed'))->assertNoContent();

    expect($this->pago->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->pago->fresh()->provider_payment_intent_id)->toBe('pi_test_webhook')
        ->and($this->pago->fresh()->paid_at)->not->toBeNull()
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);

    $registro = WebhookEvent::query()->sole();

    expect($registro->provider_event_id)->toBe('evt_test_1')
        ->and($registro->yaProcesado())->toBeTrue()
        ->and($registro->error)->toBeNull();
});

describe('la firma', function () {
    it('rechaza una firma que no cuadra', function () {
        entregar(evento('checkout.session.completed'), 't=1,v1=falsa')->assertStatus(400);

        expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)
            ->and($this->pago->fresh()->status)->toBe(PaymentStatus::Pending)
            // Sin firma valida no se guarda nada: no es un evento de Stripe.
            ->and(WebhookEvent::query()->count())->toBe(0);
    });

    it('rechaza una firma calculada con otro secreto', function () {
        $cuerpo = json_encode(evento('checkout.session.completed'), JSON_THROW_ON_ERROR);

        entregar(
            evento('checkout.session.completed'),
            firma($cuerpo, 'whsec_de_otro')
        )->assertStatus(400);

        expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    });

    it('rechaza el cuerpo sin cabecera de firma', function () {
        entregar(evento('checkout.session.completed'), '')->assertStatus(400);

        expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    });

    /**
     * Reenviar un evento legitimo capturado hace un rato tambien es un
     * ataque. La marca de tiempo va dentro de lo firmado y Stripe la acepta
     * solo durante cinco minutos.
     */
    it('rechaza una firma vieja aunque sea autentica', function () {
        $cuerpo = json_encode(evento('checkout.session.completed'), JSON_THROW_ON_ERROR);

        entregar(
            evento('checkout.session.completed'),
            firma($cuerpo, momento: time() - 3600)
        )->assertStatus(400);

        expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    });

    /**
     * El cuerpo se verifica byte a byte. Si en algun momento alguien
     * decodifica el JSON y lo vuelve a serializar antes de comprobar la
     * firma, esto lo pilla: el orden de las claves cambia y el HMAC deja de
     * cuadrar.
     */
    it('verifica sobre el cuerpo crudo y no sobre el JSON reserializado', function () {
        $evento = evento('checkout.session.completed');
        $crudo = json_encode($evento, JSON_THROW_ON_ERROR);
        $reserializado = json_encode(json_decode($crudo, true), JSON_PRETTY_PRINT);

        // Firma valida para el cuerpo bonito, cuerpo compacto en la peticion.
        entregar($evento, firma((string) $reserializado))->assertStatus(400);
    });
});

describe('idempotencia', function () {
    /**
     * La garantia de Stripe es «al menos una vez»: el mismo evento llega
     * varias veces, y llega seguro si tardamos en responder 2xx.
     */
    it('atiende una sola vez el mismo evento', function () {
        entregar(evento('checkout.session.completed'))->assertNoContent();
        entregar(evento('checkout.session.completed'))->assertNoContent();

        expect(WebhookEvent::query()->count())->toBe(1)
            ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
    });

    /**
     * Dos eventos distintos sobre el mismo cobro tampoco lo pagan dos veces.
     * Pasa de verdad: una sesion con pago diferido manda `completed` y
     * despues `async_payment_succeeded`.
     */
    it('no vuelve a cobrar con un evento distinto sobre la misma sesion', function () {
        entregar(evento('checkout.session.completed', id: 'evt_1'));
        entregar(evento('checkout.session.async_payment_succeeded', id: 'evt_2'));

        expect(Payment::query()->count())->toBe(1)
            ->and($this->pago->fresh()->status)->toBe(PaymentStatus::Succeeded)
            ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid)
            ->and(WebhookEvent::query()->count())->toBe(2);
    });
});

describe('pagos diferidos', function () {
    /**
     * Una sesion puede completarse con el pago aun en camino —transferencia,
     * domiciliacion—. Entregar ahi seria entregar sin cobrar.
     */
    it('no entrega si la sesion se completa sin pagar', function () {
        entregar(evento('checkout.session.completed', ['payment_status' => 'unpaid']))
            ->assertNoContent();

        expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)
            ->and($this->pago->fresh()->status)->toBe(PaymentStatus::Pending)
            // El evento si queda atendido: no hay nada mas que hacer con el.
            ->and(WebhookEvent::query()->sole()->yaProcesado())->toBeTrue();
    });

    /**
     * Y cuando el pago diferido se confirma dias despues, entrega. Sin este
     * evento, una transferencia no se entregaria nunca.
     */
    it('entrega cuando el pago diferido se confirma', function () {
        entregar(evento('checkout.session.completed', ['payment_status' => 'unpaid'], 'evt_1'));
        entregar(evento('checkout.session.async_payment_succeeded', id: 'evt_2'))->assertNoContent();

        expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
            ->and($this->pago->fresh()->status)->toBe(PaymentStatus::Succeeded);
    });

    it('marca el cobro fallido si el pago diferido se rechaza', function () {
        entregar(evento('checkout.session.async_payment_failed'))->assertNoContent();

        expect($this->pago->fresh()->status)->toBe(PaymentStatus::Failed)
            ->and($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    });
});

it('cancela el cobro si la sesion caduca', function () {
    entregar(evento('checkout.session.expired', ['status' => 'expired', 'payment_status' => 'unpaid']))
        ->assertNoContent();

    expect($this->pago->fresh()->status)->toBe(PaymentStatus::Cancelled)
        // El pedido sigue en pie: el cliente puede volver a intentarlo.
        ->and($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

/**
 * Que Stripe diga «pagaron» no basta: hay que comprobar que pagaron lo que
 * costaba. Sin esto, una sesion manipulada o un cruce de entornos podria
 * entregar un pedido de 45 EUR cobrando 1.
 */
it('no entrega si el importe cobrado no es el del pedido', function () {
    entregar(evento('checkout.session.completed', ['amount_total' => 100]))->assertStatus(500);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($this->pago->fresh()->status)->toBe(PaymentStatus::Pending);

    $registro = WebhookEvent::query()->sole();

    // Sin marcar como atendido, para que el reintento de Stripe vuelva a
    // entrar, y con el motivo guardado para poder mirarlo.
    expect($registro->yaProcesado())->toBeFalse()
        ->and($registro->error)->toContain('no cuadra');
});

it('no entrega si la moneda no es la del cobro', function () {
    entregar(evento('checkout.session.completed', ['currency' => 'usd']))->assertStatus(500);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('deja rastro si no conoce la sesion', function () {
    entregar(evento('checkout.session.completed', ['id' => 'cs_test_desconocida']))
        ->assertStatus(500);

    expect(WebhookEvent::query()->sole()->error)->toContain('cs_test_desconocida')
        ->and($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

/** Stripe manda mas de lo que nos interesa. */
it('guarda y da por atendido un evento que no usa', function () {
    entregar(evento('payment_intent.created'))->assertNoContent();

    expect(WebhookEvent::query()->sole()->yaProcesado())->toBeTrue()
        ->and($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

/**
 * Las devoluciones se hacen desde el panel de Stripe. Lo que no puede pasar
 * es que nuestra tabla siga diciendo «cobrado».
 */
it('marca el cobro como devuelto', function () {
    entregar(evento('checkout.session.completed', id: 'evt_1'));

    entregar([
        'id' => 'evt_2',
        'object' => 'event',
        'api_version' => '2026-07-29.dahlia',
        'created' => time(),
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id' => 'ch_test_1',
            'object' => 'charge',
            'payment_intent' => 'pi_test_webhook',
            'refunded' => true,
        ]],
    ])->assertNoContent();

    expect($this->pago->fresh()->status)->toBe(PaymentStatus::Refunded);
});

/** La ruta no lleva `auth:sanctum` ni CSRF: Stripe no tiene cuenta aqui. */
it('no exige sesion ni token CSRF', function () {
    expect(auth()->check())->toBeFalse();

    entregar(evento('checkout.session.completed'))->assertNoContent();
});
