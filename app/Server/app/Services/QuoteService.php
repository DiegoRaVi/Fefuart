<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\EventConfirmed;
use App\Notifications\QuoteReady;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * D6 — el presupuesto de la artista y la señal que reserva la fecha.
 *
 * N13: el precio de un evento siempre es a medida. Cada boda es distinta y
 * una tarifa publicada no encaja, asi que entre la solicitud y la reserva hay
 * una negociacion con estados propios. v1 no la tenia: su enum era
 * `pending / confirmed / rejected / done` y el propietario podia moverse a
 * `confirmed` el solo (SEC-010).
 *
 * Aqui no se cobra nada. Aceptar el presupuesto deja el evento en `accepted`;
 * quien lo pasa a `confirmed` es el webhook de la señal, con firma verificada.
 */
class QuoteService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly SettingsService $ajustes,
        private readonly StripePaymentService $pagos,
    ) {}

    /**
     * La artista emite el presupuesto. La señal se calcula y se guarda con
     * el, no se deriva al vuelo: el porcentaje es configurable, y si manana
     * cambia, la señal de un evento ya presupuestado tiene que seguir siendo
     * la que se le dijo al cliente.
     *
     * @throws ValidationException la fecha ya esta ocupada
     */
    public function presupuestar(Event $event, string $importe, ?int $diasDeValidez = null): Event
    {
        if (! $event->status->canTransitionTo(EventStatus::Quoted)) {
            throw new RuntimeException(
                "No se puede presupuestar un evento en estado {$event->status->value}."
            );
        }

        // N16 — presupuestar una fecha que ya esta comprometida seria
        // ofrecerle al cliente algo que no se le puede dar.
        $this->guardFranjaLibre($event);

        $dias = $diasDeValidez ?? $this->ajustes->diasDeValidezDelPresupuesto();

        $event->quoted_amount = $importe;
        $event->deposit_amount = $this->pricing->deposit($importe, $this->ajustes->porcentajeDeSenal());
        $event->quoted_at = now();
        $event->quote_expires_at = now()->addDays($dias);
        $event->status = EventStatus::Quoted;
        $event->save();

        // D10 — despues del `save()` y no antes: el aviso solo sale si el
        // presupuesto llego a emitirse.
        $event->user->notify(new QuoteReady($event));

        return $event;
    }

    /**
     * El cliente acepta. Es idempotente a proposito: si abandona la pagina de
     * pago y vuelve a darle a aceptar, el evento ya esta en `accepted` y lo
     * que hace falta es otra sesion de pago, no un error.
     *
     * @throws ValidationException el presupuesto caduco o la fecha se ocupo
     */
    public function aceptar(Event $event): Event
    {
        if ($event->status === EventStatus::Accepted) {
            return $event;
        }

        if ($event->status !== EventStatus::Quoted) {
            throw ValidationException::withMessages([
                'status' => 'Este evento no tiene un presupuesto pendiente de aceptar.',
            ]);
        }

        if ($event->presupuestoCaducado()) {
            // P1 — sin esto, un presupuesto de hace ocho meses se podria
            // aceptar hoy a aquel precio.
            throw ValidationException::withMessages([
                'quote' => 'Este presupuesto ha caducado. Escribenos y te preparamos uno nuevo.',
            ]);
        }

        $this->guardFranjaLibre($event);

        $event->status = EventStatus::Accepted;
        $event->save();

        return $event;
    }

    /**
     * N16 — una fecha y franja con un evento ya confirmado no admite otro.
     *
     * La base de datos lo garantiza igual con un indice unico sobre columna
     * generada, pero enterarse ahi seria enterarse cuando el cliente ya ha
     * pagado la señal. Esto es la comprobacion amable; aquella es la que no
     * se puede saltar.
     *
     * @throws ValidationException
     */
    private function guardFranjaLibre(Event $event): void
    {
        $ocupada = Event::query()
            ->where('status', EventStatus::Confirmed)
            ->where('event_date', $event->event_date)
            ->where('schedule', $event->schedule)
            ->whereKeyNot($event->getKey())
            ->exists();

        if ($ocupada) {
            throw ValidationException::withMessages([
                'event_date' => 'Esa fecha y franja ya estan reservadas para otro evento.',
            ]);
        }
    }

    /**
     * N21 — cancelar un evento, con lo que eso hace con la señal.
     *
     * La señal reserva la fecha y bloquea la agenda: si quien se echa atras
     * es el cliente, compensa el hueco y no se devuelve. Si quien cancela es
     * la artista, se devuelve entera, porque el hueco lo libera ella.
     *
     * La devolucion se hace aqui y no a mano en el panel a proposito: la
     * regla es deterministica y olvidarse de aplicarla deja al cliente sin su
     * dinero. Lo que si es explicito es el boton, que dice en su etiqueta que
     * va a devolver la señal.
     */
    public function cancelar(Event $event, User $quien): Event
    {
        if (! $event->status->canTransitionTo(EventStatus::Cancelled)) {
            throw ValidationException::withMessages([
                'status' => 'Este evento ya no se puede cancelar.',
            ]);
        }

        $senal = $this->senalCobradaDe($event);

        DB::transaction(function () use ($event) {
            $event->status = EventStatus::Cancelled;
            $event->save();
        });

        // Fuera de la transaccion: si la devolucion falla, la cancelacion se
        // queda hecha. Lo contrario —deshacer la cancelacion porque Stripe no
        // responde— dejaria la fecha ocupada por un evento que ya nadie va a
        // celebrar.
        if ($senal !== null && $quien->isAdmin()) {
            $this->pagos->devolver($senal, 'Cancelado por la artista.');
        }

        return $event;
    }

    /** La señal cobrada de un evento, si la hay. */
    public function senalCobradaDe(Event $event): ?Payment
    {
        return $event->payments()
            ->where('kind', PaymentKind::Deposit)
            ->where('status', PaymentStatus::Succeeded)
            ->latest('id')
            ->first();
    }

    /**
     * Lo que hace el webhook cuando la señal se cobra. Va aqui y no en el
     * servicio de webhooks para que la regla de negocio —que confirmar es
     * pasar de `accepted` a `confirmed` y nada mas— viva junto al resto.
     */
    public function confirmarPorSenal(Event $event): void
    {
        if ($event->status === EventStatus::Confirmed) {
            return;
        }

        if (! $event->status->canTransitionTo(EventStatus::Confirmed)) {
            throw new RuntimeException(
                "El evento {$event->id} esta en «{$event->status->value}» y no puede confirmarse."
            );
        }

        DB::transaction(function () use ($event) {
            $event->status = EventStatus::Confirmed;
            $event->save();
        });

        /*
         * D10 — el aviso va **despues** del cambio de estado, nunca antes.
         *
         * El caso que lo obliga es la colision de franja de N16: dos
         * clientes pagando la misma fecha a la vez. El segundo `UPDATE` se
         * estrella contra el indice unico sobre `confirmed_slot`, la
         * excepcion sale de la transaccion y esta linea no llega a
         * ejecutarse. El cliente ha pagado y no tiene la fecha, asi que lo
         * ultimo que puede recibir es un «tu fecha queda reservada».
         *
         * Que ademas este fuera de la transaccion no cambia nada en ese
         * caso concreto —el `save()` revienta antes que cualquier aviso
         * puesto detras de el—, pero es donde tiene que estar: un aviso
         * cuenta un hecho ya confirmado, y dentro de una transaccion todavia
         * no lo es.
         */
        $event->user->notify(new EventConfirmed($event));
    }
}
