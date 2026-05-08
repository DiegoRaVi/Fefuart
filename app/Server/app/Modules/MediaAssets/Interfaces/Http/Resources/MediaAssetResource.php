<?php

namespace App\Modules\MediaAssets\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'context_type' => $this->context_type,
            'context_id' => $this->context_id !== null ? (int) $this->context_id : null,
            'disk' => $this->disk,
            'path' => $this->path,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size_bytes' => (int) $this->size_bytes,
            'visibility' => $this->visibility,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
