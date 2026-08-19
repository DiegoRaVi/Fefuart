<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Los importes que salen aqui son los del snapshot: lo que se cobro, no lo
 * que el catalogo valga hoy.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name,
            'variant_name' => $this->variant_name,
            'delivery_type' => $this->delivery_type->value,
            'quantity' => $this->quantity,
            'customer_notes' => $this->customer_notes,
            'unit_price' => $this->unit_price,
            'additional_copy_price' => $this->additional_copy_price,
            'line_total' => $this->line_total,
            'reference_media' => MediaAssetResource::make($this->whenLoaded('referenceMedia')),

            /*
             * N11 — si esta linea ya se puede descargar.
             *
             * Un booleano y **no** el media: la ruta del fichero vive en el
             * disco privado y no tiene por que viajar a ningun navegador, ni
             * siquiera al del dueño. Para descargarlo esta el endpoint, que
             * comprueba el permiso; publicar aqui la ruta seria dar la mitad
             * del camino a quien no debe recorrerlo.
             */
            'delivered' => $this->delivered_media_id !== null,
        ];
    }
}
