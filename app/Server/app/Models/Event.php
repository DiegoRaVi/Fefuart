<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Models\Concerns\SeBusca;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una solicitud de Live Art. N13: el precio siempre es a medida.
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SeBusca, SoftDeletes;

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
            'quoted_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'quoted_at' => 'datetime',
            'quote_expires_at' => 'datetime',
        ];
    }

    /**
     * P1 — un presupuesto no vale para siempre. El plazo lo fija
     * `settings.quote_validity_days` y se congela al presupuestar, para que
     * cambiarlo no mueva la caducidad de los que ya estan emitidos.
     */
    public function presupuestoCaducado(): bool
    {
        return $this->quote_expires_at !== null && $this->quote_expires_at->isPast();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * N15 — la señal. Es `morphMany` y no `morphOne` porque un intento
     * caducado o rechazado no se borra: es el rastro que hace falta cuando
     * un cliente dice que pago y el evento dice que no.
     *
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
