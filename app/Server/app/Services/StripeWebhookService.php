<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\OrderPaid;
use App\Notifications\PaymentConfirmed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Event as StripeEvent;
use Throwable;

/**
 * Lo que Stripe nos cuenta, convertido en estado del negocio.
 *
 * **Este es el unico sitio donde un pedido pasa a `paid`.** La pagina de
 * vuelta de Stripe no vale como prueba: es una URL que el cliente puede
 * abrir a mano, sin haber pagado. La firma del webhook si es una prueba, y
 * se verifica antes de llegar aqui (StripeWebhookController).
 *
 * La garantia de Stripe es «al menos una vez»: el mismo evento puede llegar
 * varias veces, y llega seguro si tardamos en responder 2xx. De ahi que todo
 * lo de aqui se pueda repetir sin consecuencias.
 */
class StripeWebhookService
{
    private const PROVEEDOR = 'stripe';

    public function __construct(
        private readonly PricingService $pricing,
        private readonly QuoteService $presupuestos,
    ) {}

    public function procesar(StripeEvent $evento): void
    {
        $registro = $this->registrar($evento);

        // Ya atendido en una entrega anterior. No es un error: es Stripe
        // reintentando porque no le llego nuestro 2xx a tiempo.
        if ($registro === null) {
            return;
        }

        try {
            $this->despachar($evento);

            $registro->processed_at = now();
            $registro->error = null;
            $registro->save();
        } catch (Throwable $e) {
            // Se guarda el motivo y **no** se marca como procesado. Despues se
            // propaga a proposito: el controller responde 500 y Stripe
            // reintenta con espera creciente hasta tres dias. Responder 2xx
            // aqui dejaria el evento muerto con un error que nadie ha visto.
            $registro->error = $e->getMessage();
            $registro->save();

            throw $e;
        }
    }

    /**
     * Guarda el evento tal cual llego y devuelve null si ya estaba atendido.
     *
     * El `lockForUpdate` es para dos entregas simultaneas del mismo evento:
     * sin el, las dos leerian `processed_at` a null y las dos entregarian.
     */
    private function registrar(StripeEvent $evento): ?WebhookEvent
    {
        return DB::transaction(function () use ($evento) {
            $existente = WebhookEvent::query()
                ->where('provider', self::PROVEEDOR)
                ->where('provider_event_id', $evento->id)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                return $existente->yaProcesado() ? null : $existente;
            }

            // `$fillable` esta vacio a proposito: nada de esta tabla se
            // rellena en masa desde una peticion.
            $registro = new WebhookEvent;
            $registro->provider = self::PROVEEDOR;
            $registro->provider_event_id = $evento->id;
            $registro->type = $evento->type;
            $registro->payload = $evento->toArray();
            $registro->save();

            return $registro;
        });
    }

    private function despachar(StripeEvent $evento): void
    {
        $objeto = $evento->data->object;

        if ($objeto instanceof Charge && $evento->type === 'charge.refunded') {
            $this->devolver($objeto);

            return;
        }

        // Stripe manda mas de lo que nos interesa. Lo demas se guarda y se
        // marca como atendido para que no vuelva.
        if (! $objeto instanceof Session) {
            return;
        }

        if ($evento->type === 'checkout.session.completed') {
            // `payment_status` es lo que decide: una sesion puede completarse
            // con el pago aun en camino, y entregar ahi seria entregar sin
            // cobrar. Cuando se confirme llegara el evento asincrono de abajo.
            if ($objeto->payment_status !== 'unpaid') {
                $this->cumplir($objeto);
            }

            return;
        }

        // Metodos diferidos. Sin este par, un pago por transferencia o
        // domiciliacion no se entregaria nunca: su sesion se completa como
        // `unpaid` y solo se resuelve dias despues.
        if ($evento->type === 'checkout.session.async_payment_succeeded') {
            $this->cumplir($objeto);

            return;
        }

        if ($evento->type === 'checkout.session.async_payment_failed') {
            $this->fallar($objeto);

            return;
        }

        if ($evento->type === 'checkout.session.expired') {
            $this->caducar($objeto);
        }
    }

    /**
     * El cobro se da por bueno y el pedido avanza. Es la unica transicion a
     * `paid` del sistema.
     */
    private function cumplir(Session $sesion): void
    {
        $payment = $this->pagoDe($sesion->id);

        if ($payment->status !== PaymentStatus::Succeeded) {
            $this->guardImporte($payment, (int) $sesion->amount_total, (string) $sesion->currency);

            $payment->status = PaymentStatus::Succeeded;
            $payment->provider_payment_intent_id = $this->idDelIntent($sesion);
            $payment->paid_at = now();
            $payment->failure_reason = null;
            $payment->save();

            $this->avisarDelCobro($payment);
        }

        /*
         * La entrega va fuera del guardado del cobro y se reintenta siempre,
         * tambien cuando el cobro ya constaba.
         *
         * Meterlas en la misma transaccion seria peor: si la entrega falla
         * —el caso real es la colision de franja de N16—, el rollback
         * borraria tambien el «cobrado», y el dinero se habria movido de
         * verdad. Asi el cobro queda guardado, el evento se queda donde
         * estaba y el motivo aparece en `webhook_events.error`.
         */
        $this->entregar($payment);
    }

    /**
     * D10 — el resguardo del cobro, dentro del `if` que lo guarda.
     *
     * **El sitio es la garantia.** Ese bloque corre una vez por cobro y
     * jamas dos, asi que un reenvio de Stripe —o una reentrega despues de
     * que la entrega fallara, que es el caso de D30— no produce un segundo
     * correo. Encolarlo un nivel mas arriba, en `procesar()` o en
     * `despachar()`, si lo produciria.
     *
     * Solo para pedidos: la señal de un evento la cuenta `EventConfirmed`,
     * porque aceptar, pagar y reservar son un solo gesto para el cliente.
     */
    private function avisarDelCobro(Payment $payment): void
    {
        $payable = $payment->payable;

        if ($payable instanceof Order) {
            $payable->user->notify(new PaymentConfirmed($payment));

            // Y a quien tiene que ponerse a dibujar.
            Notification::send(User::admins()->get(), new OrderPaid($payment));
        }
    }

    /**
     * Lo que el cobro desbloquea. Cada tipo de payable sabe a que estado
     * pasa; lo que no cambia es que **solo** se llega aqui con un pago
     * verificado.
     */
    private function entregar(Payment $payment): void
    {
        $payable = $payment->payable;

        if ($payable instanceof Order) {
            if ($payable->status === OrderStatus::Paid) {
                return;
            }

            if (! $payable->status->canTransitionTo(OrderStatus::Paid)) {
                throw new RuntimeException(
                    "El pedido {$payable->id} esta en «{$payable->status->value}» y no puede pasar a pagado."
                );
            }

            $payable->status = OrderStatus::Paid;
            $payable->save();

            return;
        }

        // N15 — la señal cobrada es lo que confirma la reserva.
        if ($payable instanceof Event) {
            try {
                $this->presupuestos->confirmarPorSenal($payable);
            } catch (QueryException $e) {
                /*
                 * N16 por la via de la base de datos: otro evento se confirmo
                 * en esa misma fecha y franja mientras este pagaba.
                 *
                 * QuoteService lo comprueba al aceptar el presupuesto, asi
                 * que para llegar aqui hacen falta dos clientes pagando la
                 * misma franja a la vez. Es raro y no es imposible, y lo que
                 * no se puede hacer es entregar igual. El cobro queda
                 * guardado —el dinero se movio— y hay que devolverlo a mano
                 * desde el panel de Stripe.
                 */
                throw new RuntimeException(sprintf(
                    'La señal del evento %d se cobro pero la franja del %s ya estaba ocupada. '
                    .'Hay que devolver el cobro %d desde el panel de Stripe.',
                    $payable->id,
                    $payable->event_date->format('d/m/Y'),
                    $payment->id
                ), previous: $e);
            }
        }
    }

    private function fallar(Session $sesion): void
    {
        $payment = $this->pagoDe($sesion->id);

        if ($payment->status->esDefinitivo()) {
            return;
        }

        $payment->status = PaymentStatus::Failed;
        $payment->provider_payment_intent_id = $this->idDelIntent($sesion);
        $payment->failure_reason = 'El pago diferido fue rechazado.';
        $payment->save();
    }

    /** El cliente cerro la pagina de pago o la sesion llego a su limite. */
    private function caducar(Session $sesion): void
    {
        $payment = $this->pagoDe($sesion->id);

        if ($payment->status->esDefinitivo()) {
            return;
        }

        $payment->status = PaymentStatus::Cancelled;
        $payment->failure_reason = 'La sesion de pago caduco sin cobrarse.';
        $payment->save();
    }

    /**
     * Las devoluciones se hacen desde el panel de Stripe, no desde aqui: son
     * decision de la artista y no hay ningun endpoint que las dispare. Lo que
     * si hace falta es que nuestra tabla no se quede diciendo «cobrado».
     */
    private function devolver(Charge $cargo): void
    {
        $payment = Payment::query()
            ->where('provider', self::PROVEEDOR)
            ->where('provider_payment_intent_id', $cargo->payment_intent)
            ->first();

        if ($payment === null || $payment->status === PaymentStatus::Refunded) {
            return;
        }

        $payment->status = PaymentStatus::Refunded;
        $payment->save();
    }

    private function pagoDe(string $sesionId): Payment
    {
        $payment = Payment::query()
            ->where('provider', self::PROVEEDOR)
            ->where('provider_session_id', $sesionId)
            ->first();

        if ($payment === null) {
            // Pasa si el webhook apunta a otro entorno —el mismo `stripe
            // listen` sirviendo a dos maquinas— o si alguien borro la fila.
            // Se deja como error sin procesar para poder mirarlo.
            throw new RuntimeException("No hay ningun cobro con la sesion {$sesionId}.");
        }

        return $payment;
    }

    /**
     * Que lo cobrado sea lo que guardamos. Si no cuadra, no se entrega nada:
     * es la comprobacion que convierte «Stripe dice que pagaron» en «pagaron
     * lo que costaba».
     */
    private function guardImporte(Payment $payment, int $cobrado, string $moneda): void
    {
        $esperado = $this->pricing->toCents((string) $payment->amount);

        if ($cobrado !== $esperado || strtoupper($moneda) !== strtoupper((string) $payment->currency)) {
            throw new RuntimeException(sprintf(
                'El cobro %d no cuadra: esperabamos %d %s y Stripe informa de %d %s.',
                $payment->id,
                $esperado,
                $payment->currency,
                $cobrado,
                strtoupper($moneda)
            ));
        }
    }

    /**
     * Con Checkout hospedado el intent llega como id; expandido, como objeto.
     * Se admiten los dos para no depender de con que parametros se pidio.
     */
    private function idDelIntent(Session $sesion): ?string
    {
        $intent = $sesion->payment_intent;

        if (is_string($intent)) {
            return $intent;
        }

        return $intent?->id;
    }
}
