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

            /*
             * La foto del articulo, para el catalogo y la ficha.
             *
             * Anadida tras la auditoria de UX del 2026-08-20: la tienda vendia
             * dibujos sin enseñar ninguno, y el precio aparecia antes que el
             * producto. Es nullable porque un producto recien creado desde el
             * backoffice todavia no la tiene y la tienda no puede caerse por
             * eso.
             *
             * Columna propia y no una consulta a la galeria: son cosas
             * distintas (D33). La galeria es obra seleccionada por ella, sin
             * relacion con lo que se vende; esto es el escaparate de un
             * articulo concreto.
             */
            $table->foreignId('image_media_id')->nullable()->constrained('media_assets');

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
