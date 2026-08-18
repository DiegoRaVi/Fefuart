<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

/**
 * SEC-003 — el pedido de cada cual.
 *
 * En v1 `PATCH /api/orders/{id}` estaba bajo `IsUserAuth` y la comprobacion
 * de autorizacion estaba **comentada** (`OrderController.php:223-227`), asi
 * que cualquier cuenta autenticada movia el estado, el total y la direccion
 * de cualquier pedido. Y donde si habia comprobacion, era esta:
 *
 *     if ($user->role !== 'admin' && $user->role !== $order->user_id)
 *
 * que compara un rol con un id (BUG-004), de modo que un cliente normal
 * nunca podia ver su propio pedido: siempre 403.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $this->owns($user, $order) || $user->isAdmin();
    }

    /**
     * Tocar el carrito: anadir, cambiar o quitar lineas. Solo su duenno, y
     * solo mientras siga siendo un carrito — despues de encargar, el pedido
     * ya no se edita desde el cliente.
     *
     * La administradora no entra aqui: no encarga en nombre de nadie.
     */
    public function updateCart(User $user, Order $order): bool
    {
        return $this->owns($user, $order) && $order->status->isCancellableByCustomer();
    }

    /**
     * Abrir la sesion de pago. Solo su duenno y solo mientras el pedido
     * espere cobro: pagar dos veces el mismo pedido no es un caso de uso.
     *
     * La administradora no entra: no paga en nombre de nadie, y si algun dia
     * cobra por otra via lo hara desde el panel de Stripe.
     */
    public function pay(User $user, Order $order): bool
    {
        return $this->owns($user, $order) && $order->status === OrderStatus::PendingPayment;
    }

    /**
     * N12 — el cliente cancela solo antes de pagar. Una vez pagado se acuerda
     * con la artista y lo aplica ella desde el backoffice.
     */
    public function cancel(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->owns($user, $order) && $order->status->isCancellableByCustomer();
    }

    private function owns(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
}
