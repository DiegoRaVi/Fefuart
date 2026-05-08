<?php

namespace App\Modules\OrderManagement\Application\UseCases;

use App\Models\Order;
use App\Modules\OrderManagement\Domain\Contracts\CartRepository;

final class CreateOrGetCartUseCase
{
    public function __construct(private readonly CartRepository $cartRepository)
    {
    }

    public function execute(int $userId): Order
    {
        $cart = $this->cartRepository->findActiveByUserId($userId);

        if ($cart) {
            return $cart->load('products');
        }

        return $this->cartRepository->createActiveForUser($userId)->load('products');
    }
}
