<?php

namespace App\Modules\BackofficeOps\Domain\Contracts;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BackofficeEventRepository
{
    public function paginateByStatus(?string $status, int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id): ?Event;

    public function updateStatus(Event $event, string $status): Event;
}
