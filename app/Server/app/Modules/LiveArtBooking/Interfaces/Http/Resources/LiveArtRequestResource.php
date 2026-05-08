<?php

namespace App\Modules\LiveArtBooking\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveArtRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'phone' => $this->phone,
            'date' => $this->date,
            'location' => $this->location,
            'schedule' => $this->schedule,
            'status' => $this->status,
            'user_id' => (int) $this->user_id,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
