<?php

namespace App\Enums;

/**
 * Estado de un cobro, que **no** es el estado del pedido.
 *
 * D3 separa las dos cosas a proposito: un pedido puede estar «pagado» y su
 * cobro seguir en camino, o el cobro fallar y el pedido tener que quedarse
 * donde estaba. Mezclarlos es como en v1 «Pagar» acababa siendo un enum que
 * cambiaba solo porque el navegador lo pedia.
 */
enum PaymentStatus: string
{
    /** Creado en Stripe, todavia sin resolver. */
    case Pending = 'pending';

    /** Cobrado. Es el unico estado que mueve el pedido. */
    case Succeeded = 'succeeded';

    case Failed = 'failed';

    /** El cliente abandono la pagina de pago o la sesion caduco. */
    case Cancelled = 'cancelled';

    case Refunded = 'refunded';

    public function esDefinitivo(): bool
    {
        return $this !== self::Pending;
    }
}
