<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
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
}
