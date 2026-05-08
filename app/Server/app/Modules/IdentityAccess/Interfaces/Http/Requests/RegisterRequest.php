<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:20'],
            'email' => ['required', 'string', 'email', 'min:10', 'max:50', 'unique:users,email'],
            'password' => ['required', 'string', 'min:5', 'confirmed'],
        ];
    }
}
