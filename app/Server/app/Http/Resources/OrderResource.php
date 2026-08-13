<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ARCH-005 — v1 devolvia el modelo Eloquent crudo.
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'subtotal' => $this->subtotal,
            'shipping_total' => $this->shipping_total,
            'total' => $this->total,
            'shipping_method' => ShippingMethodResource::make($this->whenLoaded('shippingMethod')),
            'placed_at' => $this->placed_at?->toAtomString(),
            'shipping_address' => [
                'name' => $this->shipping_name,
                'phone' => $this->shipping_phone,
                'line1' => $this->shipping_line1,
                'line2' => $this->shipping_line2,
                'city' => $this->shipping_city,
                'province' => $this->shipping_province,
                'postal_code' => $this->shipping_postal_code,
                'country' => $this->shipping_country,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
