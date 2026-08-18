<?php

namespace App\Enums;

/**
 * Que se esta cobrando.
 *
 * N15 — la reserva de un evento se confirma con una señal, un porcentaje del
 * presupuesto; un pedido de catalogo se paga entero. La misma tabla sirve
 * para los dos porque lo unico que cambia es el importe y a que se engancha.
 */
enum PaymentKind: string
{
    case Full = 'full';
    case Deposit = 'deposit';
}
