<?php

namespace App\Notifications;

use App\Models\Event;

/**
 * D32 — el cliente ha cancelado y la fecha vuelve a estar libre.
 *
 * Es el aviso en sentido contrario, y entra por el mismo motivo que los
 * otros dos hacia la artista: le cambia el trabajo. Un evento confirmado
 * bloquea la agenda por N16, asi que hasta que ella no se entera, esa fecha
 * sigue sin poder ofrecerse a nadie mas.
 *
 * No sale cuando cancela ella: no hace falta contarle lo que acaba de hacer.
 */
class SlotFreed extends Aviso
{
    public function __construct(private readonly Event $event) {}

    protected function tipo(): string
    {
        return 'fecha_liberada';
    }

    protected function titulo(): string
    {
        return 'Se ha liberado una fecha';
    }

    protected function cuerpo(): string
    {
        return sprintf(
            '%s ha cancelado «%s», asi que el %s vuelve a estar libre.',
            $this->event->user->name,
            $this->event->title,
            $this->event->event_date->format('d/m/Y'),
        );
    }

    protected function textoDelBoton(): string
    {
        return 'Ver la agenda';
    }

    protected function ruta(): string
    {
        return '/backoffice/eventos';
    }
}
