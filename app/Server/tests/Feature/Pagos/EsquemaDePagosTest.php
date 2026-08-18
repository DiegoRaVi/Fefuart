<?php

use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;

/**
 * D3 — el estado del cobro va aparte del estado del negocio.
 *
 * Mezclarlos es como en v1 «Pagar» acababa siendo un enum que cambiaba solo
 * porque el navegador lo pedia. Un pedido puede estar pagado y su cobro
 * seguir en camino, o el cobro fallar y el pedido tener que quedarse donde
 * estaba.
 */
it('cuelga el cobro del pedido sin tocar su estado', function () {
    $pedido = Order::factory()->placed()->create();

    $pago = Payment::factory()->create([
        'payable_type' => Order::class,
        'payable_id' => $pedido->id,
    ]);

    expect($pago->payable->is($pedido))->toBeTrue()
        ->and($pago->status)->toBe(PaymentStatus::Pending)
        // El pedido sigue donde estaba: el cobro no lo ha movido.
        ->and($pedido->fresh()->status)->toBe($pedido->status);
});

/** N15 — la señal de un evento es un cobro como cualquier otro. */
it('cuelga la señal de un evento', function () {
    $evento = Event::factory()->create();

    $senal = Payment::factory()->create([
        'payable_type' => Event::class,
        'payable_id' => $evento->id,
        'kind' => PaymentKind::Deposit,
        'amount' => '150.00',
    ]);

    expect($senal->payable->is($evento))->toBeTrue()
        ->and($senal->kind)->toBe(PaymentKind::Deposit);
});

/**
 * SEC-006 hasta el final: ningun campo de un cobro puede venir de una
 * peticion. El importe sale del pedido, que a su vez lo calculo
 * PricingService, y el estado solo lo mueve el webhook.
 */
it('no admite nada por asignacion masiva', function () {
    $pago = Payment::factory()->create();

    // Con `$fillable` vacio, Eloquent no ignora en silencio: revienta. Es
    // mejor asi — un cobro asignado en masa es un error de programacion, y
    // enterarse en el momento vale mas que un importe que no cuadra.
    expect(fn () => $pago->fill([
        'amount' => '0.01',
        'status' => PaymentStatus::Succeeded,
        'provider_payment_intent_id' => 'pi_falso',
    ]))->toThrow(MassAssignmentException::class);

    expect($pago->fresh()->amount)->toBe('45.00')
        ->and($pago->fresh()->status)->toBe(PaymentStatus::Pending);
});

describe('no se cobra dos veces por lo mismo', function () {
    /**
     * Si la peticion de creacion se repite —el cliente pulsa dos veces, la
     * red se cae y se reintenta— tiene que quedar un solo cobro.
     */
    it('impide dos cobros con la misma clave de idempotencia', function () {
        Payment::factory()->create(['idempotency_key' => 'pedido-7-intento-1']);

        expect(fn () => Payment::factory()->create([
            'idempotency_key' => 'pedido-7-intento-1',
        ]))->toThrow(QueryException::class);
    });

    it('impide dos cobros para la misma sesion de Stripe', function () {
        Payment::factory()->create(['provider_session_id' => 'cs_test_repetida']);

        expect(fn () => Payment::factory()->create([
            'provider_session_id' => 'cs_test_repetida',
        ]))->toThrow(QueryException::class);
    });

    /**
     * Con Checkout hospedado la sesion existe antes que el PaymentIntent: un
     * cobro abandonado tiene sesion y no tiene intent. Por eso el intent es
     * nulo, y varios nulos no pueden chocar entre si.
     */
    it('admite varios cobros sin PaymentIntent todavia', function () {
        Payment::factory()->count(3)->create();

        expect(Payment::query()->whereNull('provider_payment_intent_id')->count())->toBe(3);
    });

    it('impide dos cobros para el mismo PaymentIntent', function () {
        Payment::factory()->succeeded()->create(['provider_payment_intent_id' => 'pi_repetido']);

        expect(fn () => Payment::factory()->succeeded()->create([
            'provider_payment_intent_id' => 'pi_repetido',
        ]))->toThrow(QueryException::class);
    });
});

/**
 * Stripe reenvia los eventos cuando no recibe un 2xx a tiempo, y su garantia
 * es «al menos una vez», no «exactamente una». Sin esto, un reenvio de un
 * pago confirmado volveria a mover el pedido — y con la señal de un evento,
 * podria llegar a cobrarse dos veces.
 */
describe('el registro de webhooks', function () {
    it('impide guardar dos veces el mismo evento', function () {
        WebhookEvent::factory()->create(['provider_event_id' => 'evt_repetido']);

        expect(fn () => WebhookEvent::factory()->create([
            'provider_event_id' => 'evt_repetido',
        ]))->toThrow(QueryException::class);
    });

    /**
     * Que lo impida el indice unico y no un `if` importa: dos entregas
     * simultaneas del mismo evento pasarian las dos por la comprobacion
     * antes de que ninguna escriba.
     */
    it('distingue haber llegado de haberse atendido', function () {
        $recien = WebhookEvent::factory()->create();
        $hecho = WebhookEvent::factory()->procesado()->create();

        expect($recien->yaProcesado())->toBeFalse()
            ->and($hecho->yaProcesado())->toBeTrue();
    });

    /** Cuando algo no cuadre, lo que importa es lo que llego, no lo que
     *  nosotros entendimos. */
    it('guarda el cuerpo entero tal cual llego', function () {
        $cuerpo = [
            'id' => 'evt_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_1', 'amount_total' => 4500]],
        ];

        $evento = WebhookEvent::factory()->create(['payload' => $cuerpo]);

        expect($evento->fresh()->payload)->toBe($cuerpo);
    });
});
