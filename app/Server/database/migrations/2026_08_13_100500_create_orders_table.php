<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El pedido. Reescribe la tabla de v1 (D25).
 *
 * Los importes los calcula el servidor via PricingService y nunca llegan del
 * cliente (SEC-006). El envio se cobra aqui, una vez por pedido, y no en
 * cada linea como hacia v1 (D17, N5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Sin DEFAULT: el estado lo fija siempre el Service que crea o
            // mueve el pedido, y las transiciones validas las declara el enum.
            $table->string('status', 30);

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->foreignId('shipping_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('shipping_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // DB-005 — en v1 esto era `order_date`, un DATE sin hora que se
            // usaba para ordenar cronologicamente. Es nulo mientras el pedido
            // sigue siendo un carrito.
            $table->timestamp('placed_at')->nullable();

            // DB-005 — en v1 la direccion era un string plano.
            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone', 30)->nullable();
            $table->string('shipping_line1')->nullable();
            $table->string('shipping_line2')->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_province', 100)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 2)->nullable();

            /*
             * DB-006 — un solo carrito abierto por usuario, garantizado por
             * la base de datos.
             *
             * Mismo truco que el `confirmed_slot` de N16: vale el id del
             * usuario mientras el pedido es un carrito y NULL en cuanto deja
             * de serlo, con indice unico encima. Los NULL no colisionan, asi
             * que un usuario puede tener un carrito y todos los pedidos que
             * quiera.
             *
             * v1 no lo impedia y `getCartOrder` resolvia el empate con un
             * `->first()`: el carrito que veias dependia del orden de
             * insercion. `CartService` ya lo evita, pero esto lo hace cierto
             * aunque dos peticiones simultaneas se crucen.
             *
             * Aqui no hace falta distinguir motores: es un `CASE` sin `ELSE`
             * sobre un entero, sin concatenar nada.
             */
            $table->unsignedBigInteger('cart_slot')
                ->storedAs("CASE WHEN status = 'cart' THEN user_id END")
                ->nullable();

            // DB-004 — v1 borraba el pedido y sus lineas sin traza.
            $table->softDeletes();
            $table->timestamps();

            $table->unique('cart_slot');

            // DB-003 — «mis pedidos» y los listados del backoffice.
            $table->index(['user_id', 'status']);
            $table->index(['status', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
