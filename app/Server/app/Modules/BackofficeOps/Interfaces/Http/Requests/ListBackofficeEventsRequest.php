<?php

namespace App\Modules\BackofficeOps\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListBackofficeEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,confirmed,rejected,done'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
