<?php

namespace App\Modules\BackofficeOps\Application\UseCases;

use App\Models\Order;
use App\Modules\BackofficeOps\Domain\Contracts\BackofficeOrderRepository;

final class UpdateBackofficeOrderStatusUseCase
{
    public function __construct(private readonly BackofficeOrderRepository $backofficeOrderRepository)
    {
    }

    public function execute(Order $order, string $status): Order
    {
        return $this->backofficeOrderRepository->updateStatus($order, $status);
    }
}
