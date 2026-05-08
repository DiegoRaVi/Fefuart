<?php

namespace App\Modules\OrderManagement\Domain\Contracts;

use App\Models\Product;

interface CartItemRepository
{
    public function create(array $attributes): Product;

    public function findByIdInCart(int $itemId, int $cartId): ?Product;

    public function update(Product $item, array $attributes): Product;

    public function delete(Product $item): void;
}
