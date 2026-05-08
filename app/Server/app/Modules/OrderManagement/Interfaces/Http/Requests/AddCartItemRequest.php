<?php

namespace App\Modules\OrderManagement\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'delivery_type' => ['required', 'in:digital,physical'],
            'delivery_time' => ['required', 'string', 'max:100'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
