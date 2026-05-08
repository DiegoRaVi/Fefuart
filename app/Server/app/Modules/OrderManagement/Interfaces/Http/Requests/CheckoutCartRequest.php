<?php

namespace App\Modules\OrderManagement\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'max:255'],
        ];
    }
}
