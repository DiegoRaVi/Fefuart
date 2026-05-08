<?php

namespace App\Modules\OrderManagement\Domain\Contracts;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CartRepository
{
    public function findActiveByUserId(int $userId): ?Order;

    public function createActiveForUser(int $userId): Order;

    public function refreshTotal(Order $cart): Order;

    public function checkout(Order $cart, string $address): Order;

    public function getUserOrders(int $userId, int $perPage = 10, ?string $status = null): LengthAwarePaginator;
}
