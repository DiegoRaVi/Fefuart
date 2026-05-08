<?php

namespace App\Modules\BackofficeOps\Application\UseCases;

use App\Models\Event;
use App\Modules\BackofficeOps\Domain\Contracts\BackofficeEventRepository;

final class UpdateBackofficeEventStatusUseCase
{
    public function __construct(private readonly BackofficeEventRepository $backofficeEventRepository)
    {
    }

    public function execute(Event $event, string $status): Event
    {
        return $this->backofficeEventRepository->updateStatus($event, $status);
    }
}
