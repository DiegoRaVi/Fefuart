<?php

namespace App\Enums;

/**
 * Estados de un evento de Live Art.
 *
 * N13 — el precio siempre es a medida: la artista revisa la solicitud y
 * emite un presupuesto. De ahi que haya estados intermedios que v1 no tenia:
 * su enum era `pending / confirmed / rejected / done`, sin sitio para la
 * negociacion.
 *
 * Los importes del presupuesto y la señal llegan en la Fase 5 (D27).
 */
enum EventStatus: string
{
    case Requested = 'requested';
    case Quoted = 'quoted';
    case Accepted = 'accepted';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Quoted, self::Rejected, self::Cancelled],
            self::Quoted => [self::Accepted, self::Rejected, self::Cancelled],
            // De aceptado a confirmado se pasa al cobrar la señal (N15), que
            // es de la Fase 5.
            self::Accepted => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $destino): bool
    {
        return in_array($destino, $this->allowedTransitions(), strict: true);
    }

    /**
     * Mientras la artista no lo haya presupuestado, el cliente puede corregir
     * los datos de su solicitud.
     */
    public function isEditableByCustomer(): bool
    {
        return $this === self::Requested;
    }
}
