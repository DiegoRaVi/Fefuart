<?php

namespace App\Modules\Notifications\Application\UseCases;

use App\Models\OperationalNotification;
use App\Modules\Notifications\Domain\Contracts\OperationalNotificationRepository;

final class MarkNotificationAsReadUseCase
{
    public function __construct(private readonly OperationalNotificationRepository $operationalNotificationRepository)
    {
    }

    public function execute(int $notificationId, int $userId): ?OperationalNotification
    {
        $notification = $this->operationalNotificationRepository->findByIdForUser($notificationId, $userId);

        if (!$notification) {
            return null;
        }

        if ($notification->read_at) {
            return $notification;
        }

        return $this->operationalNotificationRepository->markAsRead($notification);
    }
}
