<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * N4 — los dos precios de la variante. `additional_copy_price` puede ser 0,
 * pero no puede faltar: si faltara, una copia adicional saldria gratis por
 * omision, y los valores por defecto silenciosos son justo lo que produjo
 * SEC-001.
 */
class StoreVariantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'price' => ['required', 'decimal:0,2', 'min:0', 'max:999999'],
            'additional_copy_price' => ['required', 'decimal:0,2', 'min:0', 'max:999999'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
            // N7 — que entregas admite. Sin ninguna, la variante no se puede
            // encargar, asi que se exige al menos una.
            'shipping_method_ids' => ['required', 'array', 'min:1'],
            'shipping_method_ids.*' => ['integer', 'exists:shipping_methods,id'],
        ];
    }
}
