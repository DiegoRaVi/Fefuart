<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * SEC-010 — quien confirma un evento.
 *
 * En v1 `EventController::updateEvent` hacia
 * `$event->update($request->only([... 'status']))`, sin restringir ni el rol
 * ni los valores admitidos, de modo que el propietario podia pasar su propio
 * evento de `pending` a `confirmed`. Era latente solo porque ese metodo no
 * existia y la ruta respondia 500 (BUG-002): se habria activado en el
 * momento de arreglar el bug.
 *
 * Aqui presupuestar y confirmar son abilities separadas y solo de la
 * administradora.
 */
class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $this->owns($user, $event) || $user->isAdmin();
    }

    /**
     * El cliente corrige los datos de su solicitud mientras no este
     * presupuestada. Nunca su estado: `status` no es asignable en masa.
     */
    public function update(User $user, Event $event): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->owns($user, $event) && $event->status->isEditableByCustomer();
    }

    /** N13 — el presupuesto lo emite la artista, nadie mas. */
    public function quote(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /** SEC-010 — confirmar es cosa de la administradora. */
    public function confirm(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * El cliente acepta el presupuesto que le han emitido. Es lo unico del
     * flujo de negociacion que le corresponde.
     */
    public function acceptQuote(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    public function cancel(User $user, Event $event): bool
    {
        return $this->owns($user, $event) || $user->isAdmin();
    }

    private function owns(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }
}
