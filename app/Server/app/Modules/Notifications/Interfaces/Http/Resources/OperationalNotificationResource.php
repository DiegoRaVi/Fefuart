<?php

namespace App\Modules\Notifications\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationalNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'actor_user_id' => $this->actor_user_id !== null ? (int) $this->actor_user_id : null,
            'context_type' => $this->context_type,
            'context_id' => (int) $this->context_id,
            'channel' => $this->channel,
            'title' => $this->title,
            'body' => $this->body,
            'previous_status' => $this->previous_status,
            'new_status' => $this->new_status,
            'payload' => $this->payload,
            'is_read' => $this->read_at !== null,
            'read_at' => optional($this->read_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
