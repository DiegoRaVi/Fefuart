<?php

namespace App\Modules\OrderManagement\Infrastructure\Persistence;

use App\Models\Product;
use App\Modules\OrderManagement\Domain\Contracts\CartItemRepository;

final class EloquentCartItemRepository implements CartItemRepository
{
    public function create(array $attributes): Product
    {
        return Product::create($attributes);
    }

    public function findByIdInCart(int $itemId, int $cartId): ?Product
    {
        return Product::where('id', $itemId)
            ->where('order_id', $cartId)
            ->first();
    }

    public function update(Product $item, array $attributes): Product
    {
        $item->update($attributes);

        return $item->fresh();
    }

    public function delete(Product $item): void
    {
        $item->delete();
    }
}
