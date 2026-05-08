<?php

namespace App\Modules\OrderManagement\Application\UseCases;

use App\Modules\OrderManagement\Domain\Contracts\CartRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetUserOrdersUseCase
{
    public function __construct(private readonly CartRepository $cartRepository)
    {
    }

    public function execute(int $userId, int $perPage = 10, ?string $status = null): LengthAwarePaginator
    {
        return $this->cartRepository->getUserOrders($userId, $perPage, $status);
    }
}
