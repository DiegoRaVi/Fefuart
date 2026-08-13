<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Cart,
            'subtotal' => '0.00',
            'shipping_total' => '0.00',
            'total' => '0.00',
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'placed_at' => $status->isPlaced() ? now() : null,
        ]);
    }

    public function placed(): static
    {
        return $this->status(OrderStatus::PendingPayment)->state(fn () => [
            'shipping_name' => 'Felicitas Varela',
            'shipping_phone' => '600123456',
            'shipping_line1' => 'Calle Mayor 1',
            'shipping_city' => 'Madrid',
            'shipping_province' => 'Madrid',
            'shipping_postal_code' => '28001',
            'shipping_country' => 'ES',
        ]);
    }
}
