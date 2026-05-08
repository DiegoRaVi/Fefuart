<?php

namespace App\Modules\OrderManagement\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'order_date' => $this->order_date,
            'status' => $this->status,
            'address' => $this->address,
            'total' => (float) $this->total,
            'items' => CartItemResource::collection($this->whenLoaded('products')),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
