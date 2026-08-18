<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Payment;

/**
 * D10 — hay un encargo cobrado esperando a que alguien lo empiece.
 *
 * Sale del mismo `if` que `PaymentConfirmed`, con la misma garantia: ese
 * bloque corre una vez por cobro, asi que un reenvio de Stripe no manda un
 * segundo correo.
 *
 * Solo de pedidos. La señal de un evento no abre trabajo nuevo: la fecha ya
 * estaba en la agenda desde que se presupuesto.
 */
class OrderPaid extends Aviso
{
    public function __construct(private readonly Payment $payment) {}

    protected function tipo(): string
    {
        return 'pedido_pagado';
    }

    protected function titulo(): string
    {
        return "Pedido #{$this->pedido()->id} pagado";
    }

    protected function cuerpo(): string
    {
        return sprintf(
            '%s ha pagado %s. El pedido tiene %d %s.',
            $this->pedido()->user->name,
            $this->euros((string) $this->payment->amount),
            $this->lineas(),
            $this->lineas() === 1 ? 'linea' : 'lineas',
        );
    }

    protected function textoDelBoton(): string
    {
        return 'Ver el pedido';
    }

    protected function ruta(): string
    {
        return "/backoffice/pedidos/{$this->pedido()->id}";
    }

    private function pedido(): Order
    {
        return $this->payment->payable;
    }

    private function lineas(): int
    {
        return $this->pedido()->items()->count();
    }
}
