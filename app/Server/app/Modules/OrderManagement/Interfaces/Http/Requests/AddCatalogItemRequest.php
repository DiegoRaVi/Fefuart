<?php

namespace App\Modules\OrderManagement\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
