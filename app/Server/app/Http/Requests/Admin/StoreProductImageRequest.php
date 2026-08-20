<?php

namespace App\Http\Requests\Admin;

use App\Rules\DecodableImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/** La foto de un articulo del catalogo. Misma puerta que el resto de imagenes. */
class StoreProductImageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::image()->types(['jpeg', 'jpg', 'png', 'webp'])->max(40 * 1024),
                // Mira los bytes, no lo que declara el cliente.
                new DecodableImage,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => 'imagen'];
    }
}
