<?php

namespace App\Http\Requests\Admin;

use App\Rules\DecodableImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * D33 — una pieza nueva de la galeria.
 *
 * La categoria se acota aqui y no con una clave foranea a `products`: lo que
 * se enseña no tiene por que coincidir con lo que se vende. «Papeleria» es el
 * caso que lo demuestra — invitaciones y seating plans que no estan en el
 * catalogo y que si traen encargos.
 */
class StoreGalleryPieceRequest extends FormRequest
{
    /** @var list<string> */
    public const CATEGORIAS = ['live-art', 'dibujo', 'letras', 'ramos', 'papeleria'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::image()->types(['jpeg', 'jpg', 'png', 'webp'])->max(40 * 1024),
                // Mira los bytes, no la cabecera que mando el cliente.
                new DecodableImage,
            ],
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(self::CATEGORIAS)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'imagen',
            'title' => 'titulo',
            'category' => 'categoria',
            'description' => 'descripcion',
        ];
    }
}
