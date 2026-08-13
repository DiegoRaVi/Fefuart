<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una solicitud de Live Art. N13: el precio siempre es a medida.
 */
class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory, SoftDeletes;

    /**
     * SEC-010: `status` NO figura aqui. En v1 `updateEvent` hacia
     * `$event->update($request->only([... 'status']))`, de modo que el
     * propietario podia confirmarse su propio evento.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'phone',
        'event_date',
        'schedule',
        'location',
        'guest_count',
        'duration_hours',
        'event_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'status' => EventStatus::class,
            'guest_count' => 'integer',
            'duration_hours' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
