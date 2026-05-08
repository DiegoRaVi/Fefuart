<?php

namespace App\Modules\Catalog\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'string', 'max:100'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'delivery_type' => ['sometimes', 'in:digital,physical'],
            'delivery_time' => ['sometimes', 'string', 'max:100'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
