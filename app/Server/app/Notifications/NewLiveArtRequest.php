<?php

namespace App\Notifications;

use App\Models\Event;

/**
 * D10 — ha entrado una solicitud de Live Art.
 *
 * No estaba en la lista original del roadmap, que solo miraba al cliente.
 * Entra porque este flujo se atasca sin el: N13 dice que el precio siempre
 * es a medida, asi que el cliente se queda esperando un presupuesto que solo
 * puede emitir Felicitas. Si ella no se entera, nadie se entera.
 */
class NewLiveArtRequest extends Aviso
{
    public function __construct(private readonly Event $event) {}

    protected function tipo(): string
    {
        return 'solicitud_de_live_art';
    }

    protected function titulo(): string
    {
        return 'Nueva solicitud de Live Art';
    }

    protected function cuerpo(): string
    {
        return sprintf(
            '%s ha pedido presupuesto para «%s» el %s en %s.',
            $this->event->user->name,
            $this->event->title,
            $this->event->event_date->format('d/m/Y'),
            $this->event->location,
        );
    }

    /**
     * N14 — invitados y duracion son los dos datos que determinan la tarifa,
     * asi que van en el correo: con ellos se puede ir pensando el precio sin
     * abrir el backoffice.
     *
     * @return list<string>
     */
    protected function detalles(): array
    {
        return [
            sprintf(
                'Tipo de evento: %s · %d invitados · %d horas de servicio.',
                $this->event->event_type,
                $this->event->guest_count,
                $this->event->duration_hours,
            ),
        ];
    }

    protected function textoDelBoton(): string
    {
        return 'Ver la solicitud';
    }

    protected function ruta(): string
    {
        return '/backoffice/eventos';
    }
}
