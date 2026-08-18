<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'stripe',
            'provider_event_id' => 'evt_'.Str::random(24),
            'type' => 'checkout.session.completed',
            'payload' => ['id' => 'evt_x', 'type' => 'checkout.session.completed'],
            'processed_at' => null,
        ];
    }

    public function procesado(): static
    {
        return $this->state(fn () => ['processed_at' => now()]);
    }
}
