<?php

namespace App\Modules\Catalog\Application\DTOs;

final class UpsertCatalogProductData
{
    public function __construct(
        public readonly string $name,
        public readonly float $price,
        public readonly int $quantity,
        public readonly ?string $description,
        public readonly string $category,
        public readonly ?string $subcategory,
        public readonly string $deliveryType,
        public readonly string $deliveryTime,
        public readonly ?string $imageUrl,
        public readonly ?int $stock,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            price: (float) $data['price'],
            quantity: (int) $data['quantity'],
            description: $data['description'] ?? null,
            category: $data['category'],
            subcategory: $data['subcategory'] ?? null,
            deliveryType: $data['delivery_type'],
            deliveryTime: (string) $data['delivery_time'],
            imageUrl: $data['image_url'] ?? null,
            stock: isset($data['stock']) ? (int) $data['stock'] : null,
        );
    }

    public function toRepositoryPayload(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'description' => $this->description,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'delivery_type' => $this->deliveryType,
            'delivery_time' => $this->deliveryTime,
            'image_url' => $this->imageUrl,
            'stock' => $this->stock,
            'order_id' => null,
        ];
    }
}
