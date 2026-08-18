<?php

namespace App\Notifications;

use App\Models\Event;

/**
 * D6, N13 — la artista ya ha presupuestado el evento.
 *
 * Es el aviso que sostiene todo el flujo de Live Art: el cliente pide y se
 * queda esperando sin saber cuanto tardara: no hay tarifas publicadas que
 * consultar. Sin este correo, la unica forma de enterarse seria volver a
 * entrar en la web a probar suerte.
 */
class QuoteReady extends Aviso
{
    public function __construct(private readonly Event $event) {}

    protected function tipo(): string
    {
        return 'presupuesto_listo';
    }

    protected function titulo(): string
    {
        return 'Ya tienes el presupuesto de tu evento';
    }

    protected function cuerpo(): string
    {
        return sprintf(
            'Hemos preparado el presupuesto de «%s» para el %s: %s.',
            $this->event->title,
            $this->event->event_date->format('d/m/Y'),
            $this->euros((string) $this->event->quoted_amount),
        );
    }

    /**
     * La señal y la caducidad van en el correo porque son las dos cosas que
     * el cliente necesita para decidir, y P1 dice que el plazo es real: un
     * presupuesto caducado ya no se puede aceptar.
     *
     * @return list<string>
     */
    protected function detalles(): array
    {
        return [
            sprintf(
                'Para reservar la fecha se abona una señal de %s; el resto, despues del evento.',
                $this->euros((string) $this->event->deposit_amount),
            ),
            sprintf(
                'Tienes hasta el %s para aceptarlo.',
                $this->event->quote_expires_at->format('d/m/Y'),
            ),
        ];
    }

    protected function textoDelBoton(): string
    {
        return 'Ver el presupuesto';
    }

    protected function ruta(): string
    {
        return '/live-art#mias';
    }
}
