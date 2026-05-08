<?php

namespace App\Modules\OrderManagement\Application\UseCases;

use App\Models\Order;
use App\Modules\OrderManagement\Domain\Contracts\CartItemRepository;
use App\Modules\OrderManagement\Domain\Contracts\CartRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RemoveCartItemUseCase
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
    ) {
    }

    public function execute(int $userId, int $itemId): Order
    {
        return DB::transaction(function () use ($userId, $itemId): Order {
            $cart = $this->cartRepository->findActiveByUserId($userId);

            if (!$cart) {
                throw new RuntimeException('Cart not found');
            }

            $item = $this->cartItemRepository->findByIdInCart($itemId, (int) $cart->id);

            if (!$item) {
                throw new RuntimeException('Cart item not found');
            }

            $this->cartItemRepository->delete($item);

            $updatedCart = $this->cartRepository->refreshTotal($cart);

            return $updatedCart->load('products');
        });
    }
}
