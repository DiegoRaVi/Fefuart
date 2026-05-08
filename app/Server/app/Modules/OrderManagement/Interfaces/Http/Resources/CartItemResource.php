<?php

namespace App\Modules\OrderManagement\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'quantity' => (int) $this->quantity,
            'description' => $this->description,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'delivery_type' => $this->delivery_type,
            'delivery_time' => $this->delivery_time,
            'image_url' => $this->image_url,
            'stock' => $this->stock !== null ? (int) $this->stock : null,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
