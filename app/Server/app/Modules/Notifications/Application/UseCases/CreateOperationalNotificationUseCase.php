<?php

namespace App\Modules\Notifications\Application\UseCases;

use App\Models\OperationalNotification;
use App\Modules\Notifications\Domain\Contracts\OperationalNotificationRepository;

final class CreateOperationalNotificationUseCase
{
    public function __construct(private readonly OperationalNotificationRepository $operationalNotificationRepository)
    {
    }

    public function execute(array $attributes): OperationalNotification
    {
        return $this->operationalNotificationRepository->create($attributes);
    }
}
