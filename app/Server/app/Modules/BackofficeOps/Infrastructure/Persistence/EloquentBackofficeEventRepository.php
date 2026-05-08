<?php

namespace App\Modules\BackofficeOps\Infrastructure\Persistence;

use App\Models\Event;
use App\Modules\BackofficeOps\Domain\Contracts\BackofficeEventRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentBackofficeEventRepository implements BackofficeEventRepository
{
    public function paginateByStatus(?string $status, int $perPage = 20): LengthAwarePaginator
    {
        $query = Event::with('user')->orderBy('date', 'asc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?Event
    {
        return Event::with('user')->find($id);
    }

    public function updateStatus(Event $event, string $status): Event
    {
        $event->update(['status' => $status]);

        return $event->fresh()->load('user');
    }
}
