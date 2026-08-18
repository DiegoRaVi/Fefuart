<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El registro de lo que nos manda la pasarela.
 *
 * Stripe **reenvia** los eventos cuando no recibe un 2xx a tiempo, y puede
 * mandar el mismo dos veces aunque todo vaya bien: su garantia es «al menos
 * una vez», no «exactamente una». Sin esta tabla, un reenvio de
 * `payment_intent.succeeded` volveria a mover el pedido, y con la señal de un
 * evento podria llegar a cobrarse dos veces.
 *
 * El indice unico sobre `provider_event_id` es lo que lo impide, y es la
 * base de datos quien lo garantiza: dos entregas simultaneas del mismo
 * evento chocan ahi aunque las dos pasen a la vez por el mismo `if`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('provider_event_id')->unique();
            $table->string('type');

            // Se guarda entero. Cuando algo no cuadre, lo que importa es que
            // llego exactamente, no lo que nosotros entendimos.
            $table->json('payload');

            // Nulo mientras se procesa: distingue «llego» de «se atendio».
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['provider', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
