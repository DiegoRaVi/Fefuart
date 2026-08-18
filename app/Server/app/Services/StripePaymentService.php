<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\Payments\PagoEnCursoException;
use App\Services\Payments\SesionDePago;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * D3, D7 — el unico sitio que habla con la pasarela.
 *
 * Checkout hospedado: el formulario de tarjeta lo sirve Stripe en su propio
 * dominio y nosotros solo abrimos la sesion y recibimos el aviso. Es la
 * opcion que menos superficie deja —ningun dato de tarjeta pasa por aqui ni
 * por nuestro origen en el navegador— y la que menos obligaciones de
 * cumplimiento arrastra.
 *
 * **El cobro no se da por bueno en esta clase.** Quien mueve el pedido a
 * `paid` es el webhook con firma verificada (StripeWebhookService). La pagina
 * de vuelta de Stripe no vale como prueba de pago: es una URL que el cliente
 * puede abrir a mano.
 */
class StripePaymentService
{
    /**
     * Se fija a proposito en vez de dejar la que traiga el SDK. Si manana
     * Stripe cambia su version por defecto, los campos que lee el webhook
     * siguen siendo los mismos.
     */
    public const VERSION_API = '2026-07-29.dahlia';

    private const PROVEEDOR = 'stripe';

    /**
     * Etiqueta el flujo en el panel de Stripe para poder compararlo con otros
     * si algun dia hay mas de uno. El sufijo aleatorio es de la propia
     * convencion de Stripe; no identifica ni al cliente ni al pedido.
     */
    private const ETIQUETA = 'fefuart-hosted-checkout-qmvbdtxk';

    public function __construct(
        private readonly StripeClient $stripe,
        private readonly PricingService $pricing,
    ) {}

    /**
     * Abre —o recupera— la sesion de pago de un pedido de catalogo.
     *
     * @throws PagoEnCursoException el cliente ya pago y falta el webhook
     */
    public function cobrarPedido(Order $order): SesionDePago
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            throw new RuntimeException(
                "No se puede cobrar un pedido en estado {$order->status->value}."
            );
        }

        $order->loadMissing(['items', 'shippingMethod', 'user']);

        return $this->abrirSesion($order, PaymentKind::Full, (string) $order->total, [
            'line_items' => $this->lineasDe($order),
            'shipping_options' => $this->envioDe($order),
            'customer_email' => $order->user->email,
            'success_url' => $this->urlSpa("/pedidos/{$order->id}/pago?sesion={CHECKOUT_SESSION_ID}"),
            'cancel_url' => $this->urlSpa("/pedidos/{$order->id}"),
        ]);
    }

    /**
     * Parte comun a todo cobro: reutilizar lo que ya haya, crear la sesion y
     * guardar el rastro.
     *
     * @param  array<string, mixed>  $parametros  lo que distingue a este cobro
     */
    private function abrirSesion(Model $payable, PaymentKind $kind, string $importe, array $parametros): SesionDePago
    {
        if ($reutilizada = $this->reutilizar($payable, $kind, $importe)) {
            return $reutilizada;
        }

        if ($this->pricing->toCents($importe) <= 0) {
            throw new RuntimeException('No se puede abrir una sesion de pago por 0 EUR.');
        }

        $clave = $this->claveDe($payable, $kind);

        $sesion = $this->stripe->checkout->sessions->create(
            array_filter([
                ...$parametros,
                'mode' => 'payment',

                // La etiqueta de D7 para el panel. Nada de
                // `payment_method_types`: omitirlo es lo que deja a Stripe
                // ofrecer los metodos configurados en el panel y los que
                // encajen con cada cliente.
                'integration_identifier' => self::ETIQUETA,

                // Por donde volver a este pedido si algun dia hay que
                // reconstruir un cobro a mano. El camino normal es por
                // `provider_session_id`, que es unico en nuestra tabla.
                'client_reference_id' => $payable->getMorphClass().':'.$payable->getKey(),
                'metadata' => [
                    'payable_type' => $payable->getMorphClass(),
                    'payable_id' => (string) $payable->getKey(),
                    'kind' => $kind->value,
                ],
            ], fn ($valor) => $valor !== [] && $valor !== null),
            ['idempotency_key' => $clave]
        );

        return new SesionDePago(
            $this->guardar($payable, $kind, $importe, $sesion, $clave),
            (string) $sesion->url
        );
    }

    /**
     * Si ya hay un cobro pendiente para esto mismo, se mira que ha sido de el
     * en Stripe antes de abrir otro. Sin esto, pulsar «Pagar» dos veces
     * dejaria dos sesiones vivas por el mismo pedido.
     */
    private function reutilizar(Model $payable, PaymentKind $kind, string $importe): ?SesionDePago
    {
        $pendiente = Payment::query()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->where('kind', $kind)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($pendiente === null) {
            return null;
        }

        $sesion = $this->stripe->checkout->sessions->retrieve($pendiente->provider_session_id);

        // Pagada, pero el webhook aun no ha llegado. Abrir otra sesion aqui
        // seria cobrar dos veces.
        if ($sesion->status === 'complete') {
            throw new PagoEnCursoException($pendiente);
        }

        if ($sesion->status === 'open') {
            // El importe puede haber cambiado desde el backoffice mientras la
            // pestana seguia abierta (D5). Si ha cambiado, esa sesion cobra un
            // precio que ya no existe y hay que cerrarla en Stripe, no solo en
            // nuestra tabla.
            if ((int) $sesion->amount_total === $this->pricing->toCents($importe)) {
                return new SesionDePago($pendiente, (string) $sesion->url);
            }

            $this->stripe->checkout->sessions->expire($sesion->id);
        }

        $pendiente->status = PaymentStatus::Cancelled;
        $pendiente->failure_reason = "La sesion quedo en «{$sesion->status}» sin cobrar.";
        $pendiente->save();

        return null;
    }

    /**
     * La clave de idempotencia protege por dos lados a la vez: Stripe
     * devuelve la misma sesion si la peticion se repite, y el indice unico de
     * `payments` para al perdedor si dos peticiones simultaneas llegan hasta
     * el insert. Por eso es determinista y no un UUID.
     */
    private function claveDe(Model $payable, PaymentKind $kind): string
    {
        $intento = 1 + Payment::query()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->where('kind', $kind)
            ->count();

        return sprintf('%s:%s:%s:%d', $payable->getMorphClass(), $payable->getKey(), $kind->value, $intento);
    }

    private function guardar(Model $payable, PaymentKind $kind, string $importe, Session $sesion, string $clave): Payment
    {
        // SEC-006 hasta el final: `Payment` no tiene nada asignable en masa,
        // asi que los campos se ponen uno a uno y ninguno viene de fuera.
        $payment = new Payment;
        $payment->payable()->associate($payable);
        $payment->provider = self::PROVEEDOR;
        $payment->provider_session_id = $sesion->id;
        $payment->amount = $importe;
        $payment->currency = 'EUR';
        $payment->status = PaymentStatus::Pending;
        $payment->kind = $kind;
        $payment->idempotency_key = $clave;
        $payment->save();

        return $payment;
    }

    /**
     * N4 — la linea **no** es precio unitario por cantidad: la primera copia
     * paga el trabajo artistico y las siguientes solo la impresion. Por eso va
     * como una sola unidad con el total de la linea ya calculado; dejar que
     * Stripe multiplique daria un importe distinto al del pedido.
     *
     * @return list<array<string, mixed>>
     */
    private function lineasDe(Order $order): array
    {
        return $order->items->map(fn (OrderItem $item) => [
            'quantity' => 1,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $this->pricing->toCents((string) $item->line_total),
                'product_data' => array_filter([
                    'name' => "{$item->product_name} · {$item->variant_name}",
                    'description' => $item->quantity > 1
                        ? "{$item->quantity} copias de la misma lamina"
                        : null,
                ]),
            ],
        ])->values()->all();
    }

    /**
     * N5 — el envio se cobra una vez por pedido. Va como tarifa de envio y no
     * como una linea mas para que en la pagina de Stripe se lea como lo que
     * es. Si todo el pedido es digital no hay envio y el array va vacio (N6).
     *
     * @return list<array<string, mixed>>
     */
    private function envioDe(Order $order): array
    {
        $centimos = $this->pricing->toCents((string) $order->shipping_total);

        if ($centimos === 0) {
            return [];
        }

        return [[
            'shipping_rate_data' => [
                'type' => 'fixed_amount',
                'display_name' => $order->shippingMethod?->name ?? 'Envio',
                'fixed_amount' => ['amount' => $centimos, 'currency' => 'eur'],
            ],
        ]];
    }

    private function urlSpa(string $ruta): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$ruta;
    }
}
