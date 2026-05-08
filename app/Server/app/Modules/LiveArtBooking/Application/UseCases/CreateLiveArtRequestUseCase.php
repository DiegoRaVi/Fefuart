<?php

namespace App\Modules\LiveArtBooking\Application\UseCases;

use App\Models\Event;
use App\Modules\LiveArtBooking\Application\DTOs\CreateLiveArtRequestData;
use App\Modules\LiveArtBooking\Domain\Contracts\LiveArtRequestRepository;

final class CreateLiveArtRequestUseCase
{
    public function __construct(private readonly LiveArtRequestRepository $liveArtRequestRepository)
    {
    }

    public function execute(CreateLiveArtRequestData $data, int $userId): Event
    {
        return $this->liveArtRequestRepository->create(
            $data->toRepositoryPayload($userId)
        );
    }
}
