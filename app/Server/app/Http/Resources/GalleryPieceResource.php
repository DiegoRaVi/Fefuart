<?php

namespace App\Http\Resources;

use App\Models\GalleryPiece;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ARCH-005 — una pieza de la galeria tal y como la consume la SPA.
 *
 * Las dos imagenes salen siempre: la rejilla usa la miniatura y el visor la
 * grande, y pedirlas por separado obligaria a una segunda vuelta justo cuando
 * el usuario ya esta esperando a ver algo.
 *
 * @mixin GalleryPiece
 */
class GalleryPieceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,
            'image' => MediaAssetResource::make($this->whenLoaded('media')),
            'thumbnail' => MediaAssetResource::make($this->whenLoaded('thumbnail')),
        ];
    }
}
