<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El precio viaja hacia el cliente para que lo muestre, nunca para que lo
 * devuelva: el carrito recibe `variant_id` y lo vuelve a calcular en
 * servidor (SEC-006).
 *
 * @mixin \App\Models\ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'additional_copy_price' => $this->additional_copy_price,
            // N7 — que entregas admite esta variante.
            'shipping_methods' => ShippingMethodResource::collection(
                $this->whenLoaded('shippingMethods')
            ),
        ];
    }
}
