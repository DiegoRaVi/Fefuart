<?php

namespace App\Modules\BackofficeOps\Application\UseCases;

use App\Modules\BackofficeOps\Domain\Contracts\BackofficeEventRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListBackofficeEventsUseCase
{
    public function __construct(private readonly BackofficeEventRepository $backofficeEventRepository)
    {
    }

    public function execute(?string $status, int $perPage = 20): LengthAwarePaginator
    {
        return $this->backofficeEventRepository->paginateByStatus($status, $perPage);
    }
}
