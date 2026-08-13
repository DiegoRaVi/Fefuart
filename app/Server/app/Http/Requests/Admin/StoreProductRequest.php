<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D5 — el catalogo se gestiona desde el backoffice. La ruta ya va tras
 * `auth:sanctum` y `admin`, asi que aqui solo se valida la forma.
 */
class StoreProductRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', 'unique:products,slug'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
            'requires_reference_image' => ['boolean'],
            'requires_notes' => ['boolean'],
            'max_quantity' => ['integer', 'min:1', 'max:999'],
            'delivery_days' => ['integer', 'min:1', 'max:365'],
        ];
    }
}
