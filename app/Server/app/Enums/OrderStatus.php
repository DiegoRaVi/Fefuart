<?php

namespace App\Enums;

/**
 * Estados de un pedido. **No hay fase de boceto** (D19): el cliente encarga,
 * paga y recibe la obra terminada.
 *
 * En v1 el estado lo decidia el navegador —«Pagar» era un `PATCH` con
 * `{status:'pending'}` y el servidor aceptaba cualquier valor del enum,
 * `paid` incluido (SEC-003)—. Aqui las transiciones validas se declaran una
 * sola vez y se comprueban en los Services, nunca en el controller.
 */
enum OrderStatus: string
{
    case Cart = 'cart';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case InProgress = 'in_progress';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Cart => [self::PendingPayment, self::Cancelled],
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::InProgress, self::Cancelled],
            // Un pedido digital no se envia: de en curso pasa a completado.
            self::InProgress => [self::Shipped, self::Completed, self::Cancelled],
            self::Shipped => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $destino): bool
    {
        return in_array($destino, $this->allowedTransitions(), strict: true);
    }

    /**
     * N12 — el cliente cancela solo antes de pagar. Despues se acuerda con la
     * artista y lo aplica ella desde el backoffice. Quien puede hacerlo es
     * cosa de la Policy; esto solo dice desde donde es posible.
     */
    public function isCancellableByCustomer(): bool
    {
        return in_array($this, [self::Cart, self::PendingPayment], strict: true);
    }

    /**
     * Un pedido en el carrito todavia no existe como pedido: no se lista en
     * «mis pedidos» ni cuenta para el backoffice.
     */
    public function isPlaced(): bool
    {
        return $this !== self::Cart;
    }
}
