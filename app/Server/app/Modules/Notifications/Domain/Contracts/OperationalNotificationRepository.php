<?php

namespace App\Modules\Notifications\Domain\Contracts;

use App\Models\OperationalNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OperationalNotificationRepository
{
    public function create(array $attributes): OperationalNotification;

    public function paginateByUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function findByIdForUser(int $notificationId, int $userId): ?OperationalNotification;

    public function markAsRead(OperationalNotification $notification): OperationalNotification;
}
