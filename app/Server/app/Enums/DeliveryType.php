<?php

namespace App\Enums;

/**
 * Como se entrega una linea del pedido.
 *
 * Los valores coinciden con `shipping_methods.code` a proposito: el metodo
 * de envio del pedido y el tipo de entrega de cada linea hablan el mismo
 * idioma, y N6 —un pedido paga envio fisico si contiene al menos una linea
 * fisica— se resuelve comparando enums, no cadenas sueltas.
 */
enum DeliveryType: string
{
    case Physical = 'physical';
    case Digital = 'digital';

    /**
     * N3/D26 — la cantidad son copias de la misma lamina. En digital eso no
     * significa nada: es el mismo fichero, asi que la linea se limita a una.
     */
    public function allowsMultipleCopies(): bool
    {
        return $this === self::Physical;
    }
}
