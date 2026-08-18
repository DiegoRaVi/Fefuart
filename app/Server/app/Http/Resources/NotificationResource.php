<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * ARCH-005 — un aviso tal y como lo consume la SPA.
 *
 * `data` llega de la columna JSON que escribio `Aviso::toArray()`, y por eso
 * las claves son siempre las mismas cuatro: la lista se pinta con un solo
 * componente y no con un `switch` por tipo que haya que ampliar cada vez que
 * nazca un aviso nuevo.
 *
 * `enlace` es una ruta relativa de la SPA a proposito, para que la
 * navegacion sea de React Router y no una recarga completa.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $datos = $this->data;

        return [
            'id' => $this->id,
            'tipo' => $datos['tipo'] ?? null,
            'titulo' => $datos['titulo'] ?? null,
            'cuerpo' => $datos['cuerpo'] ?? null,
            'enlace' => $datos['enlace'] ?? null,
            'leido' => $this->read_at !== null,
            'creado_en' => $this->created_at?->toAtomString(),
        ];
    }
}
