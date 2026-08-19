<?php

namespace App\Notifications;

use App\Models\Event;

/**
 * El evento se cancela, y que pasa con la señal.
 *
 * Es el aviso que faltaba y el que mas falta hacia: **N21 devuelve la señal
 * por codigo**, no a mano. Sin este correo se le cancelaba la boda a alguien
 * y le aparecian 360 € en la tarjeta sin ninguna explicacion.
 *
 * Sale en las dos direcciones —cancele quien cancele— porque el cliente
 * necesita por escrito que paso con su dinero. Lo que cambia es el texto: si
 * cancelo el, la señal no vuelve, y decirlo es justo lo que evita la
 * reclamacion.
 */
class EventCancelled extends Aviso
{
    public function __construct(
        private readonly Event $event,
        private readonly ?string $senalDevuelta,
    ) {}

    protected function tipo(): string
    {
        return 'evento_cancelado';
    }

    protected function titulo(): string
    {
        return 'Tu evento se ha cancelado';
    }

    protected function cuerpo(): string
    {
        $cabecera = sprintf(
            'Se ha cancelado «%s», previsto para el %s.',
            $this->event->title,
            $this->event->event_date->format('d/m/Y'),
        );

        if ($this->senalDevuelta === null) {
            return $cabecera;
        }

        return $cabecera.sprintf(
            ' Te devolvemos la señal de %s; segun tu banco puede tardar unos dias en aparecer.',
            $this->euros($this->senalDevuelta),
        );
    }

    protected function textoDelBoton(): string
    {
        return 'Ver mis solicitudes';
    }

    protected function ruta(): string
    {
        return '/live-art#mias';
    }
}
