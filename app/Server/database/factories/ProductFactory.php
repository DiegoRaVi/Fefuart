<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'category' => 'dibujo',
            'is_active' => true,
            'sort_order' => 0,
            'requires_reference_image' => false,
            'requires_notes' => false,
            'max_quantity' => 10,
            'delivery_days' => 15,
        ];
    }

    /**
     * N9 — dibujo por encargo y ramos se dibujan a partir de la foto del
     * cliente: sin ella no hay encargo.
     */
    public function requiringReferenceImage(): static
    {
        return $this->state(fn () => ['requires_reference_image' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
