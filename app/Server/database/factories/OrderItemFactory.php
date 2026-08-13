<?php

namespace Database\Factories;

use App\Enums\DeliveryType;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $variant = ProductVariant::factory()->create();

        return [
            'order_id' => Order::factory(),
            ...$this->snapshotOf($variant),
            'delivery_type' => DeliveryType::Physical,
            'quantity' => 1,
        ];
    }

    /**
     * Copia el catalogo en la linea tal y como lo hara CartService. Los tests
     * que comprueban el snapshot necesitan partir de una variante concreta.
     */
    public function fromVariant(ProductVariant $variant): static
    {
        return $this->state(fn () => $this->snapshotOf($variant));
    }

    public function digital(): static
    {
        return $this->state(fn () => [
            'delivery_type' => DeliveryType::Digital,
            'quantity' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotOf(ProductVariant $variant): array
    {
        return [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'variant_name' => $variant->name,
            'unit_price' => $variant->price,
            'additional_copy_price' => $variant->additional_copy_price,
            'line_total' => $variant->price,
        ];
    }
}
