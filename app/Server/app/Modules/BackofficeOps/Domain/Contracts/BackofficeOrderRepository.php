<?php

namespace App\Modules\BackofficeOps\Domain\Contracts;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BackofficeOrderRepository
{
    public function paginateByStatus(?string $status, int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id): ?Order;

    public function updateStatus(Order $order, string $status): Order;
}
