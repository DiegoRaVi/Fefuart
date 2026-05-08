<?php

namespace App\Modules\LiveArtBooking\Domain\Contracts;

use App\Models\Event;

interface LiveArtRequestRepository
{
    public function create(array $attributes): Event;
}
