<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Payment;

/**
 * D29 — el resguardo del cobro de un pedido.
 *
 * Sale de `StripeWebhookService::cumplir()`, dentro del `if` que marca el
 * cobro como `succeeded`. Ese bloque corre una vez por cobro y jamas dos, de
 * modo que un reenvio de Stripe no produce un segundo correo.
 *
 * **Solo para pedidos.** La señal de un evento no manda este aviso: alli
 * aceptar, pagar y reservar son un solo gesto para el cliente, y lo cuenta
 * `EventConfirmed`.
 *
 * Que salga *antes* de la entrega es deliberado y es D30: si el pedido no
 * puede avanzar, el dinero se ha movido igual y el cliente tiene derecho a
 * saberlo.
 */
class PaymentConfirmed extends Aviso
{
    public function __construct(private readonly Payment $payment) {}

    protected function tipo(): string
    {
        return 'pago_confirmado';
    }

    protected function titulo(): string
    {
        return "Hemos recibido el pago de tu pedido #{$this->pedido()->id}";
    }

    protected function cuerpo(): string
    {
        return sprintf(
            'Tu pago de %s ha entrado correctamente. Ya estamos con tu encargo.',
            $this->euros((string) $this->payment->amount),
        );
    }

    protected function textoDelBoton(): string
    {
        return 'Ver el pedido';
    }

    protected function ruta(): string
    {
        return "/pedidos/{$this->pedido()->id}";
    }

    private function pedido(): Order
    {
        return $this->payment->payable;
    }
}
