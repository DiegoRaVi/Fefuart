<?php

namespace App\Modules\LiveArtBooking\Infrastructure\Persistence;

use App\Models\Event;
use App\Modules\LiveArtBooking\Domain\Contracts\LiveArtRequestRepository;

final class EloquentLiveArtRequestRepository implements LiveArtRequestRepository
{
    public function create(array $attributes): Event
    {
        return Event::create($attributes);
    }
}
