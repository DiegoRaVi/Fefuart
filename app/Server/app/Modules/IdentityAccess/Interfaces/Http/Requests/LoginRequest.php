<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'min:10', 'max:50'],
            'password' => ['required', 'string', 'min:5'],
        ];
    }
}
