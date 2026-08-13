<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Comprueba que el fichero se puede decodificar **de verdad**, mirando sus
 * bytes y no su extension ni la cabecera `Content-Type` que mando el cliente.
 *
 * Hace falta porque `mimes:` se fia de lo que el cliente declara. Sin esto,
 * un fichero que pasa la validacion pero que GD no sabe abrir llegaba hasta
 * MediaStorageService y salia como un 500 con traza, en vez de como el 422
 * que le corresponde.
 */
class DecodableImage implements ValidationRule
{
    /** Los mismos que acepta la re-encodificacion a JPEG. */
    private const ALLOWED = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('No se ha podido leer el fichero.');

            return;
        }

        $info = @getimagesizefromstring((string) file_get_contents($value->getRealPath()));

        if ($info === false || ! in_array($info[2], self::ALLOWED, strict: true)) {
            $fail('El fichero no es una imagen JPEG, PNG o WebP.');
        }
    }
}
