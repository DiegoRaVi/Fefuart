<?php

namespace App\Notifications;

use App\Models\Event;

/**
 * N15, N16 — la señal se ha cobrado y la fecha queda bloqueada.
 *
 * Es el unico aviso del cobro de una señal, y por eso lleva el importe
 * dentro: para el cliente, aceptar el presupuesto, pagar y reservar fueron
 * un solo gesto, y recibir dos correos con diez segundos de diferencia
 * —«tenemos tu dinero» y «tienes la fecha»— seria contarle dos veces lo
 * mismo.
 *
 * Sale de `QuoteService::confirmarPorSenal()`, **despues** de que el estado
 * cambie. La colision de franja de N16 —dos clientes pagando la misma fecha
 * a la vez— hace saltar el indice unico sobre `confirmed_slot` y la
 * excepcion se lleva por delante este aviso, que es justo lo que tiene que
 * pasar: quien no se ha quedado la fecha no puede recibir un correo
 * diciendole que si.
 */
class EventConfirmed extends Aviso
{
    public function __construct(private readonly Event $event) {}

    protected function tipo(): string
    {
        return 'evento_confirmado';
    }

    protected function titulo(): string
    {
        return 'Tu fecha esta reservada';
    }

    protected function cuerpo(): string
    {
        return sprintf(
            'Hemos recibido tu señal de %s y el %s queda reservado para «%s».',
            $this->euros((string) $this->event->deposit_amount),
            $this->event->event_date->format('d/m/Y'),
            $this->event->title,
        );
    }

    /**
     * @return list<string>
     */
    protected function detalles(): array
    {
        return [
            sprintf(
                'El resto del presupuesto, %s, se abona despues del evento.',
                $this->euros((string) $this->restante()),
            ),
        ];
    }

    protected function textoDelBoton(): string
    {
        return 'Ver la reserva';
    }

    protected function ruta(): string
    {
        return '/live-art#mias';
    }

    /** Lo que queda por pagar: se resta en centimos y se vuelve a montar. */
    private function restante(): string
    {
        $resto = $this->centimos((string) $this->event->quoted_amount)
            - $this->centimos((string) $this->event->deposit_amount);

        return sprintf('%d.%02d', intdiv($resto, 100), $resto % 100);
    }
}
