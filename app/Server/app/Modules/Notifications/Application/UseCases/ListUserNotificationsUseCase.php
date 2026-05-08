<?php

namespace App\Modules\Notifications\Application\UseCases;

use App\Modules\Notifications\Domain\Contracts\OperationalNotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListUserNotificationsUseCase
{
    public function __construct(private readonly OperationalNotificationRepository $operationalNotificationRepository)
    {
    }

    public function execute(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->operationalNotificationRepository->paginateByUser($userId, $perPage);
    }
}
