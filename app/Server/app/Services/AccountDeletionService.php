<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * D21 y D22 — desactivar y suprimir, que no son lo mismo.
 *
 * **Desactivar es reversible**: la cuenta deja de poder entrar y los datos
 * quedan intactos. Sirve para suspender a alguien o para que el cliente
 * aparque su cuenta.
 *
 * **Suprimir no lo es.** Un borrado logico conserva el dato personal y lo
 * mantiene tratable, asi que no cubre el art. 17 del RGPD: la supresion es
 * anonimizar de verdad. Y no es `$user->delete()`, porque el pedido tiene que
 * sobrevivir con su importe y su fecha — lo ampara el art. 17(3)(b), que
 * permite conservar lo que exige una obligacion legal.
 *
 * La direccion de envio **si** se anonimiza, y se deduce de D18: el sistema
 * no emite facturas, se gestionan fuera, asi que aqui no hay obligacion
 * contable que cubra la direccion. Lo que debe sobrevivir es cuanto y cuando.
 */
class AccountDeletionService
{
    public function __construct(private readonly MediaStorageService $medios) {}

    public function desactivar(User $user): void
    {
        $user->deactivated_at = now();
        $user->save();
    }

    public function reactivar(User $user): void
    {
        $user->deactivated_at = null;
        $user->save();
    }

    /**
     * Anonimiza la cuenta y todo rastro personal que cuelgue de ella.
     *
     * Irreversible por definicion: si se pudiera deshacer, no seria
     * supresion.
     *
     * @throws ValidationException queda trabajo por entregar
     */
    public function suprimir(User $user): void
    {
        $this->guardSinTrabajoPendiente($user);

        DB::transaction(function () use ($user) {
            $this->anonimizarPedidos($user);
            $this->anonimizarEventos($user);
            $this->anonimizarCuenta($user);

            // Los avisos llevan el nombre y el titulo del evento dentro de su
            // JSON, asi que anonimizarlos seria reescribirlos: se borran.
            $user->notifications()->delete();
        });

        // Fuera de la transaccion: borrar ficheros del disco no se puede
        // deshacer con un rollback, asi que va cuando la base ya esta
        // comprometida. Si falla, quedan huerfanos —y el comando de limpieza
        // los recoge— en vez de quedar filas anonimizadas apuntando a nada.
        $this->borrarSusFicheros($user);
    }

    /**
     * Ejecucion del contrato (art. 6(1)(b)): no se suprime a quien todavia
     * espera algo que ha pagado. Se dice cual, para que pueda esperar o
     * cancelarlo.
     *
     * @throws ValidationException
     */
    private function guardSinTrabajoPendiente(User $user): void
    {
        $pedidos = $user->orders()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::InProgress, OrderStatus::Shipped])
            ->pluck('id');

        $eventos = $user->events()
            ->whereIn('status', [EventStatus::Accepted, EventStatus::Confirmed])
            ->pluck('id');

        if ($pedidos->isEmpty() && $eventos->isEmpty()) {
            return;
        }

        $partes = [];

        if ($pedidos->isNotEmpty()) {
            $partes[] = 'el pedido '.$pedidos->map(fn ($id) => "#{$id}")->join(', ');
        }

        if ($eventos->isNotEmpty()) {
            $partes[] = 'un evento reservado';
        }

        throw ValidationException::withMessages([
            'account' => 'Todavia tienes '.implode(' y ', $partes)
                .' en marcha. Podemos suprimir tu cuenta en cuanto se complete o lo canceles.',
        ]);
    }

    private function anonimizarPedidos(User $user): void
    {
        // El importe, la fecha y el estado se quedan. Lo personal se va.
        $user->orders()->update([
            'shipping_name' => null,
            'shipping_phone' => null,
            'shipping_line1' => null,
            'shipping_line2' => null,
            'shipping_city' => null,
            'shipping_province' => null,
            'shipping_postal_code' => null,
            'shipping_country' => null,
        ]);

        // El snapshot de precio se conserva; lo que el cliente escribio, no.
        DB::table('order_items')
            ->whereIn('order_id', $user->orders()->pluck('id'))
            ->update(['customer_notes' => null]);
    }

    private function anonimizarEventos(User $user): void
    {
        /*
         * La fecha y la franja se quedan: son la agenda de Felicitas, no un
         * dato personal, y borrarlas liberaria una reserva que existio.
         *
         * `title` y `location` son NOT NULL —la agenda no admite un evento
         * sin fecha ni sitio—, asi que se sustituyen por un marcador en vez
         * de vaciarse. El resultado es el mismo: deja de identificar a nadie.
         */
        $user->events()->update([
            'title' => 'Evento suprimido',
            'description' => null,
            'phone' => null,
            'location' => 'Suprimido',
        ]);
    }

    private function anonimizarCuenta(User $user): void
    {
        // El email lleva el id porque tiene indice unico: dos cuentas
        // suprimidas con el mismo valor chocarian. `.invalid` es un TLD
        // reservado justamente para esto (RFC 2606), asi que nunca sera de
        // nadie ni recibira correo por accidente.
        $user->name = 'Cuenta suprimida';
        $user->email = "suprimido-{$user->id}@fefuart.invalid";
        $user->email_verified_at = null;

        // Aleatoria y desechada: la cuenta no vuelve a usarse.
        $user->password = Str::random(64);

        $user->deactivated_at = now();
        $user->save();
    }

    private function borrarSusFicheros(User $user): void
    {
        MediaAsset::query()
            ->where('user_id', $user->id)
            ->get()
            ->each(fn (MediaAsset $media) => $this->medios->delete($media));
    }
}
