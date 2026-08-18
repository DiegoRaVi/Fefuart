<?php

namespace App\Models;

use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que nos ha mandado la pasarela, tal cual llego.
 *
 * Existe para no procesar dos veces lo mismo: Stripe reenvia cuando no
 * recibe un 2xx a tiempo, y su garantia es «al menos una vez».
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function yaProcesado(): bool
    {
        return $this->processed_at !== null;
    }
}
