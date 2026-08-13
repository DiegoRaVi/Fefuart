<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ARCH-005 — v1 devolvia el modelo Eloquent crudo y el contrato de la API
 * quedaba atado al esquema.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            // N9 — el formulario de encargo necesita saberlo para exigir la
            // foto antes de enviar; el servidor lo vuelve a comprobar.
            'requires_reference_image' => $this->requires_reference_image,
            'requires_notes' => $this->requires_notes,
            'max_quantity' => $this->max_quantity,
            'delivery_days' => $this->delivery_days,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
