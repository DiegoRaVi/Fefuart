<?php

namespace App\Modules\OrderManagement\Application\UseCases;

use App\Modules\Catalog\Domain\Contracts\CatalogProductRepository;
use App\Modules\OrderManagement\Application\DTOs\AddCartItemData;
use RuntimeException;

final class AddCatalogItemToCartUseCase
{
    public function __construct(
        private readonly CatalogProductRepository $catalogProductRepository,
        private readonly AddItemToCartUseCase $addItemToCartUseCase,
    ) {
    }

    public function execute(int $userId, int $catalogProductId, int $quantity): array
    {
        $catalogProduct = $this->catalogProductRepository->findById($catalogProductId);

        if (!$catalogProduct) {
            throw new RuntimeException('Catalog product not found');
        }

        $itemData = AddCartItemData::fromArray([
            'name' => $catalogProduct->name,
            'price' => (float) $catalogProduct->price,
            'quantity' => $quantity,
            'description' => $catalogProduct->description,
            'category' => $catalogProduct->category,
            'subcategory' => $catalogProduct->subcategory,
            'delivery_type' => $catalogProduct->delivery_type,
            'delivery_time' => $catalogProduct->delivery_time,
            'image_url' => $catalogProduct->image_url,
            'stock' => $catalogProduct->stock,
        ]);

        return $this->addItemToCartUseCase->execute($userId, $itemData);
    }
}
