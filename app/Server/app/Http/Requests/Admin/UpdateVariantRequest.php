<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVariantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'price' => ['sometimes', 'decimal:0,2', 'min:0', 'max:999999'],
            'additional_copy_price' => ['sometimes', 'decimal:0,2', 'min:0', 'max:999999'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
            'shipping_method_ids' => ['sometimes', 'array', 'min:1'],
            'shipping_method_ids.*' => ['integer', 'exists:shipping_methods,id'],
        ];
    }
}
