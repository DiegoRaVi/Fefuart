<?php

namespace App\Services\Pricing;

/**
 * Los importes del pedido. `shippingMethodId` es el metodo que se acaba
 * cobrando, que se deriva de las lineas y no lo elige el cliente (N6).
 */
final readonly class OrderTotals
{
    public function __construct(
        public string $subtotal,
        public string $shippingTotal,
        public string $total,
        public ?int $shippingMethodId,
    ) {}
}
