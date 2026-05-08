<?php

namespace App\Modules\LiveArtBooking\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateLiveArtRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'location' => ['required', 'string', 'max:255'],
            'schedule' => ['required', 'in:morning,evening'],
        ];
    }
}
