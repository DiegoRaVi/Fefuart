<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Una sesion de pago abierta: el cobro que hemos guardado y la direccion a
 * la que hay que mandar al cliente.
 *
 * La URL no se persiste a proposito. Caduca, y guardarla obligaria a
 * mantener sincronizado algo que Stripe ya sabe: al reutilizar una sesion
 * se vuelve a pedir con `retrieve`.
 */
final readonly class SesionDePago
{
    public function __construct(
        public Payment $payment,
        public string $url,
    ) {}
}
