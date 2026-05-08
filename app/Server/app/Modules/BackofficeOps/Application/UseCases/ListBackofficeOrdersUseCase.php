<?php

namespace App\Modules\BackofficeOps\Application\UseCases;

use App\Modules\BackofficeOps\Domain\Contracts\BackofficeOrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListBackofficeOrdersUseCase
{
    public function __construct(private readonly BackofficeOrderRepository $backofficeOrderRepository)
    {
    }

    public function execute(?string $status, int $perPage = 20): LengthAwarePaginator
    {
        return $this->backofficeOrderRepository->paginateByStatus($status, $perPage);
    }
}
