<?php

namespace App\Services\Payments;

use App\Models\Payment;
use RuntimeException;

/**
 * El cliente ya ha pagado y el webhook todavia no ha llegado.
 *
 * Es una carrera normal —el navegador vuelve de Stripe antes que la
 * notificacion— y la respuesta correcta es esperar, no abrir otra sesion de
 * pago. Abrirla seria el camino directo a cobrar dos veces.
 */
class PagoEnCursoException extends RuntimeException
{
    public function __construct(public readonly Payment $payment)
    {
        parent::__construct('El pago ya se ha realizado y se esta confirmando.');
    }
}
