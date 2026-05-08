<?php

namespace App\Modules\LiveArtBooking\Application\DTOs;

final class CreateLiveArtRequestData
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $phone,
        public readonly string $date,
        public readonly string $location,
        public readonly string $schedule,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'] ?? null,
            phone: $data['phone'] ?? null,
            date: $data['date'],
            location: $data['location'],
            schedule: $data['schedule'],
        );
    }

    public function toRepositoryPayload(int $userId): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'phone' => $this->phone,
            'date' => $this->date,
            'location' => $this->location,
            'schedule' => $this->schedule,
            'status' => 'pending',
            'user_id' => $userId,
        ];
    }
}
