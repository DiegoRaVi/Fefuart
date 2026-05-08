<?php

namespace App\Modules\OrderManagement\Application\UseCases;

use App\Models\Order;
use App\Modules\OrderManagement\Domain\Contracts\CartRepository;
use RuntimeException;

final class CheckoutCartUseCase
{
    public function __construct(private readonly CartRepository $cartRepository)
    {
    }

    public function execute(int $userId, string $address): Order
    {
        $cart = $this->cartRepository->findActiveByUserId($userId);

        if (!$cart) {
            throw new RuntimeException('Cart not found');
        }

        if ($cart->products()->count() === 0) {
            throw new RuntimeException('Cart is empty');
        }

        return $this->cartRepository->checkout($cart, $address)->load('products');
    }
}
