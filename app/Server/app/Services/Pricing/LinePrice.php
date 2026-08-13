<?php

namespace App\Services\Pricing;

/**
 * Lo que CartService copia en la linea de pedido. Los tres importes salen
 * del catalogo y del calculo del servidor; ninguno viene de la peticion
 * (SEC-006).
 */
final readonly class LinePrice
{
    public function __construct(
        public string $unitPrice,
        public string $additionalCopyPrice,
        public string $lineTotal,
    ) {}
}
