<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los cobros. D3 — el estado del pago va aparte del estado del negocio.
 *
 * Polimorfica sobre pedido o evento: un pedido de catalogo se paga entero y
 * un evento se reserva con una señal (N15), pero el cobro en si es lo mismo
 * y las dos cosas necesitan el mismo rastro.
 *
 * Nada de esto lo escribe el cliente. Los importes salen del pedido, que a
 * su vez los calculo PricingService (SEC-006), y el estado solo lo mueve el
 * webhook con firma verificada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // `payments.order_id` seria mas simple, pero entonces la señal de
            // un evento no tendria donde vivir y acabaria en otra tabla igual
            // a esta con otro nombre.
            $table->morphs('payable');

            $table->string('provider', 30);

            /*
             * Con Checkout hospedado, la sesion es lo primero que existe y el
             * PaymentIntent aparece despues, cuando el cliente llega a pagar.
             * Por eso la sesion es unica y obligatoria y el intent es
             * opcional: un cobro abandonado tiene sesion y no tiene intent.
             */
            $table->string('provider_session_id')->unique();
            $table->string('provider_payment_intent_id')->nullable()->unique();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 20);

            // N15 — entero o señal.
            $table->string('kind', 20);

            /*
             * Evita cobrar dos veces por el mismo motivo. Si la peticion de
             * creacion se repite —el cliente pulsa dos veces, la red se cae y
             * se reintenta—, Stripe devuelve la sesion que ya existia en vez
             * de crear otra.
             */
            $table->string('idempotency_key')->unique();

            $table->timestamp('paid_at')->nullable();

            // Lo que devolvio la pasarela cuando algo salio mal, para poder
            // mirarlo despues sin depender del panel de Stripe.
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
