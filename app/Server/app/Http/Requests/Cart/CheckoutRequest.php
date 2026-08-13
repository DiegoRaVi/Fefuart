<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SEC-006 — no hay ningun campo de importe, ni de estado.
 *
 * Los campos de direccion son opcionales aqui a proposito: si el pedido es
 * enteramente digital no hacen falta, y este Form Request no puede saberlo
 * sin mirar el carrito. Quien decide que son obligatorios es CheckoutService,
 * que si lo sabe (N6).
 */
class CheckoutRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shipping_name' => ['nullable', 'string', 'max:255'],
            'shipping_phone' => ['nullable', 'string', 'max:30'],
            'shipping_line1' => ['nullable', 'string', 'max:255'],
            'shipping_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_province' => ['nullable', 'string', 'max:100'],
            'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_country' => ['nullable', 'string', 'size:2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shipping_name' => 'nombre',
            'shipping_phone' => 'telefono',
            'shipping_line1' => 'direccion',
            'shipping_city' => 'ciudad',
            'shipping_province' => 'provincia',
            'shipping_postal_code' => 'codigo postal',
            'shipping_country' => 'pais',
        ];
    }
}
