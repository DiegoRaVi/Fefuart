<?php

namespace App\Notifications;

use App\Services\PricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * La forma comun de los avisos de Fefuart (D10).
 *
 * Existe por lo que consume el otro extremo: la lista del centro de avisos
 * pinta cada fila con **titulo, cuerpo y enlace**, siempre los mismos tres
 * campos. Si cada notificacion decidiera su propio `toArray()`, la SPA
 * acabaria con un `switch` por tipo que hay que ampliar cada vez que nace un
 * aviso nuevo. Aqui la forma se declara una vez y las subclases solo
 * rellenan el texto.
 *
 * **Todas van en cola.** No es solo D10: el envio ocurre en el worker, asi
 * que un SMTP caido se queda en el job y no le devuelve un 500 a Stripe por
 * algo que no tiene nada que ver con el cobro.
 */
abstract class Aviso extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Tres intentos y se rinde. Un correo que no sale a la tercera no sale
     * por reintentar mas: sale cuando alguien mire `failed_jobs`.
     */
    public int $tries = 3;

    /**
     * `database` es lo que alimenta `GET /api/notifications`; `mail`, lo que
     * llega a la bandeja. Los dos siempre: un aviso que solo esta en uno de
     * los sitios es un aviso que alguien se pierde.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** Identificador corto del tipo de aviso, para la SPA. */
    abstract protected function tipo(): string;

    abstract protected function titulo(): string;

    abstract protected function cuerpo(): string;

    /**
     * La ruta **de la SPA**, relativa. Relativa a proposito: en el centro de
     * avisos la navegacion es de React Router y una URL absoluta forzaria una
     * recarga completa de la aplicacion. El correo si la necesita absoluta, y
     * de eso se encarga `enlace()`.
     */
    abstract protected function ruta(): string;

    /**
     * Lineas de detalle del correo, debajo del cuerpo. No entran en el aviso
     * de dentro de la aplicacion: alli el detalle esta a un clic.
     *
     * @return list<string>
     */
    protected function detalles(): array
    {
        return [];
    }

    protected function textoDelBoton(): string
    {
        return 'Verlo en Fefuart';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensaje = (new MailMessage)
            ->subject($this->titulo())
            ->greeting("Hola{$this->nombreDe($notifiable)},")
            ->line($this->cuerpo());

        foreach ($this->detalles() as $detalle) {
            $mensaje->line($detalle);
        }

        return $mensaje
            ->action($this->textoDelBoton(), $this->enlace())
            ->salutation('Un saludo, Fefuart');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => $this->tipo(),
            'titulo' => $this->titulo(),
            'cuerpo' => $this->cuerpo(),
            'enlace' => $this->ruta(),
        ];
    }

    /** La ruta de la SPA en absoluto, que es lo que necesita un correo. */
    protected function enlace(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$this->ruta();
    }

    /**
     * Un importe decimal en centimos enteros.
     *
     * Se lo pide a `PricingService` en vez de repetir el troceo de la cadena:
     * hay un solo sitio en el sistema que sabe convertir dinero, y este no es
     * otro. Se resuelve del contenedor y no se guarda como propiedad, para no
     * arrastrar el servicio dentro del job serializado.
     */
    protected function centimos(string $importe): int
    {
        return app(PricingService::class)->toCents($importe);
    }

    /**
     * Formatea un importe decimal como se escribe en castellano.
     *
     * Sin pasar por coma flotante en ningun momento, igual que el resto del
     * sistema: no porque un correo vaya a descuadrar por medio centimo, sino
     * para que nadie lea esto y lo copie donde si importa.
     */
    protected function euros(string $importe): string
    {
        $centimos = $this->centimos($importe);

        return number_format(intdiv($centimos, 100), 0, ',', '.')
            .','.sprintf('%02d', $centimos % 100)
            .' €';
    }

    private function nombreDe(object $notifiable): string
    {
        $nombre = $notifiable->name ?? null;

        return $nombre ? " {$nombre}" : '';
    }
}
