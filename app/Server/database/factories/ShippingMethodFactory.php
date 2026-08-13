<?php

namespace Database\Factories;

use App\Enums\DeliveryType;
use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->physicalAttributes();
    }

    public function physical(): static
    {
        return $this->state(fn () => $this->physicalAttributes());
    }

    public function digital(): static
    {
        return $this->state(fn () => [
            'code' => DeliveryType::Digital,
            'name' => 'Descarga digital',
            'price' => '0.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function physicalAttributes(): array
    {
        return [
            'code' => DeliveryType::Physical,
            'name' => 'Envio a domicilio',
            'price' => '5.00',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
