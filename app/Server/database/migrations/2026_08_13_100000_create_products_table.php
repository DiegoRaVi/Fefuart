<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-002. La tabla `products` de v1 se llamaba igual pero era otra cosa:
 * tenia `order_id` y `price`, o sea que era la tabla de lineas de pedido.
 * Aqui `products` es el catalogo —que se puede encargar— y la linea concreta
 * vive en `order_items`.
 *
 * El precio no esta aqui: esta en `product_variants` (D14, N4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 50);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // N9 — la imagen de referencia no es un adjunto opcional: es el
            // material de partida. Activo en dibujo por encargo y en ramos,
            // desactivado en letras infantiles.
            $table->boolean('requires_reference_image')->default(false);
            $table->boolean('requires_notes')->default(false);

            // Tope de copias del producto. El limite de una sola copia en
            // entrega digital no es esta columna: es una regla de negocio que
            // depende del tipo de entrega de la linea, no del producto, y
            // vive en CartService (D26, N3).
            $table->unsignedSmallInteger('max_quantity')->default(10);

            $table->unsignedSmallInteger('delivery_days')->default(15);

            // DB-004 — v1 borraba sin traza.
            $table->softDeletes();
            $table->timestamps();

            // DB-003 — el catalogo publico filtra por activo y categoria.
            $table->index(['is_active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
