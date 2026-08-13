<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ARCH-005 / SEC-009: v1 devolvia el modelo Eloquent crudo, de modo que el
 * contrato de la API quedaba atado al esquema y cualquier columna nueva se
 * publicaba sola. Aqui los campos expuestos se declaran uno a uno.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // El nombre, nunca el id (D23).
            'role' => $this->role_id->slug(),
            'email_verified_at' => $this->email_verified_at?->toAtomString(),
        ];
    }
}
