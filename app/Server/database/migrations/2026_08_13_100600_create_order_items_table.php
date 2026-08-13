<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El encargo concreto (D14). Cada dibujo es unico y lleva su descripcion y su
 * foto de partida; lo que deja de ser unico es el precio, que vive en el
 * catalogo.
 *
 * Las columnas de snapshot congelan el precio de compra: cambiar el catalogo
 * no reescribe el historico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // El catalogo usa softDeletes, asi que estas nunca llegan a
            // dispararse; estan para que un borrado duro accidental no deje
            // lineas huerfanas.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();

            // N7 — se elige por linea y lo limita la variante via pivot.
            $table->string('delivery_type', 20);

            // N3 — copias de la misma lamina, no encargos distintos.
            $table->unsignedSmallInteger('quantity')->default(1);

            $table->text('customer_notes')->nullable();

            // N9 — el material de partida. `nullOnDelete` porque la supresion
            // RGPD (D22) borra las imagenes del usuario y conserva el pedido.
            $table->foreignId('reference_media_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            // D20/N11 — el archivo final que sube la artista.
            $table->foreignId('delivered_media_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            $table->string('product_name');
            $table->string('variant_name', 80);
            $table->decimal('unit_price', 8, 2);
            $table->decimal('additional_copy_price', 8, 2);
            $table->decimal('line_total', 10, 2);

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
