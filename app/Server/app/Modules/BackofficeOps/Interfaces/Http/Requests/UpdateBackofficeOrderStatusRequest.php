<?php

namespace App\Modules\BackofficeOps\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBackofficeOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,paid,shipped,cancelled'],
        ];
    }
}
