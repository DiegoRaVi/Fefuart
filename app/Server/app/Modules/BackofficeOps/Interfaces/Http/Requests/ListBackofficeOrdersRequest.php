<?php

namespace App\Modules\BackofficeOps\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListBackofficeOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:cart,pending,paid,shipped,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
