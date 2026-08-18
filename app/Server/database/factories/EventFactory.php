<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Boda de '.fake()->firstName().' y '.fake()->firstName(),
            'description' => fake()->sentence(),
            'phone' => '600123456',
            'event_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'schedule' => fake()->randomElement(['morning', 'evening']),
            'location' => fake()->city(),
            'guest_count' => 100,
            'duration_hours' => 3,
            'event_type' => 'boda',
            'status' => EventStatus::Requested,
        ];
    }

    public function status(EventStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    /**
     * D6, N15 — presupuestado, con la señal ya calculada y un plazo por
     * delante. Un evento en `quoted` sin importe seria un estado a medias.
     */
    public function quoted(string $importe = '1200.00', int $porcentaje = 30): static
    {
        return $this->state(fn () => [
            'status' => EventStatus::Quoted,
            'quoted_amount' => $importe,
            'deposit_amount' => number_format((float) $importe * $porcentaje / 100, 2, '.', ''),
            'quoted_at' => now(),
            'quote_expires_at' => now()->addDays(14),
        ]);
    }

    /** Aceptado y pendiente de que la señal se cobre. */
    public function accepted(string $importe = '1200.00'): static
    {
        return $this->quoted($importe)->state(fn () => ['status' => EventStatus::Accepted]);
    }

    /** Un presupuesto que ya no se puede aceptar (P1). */
    public function quoteCaducado(): static
    {
        return $this->quoted()->state(fn () => ['quote_expires_at' => now()->subDay()]);
    }
}
