<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * D10 — el pedido ha avanzado y el cliente se entera sin volver a entrar.
 *
 * Sale de `OrderService::cambiarEstado()`, que es por donde pasan las
 * transiciones que hace la artista. **No sale del paso a `paid` del
 * webhook**, que tiene ruta propia y no pasa por ahi: ese hecho lo cuenta
 * `PaymentConfirmed`, y contarlo dos veces seria dos correos para lo mismo.
 */
class OrderStatusChanged extends Aviso
{
    public function __construct(private readonly Order $order) {}

    protected function tipo(): string
    {
        return 'estado_del_pedido';
    }

    protected function titulo(): string
    {
        return "Tu pedido #{$this->order->id} ha cambiado de estado";
    }

    /**
     * El texto se escribe desde el punto de vista del cliente, no con el
     * nombre interno del estado: «in_progress» no le dice nada a nadie.
     */
    protected function cuerpo(): string
    {
        return match ($this->order->status) {
            OrderStatus::Paid => 'Hemos registrado el pago de tu pedido.',
            OrderStatus::InProgress => 'Felicitas ya esta dibujando tu encargo.',
            OrderStatus::Shipped => 'Tu pedido va camino de casa.',
            OrderStatus::Completed => 'Tu pedido esta completado. Gracias por confiar en Fefuart.',
            OrderStatus::Cancelled => 'Tu pedido se ha cancelado.',
            default => 'Tu pedido ha cambiado de estado.',
        };
    }

    protected function textoDelBoton(): string
    {
        return 'Ver el pedido';
    }

    protected function ruta(): string
    {
        return "/pedidos/{$this->order->id}";
    }
}
