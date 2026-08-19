<?php

namespace App\Notifications;

use App\Models\Event;

/**
 * La artista no va a presupuestar esta solicitud.
 *
 * Existe porque N13 dice que no hay tarifas publicadas: sin este aviso el
 * cliente se queda esperando un presupuesto que no va a llegar nunca, y no
 * tiene ningun sitio donde consultar el precio por su cuenta.
 *
 * El correo no dice el motivo. Un rechazo puede ser una fecha imposible, un
 * desplazamiento inviable o cualquier cosa, y el sistema no lo sabe: inventar
 * una razon seria peor que no darla.
 */
class QuoteRejected extends Aviso
{
    public function __construct(private readonly Event $event) {}

    protected function tipo(): string
    {
        return 'presupuesto_rechazado';
    }

    protected function titulo(): string
    {
        return 'No podemos atender tu solicitud';
    }

    protected function cuerpo(): string
    {
        return sprintf(
            'Sentimos decirte que no podemos encargarnos de «%s» el %s.',
            $this->event->title,
            $this->event->event_date->format('d/m/Y'),
        );
    }

    /**
     * @return list<string>
     */
    protected function detalles(): array
    {
        return [
            'Si te encaja otra fecha, escribenos y lo miramos con mucho gusto.',
        ];
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
