<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * D20, N11 — el archivo final de un encargo digital.
 *
 * La lista blanca es corta a proposito: JPEG y PNG para una lamina, PDF para
 * lo que se manda a imprenta. Nada mas. Cada formato que se anada aqui es un
 * formato que despues hay que servir sin que el navegador lo interprete.
 *
 * **`File::types()` mira el contenido, no la extension.** Laravel resuelve el
 * MIME real con `finfo` sobre los bytes del fichero temporal, asi que un
 * `.html` renombrado a `.png` se cae aqui. Es lo que hace que la decision de
 * **no** re-encodificar (ver `MediaStorageService::storeDelivery`) siga
 * siendo segura.
 */
class StoreDeliveryRequest extends FormRequest
{
    /** Lo que hoy permite PHP: `upload_max_filesize` esta en 40M. */
    private const MAX_KB = 40 * 1024;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['jpeg', 'jpg', 'png', 'pdf'])->max(self::MAX_KB),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Hace falta el archivo de la entrega.',
            'file.max' => 'El archivo no puede pasar de 40 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => 'archivo'];
    }
}
