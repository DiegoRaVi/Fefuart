<?php

namespace App\Modules\OrderManagement\Application\UseCases;

use App\Models\Order;
use App\Modules\OrderManagement\Domain\Contracts\CartRepository;

final class GetCurrentCartUseCase
{
    public function __construct(private readonly CartRepository $cartRepository)
    {
    }

    public function execute(int $userId): ?Order
    {
        $cart = $this->cartRepository->findActiveByUserId($userId);

        return $cart?->load('products');
    }
}
