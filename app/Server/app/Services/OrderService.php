<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderStatusChanged;
use Illuminate\Validation\ValidationException;

/**
 * El avance de un pedido por su maquina de estados.
 *
 * Existe por lo que dice §4 del roadmap: «cada transicion permitida se
 * declara en el enum y se comprueba en Services, nunca en el controller».
 * Estaba en `Admin\OrderController` porque hasta ahora cambiar de estado era
 * comprobar y guardar, dos lineas que no pedian un servicio. Deja de serlo
 * en cuanto avanzar el pedido tambien avisa al cliente: eso no puede vivir
 * en un controller, y menos duplicado en cada sitio que mueva un pedido.
 *
 * Que **no** esta aqui: el paso a `paid`. Ese lo da el webhook y solo el
 * webhook (D29), y tiene su propia comprobacion en `StripeWebhookService`.
 */
class OrderService
{
    /**
     * SEC-003 — la transicion la valida el enum, tambien para la
     * administradora. En v1 cualquiera mandaba `{"status":"paid"}` a
     * `PATCH /orders/{id}` y el servidor lo aceptaba sin mirar de donde
     * venia el pedido.
     *
     * @throws ValidationException el enum no permite ese salto
     */
    public function cambiarEstado(Order $order, OrderStatus $destino): Order
    {
        if (! $order->status->canTransitionTo($destino)) {
            throw ValidationException::withMessages([
                'status' => "Un pedido en «{$order->status->value}» no puede pasar a «{$destino->value}».",
            ]);
        }

        $order->status = $destino;
        $order->save();

        // D10 — dentro del metodo que hace el cambio y despues del `save()`:
        // el aviso cuenta un hecho consumado, no una intencion.
        $order->user->notify(new OrderStatusChanged($order));

        return $order;
    }
}
