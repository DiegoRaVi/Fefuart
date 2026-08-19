<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editar una pieza: su texto y si se enseña. **La imagen no se cambia aqui**
 * — sustituirla es subir otra pieza y quitar esta, y asi el fichero viejo no
 * se queda huerfano por un camino que nadie mira.
 */
class UpdateGalleryPieceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', Rule::in(StoreGalleryPieceRequest::CATEGORIAS)],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
