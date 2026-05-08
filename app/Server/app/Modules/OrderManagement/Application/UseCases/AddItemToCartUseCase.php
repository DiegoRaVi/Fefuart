<?php

namespace App\Modules\OrderManagement\Application\UseCases;

use App\Models\Order;
use App\Models\Product;
use App\Modules\OrderManagement\Application\DTOs\AddCartItemData;
use App\Modules\OrderManagement\Domain\Contracts\CartItemRepository;
use App\Modules\OrderManagement\Domain\Contracts\CartRepository;
use Illuminate\Support\Facades\DB;

final class AddItemToCartUseCase
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
    ) {
    }

    /**
     * @return array{cart: Order, item: Product}
     */
    public function execute(int $userId, AddCartItemData $data): array
    {
        return DB::transaction(function () use ($userId, $data): array {
            $cart = $this->cartRepository->findActiveByUserId($userId)
                ?? $this->cartRepository->createActiveForUser($userId);

            $item = $this->cartItemRepository->create(
                $data->toRepositoryPayload((int) $cart->id)
            );

            $updatedCart = $this->cartRepository->refreshTotal($cart);

            return [
                'cart' => $updatedCart->load('products'),
                'item' => $item,
            ];
        });
    }
}
