<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ARCH-005 — v1 devolvia el modelo Eloquent crudo.
 *
 * @mixin Order
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

            /**
             * SEC-009 — los datos del cliente salen solo para la
             * administradora, y ademas solo si la relacion se ha cargado a
             * proposito. La doble condicion es deliberada: con solo el
             * `whenLoaded`, un eager load despistado en una ruta de cliente
             * publicaria el email de otra persona, que es exactamente lo que
             * hacia `GET /api/user/{id}` en v1.
             */
            'customer' => $this->when(
                $request->user()?->isAdmin() === true && $this->relationLoaded('user'),
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ),
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
            // En los listados basta con cuantas son; las lineas enteras solo
            // se cargan en el detalle.
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
