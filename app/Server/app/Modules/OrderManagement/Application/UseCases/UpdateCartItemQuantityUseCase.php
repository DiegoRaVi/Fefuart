<?php

namespace App\Modules\OrderManagement\Application\UseCases;

use App\Models\Order;
use App\Models\Product;
use App\Modules\OrderManagement\Domain\Contracts\CartItemRepository;
use App\Modules\OrderManagement\Domain\Contracts\CartRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UpdateCartItemQuantityUseCase
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
    ) {
    }

    /**
     * @return array{cart: Order, item: Product}
     */
    public function execute(int $userId, int $itemId, int $quantity): array
    {
        return DB::transaction(function () use ($userId, $itemId, $quantity): array {
            $cart = $this->cartRepository->findActiveByUserId($userId);

            if (!$cart) {
                throw new RuntimeException('Cart not found');
            }

            $item = $this->cartItemRepository->findByIdInCart($itemId, (int) $cart->id);

            if (!$item) {
                throw new RuntimeException('Cart item not found');
            }

            $updatedItem = $this->cartItemRepository->update($item, [
                'quantity' => $quantity,
            ]);

            $updatedCart = $this->cartRepository->refreshTotal($cart);

            return [
                'cart' => $updatedCart->load('products'),
                'item' => $updatedItem,
            ];
        });
    }
}
