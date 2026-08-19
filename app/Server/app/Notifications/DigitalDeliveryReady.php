<?php

namespace App\Notifications;

use App\Models\OrderItem;

/**
 * D20, N11 — la obra terminada ya se puede descargar.
 *
 * Sin este aviso, la entrega digital seria un fichero que aparece en una
 * pantalla que el cliente no tiene motivo para volver a abrir: encargo,
 * pago y espera ya han pasado. Es el unico momento en que hace falta que
 * vuelva.
 */
class DigitalDeliveryReady extends Aviso
{
    public function __construct(private readonly OrderItem $item) {}

    protected function tipo(): string
    {
        return 'entrega_lista';
    }

    protected function titulo(): string
    {
        return 'Tu encargo ya se puede descargar';
    }

    protected function cuerpo(): string
    {
        return sprintf(
            'Felicitas ha terminado «%s» y ya lo tienes disponible en tu pedido #%d.',
            $this->item->product_name,
            $this->item->order_id,
        );
    }

    /**
     * @return list<string>
     */
    protected function detalles(): array
    {
        return [
            'La descarga esta en el detalle del pedido, y sigue ahi siempre que la necesites.',
        ];
    }

    protected function textoDelBoton(): string
    {
        return 'Descargar mi encargo';
    }

    protected function ruta(): string
    {
        return "/pedidos/{$this->item->order_id}";
    }
}
