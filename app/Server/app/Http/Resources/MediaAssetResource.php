<?php

namespace App\Http\Resources;

use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin MediaAsset
 */
class MediaAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            // `user_id` y `path` no salen: el primero no le importa a nadie
            // fuera del servidor y el segundo se expone ya resuelto en url.
            'url' => $this->when(
                $this->visibility === 'public',
                fn () => Storage::disk('public')->url($this->path),
            ),
        ];
    }
}
