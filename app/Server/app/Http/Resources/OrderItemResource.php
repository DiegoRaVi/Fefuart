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
        ];
    }
}
