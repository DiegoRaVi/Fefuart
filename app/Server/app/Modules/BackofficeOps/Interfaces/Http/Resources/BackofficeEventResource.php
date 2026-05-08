<?php

namespace App\Modules\BackofficeOps\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackofficeEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'phone' => $this->phone,
            'date' => $this->date,
            'location' => $this->location,
            'schedule' => $this->schedule,
            'status' => $this->status,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
