<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Models\OperationalNotification;
use App\Modules\Notifications\Domain\Contracts\OperationalNotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentOperationalNotificationRepository implements OperationalNotificationRepository
{
    public function create(array $attributes): OperationalNotification
    {
        return OperationalNotification::create($attributes);
    }

    public function paginateByUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return OperationalNotification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findByIdForUser(int $notificationId, int $userId): ?OperationalNotification
    {
        return OperationalNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();
    }

    public function markAsRead(OperationalNotification $notification): OperationalNotification
    {
        $notification->update(['read_at' => now()]);

        return $notification->fresh();
    }
}
