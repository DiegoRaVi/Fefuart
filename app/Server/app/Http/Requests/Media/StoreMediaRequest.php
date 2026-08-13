<?php

namespace App\Http\Requests\Media;

use App\Rules\DecodableImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * Primera linea de SEC-014. La segunda —la que de verdad protege— es la
 * re-encodificacion de MediaStorageService: esta validacion la pasa entera
 * un JPEG valido con bytes pegados detras.
 */
class StoreMediaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::image()
                    // GIF fuera: v1 lo admitia y no aporta nada como foto de
                    // partida. WebP dentro, que es lo que sale de un movil
                    // moderno.
                    ->types(['jpeg', 'jpg', 'png', 'webp'])
                    ->max(5 * 1024),
                // `mimes:` se fia de lo que declara el cliente. Esta mira los
                // bytes, que es lo unico que despues va a poder decodificar
                // MediaStorageService.
                new DecodableImage,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Hace falta una imagen.',
        ];
    }
}
