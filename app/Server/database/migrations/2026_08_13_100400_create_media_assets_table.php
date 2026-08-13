<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficheros subidos: la foto de partida del cliente (N9) y, mas adelante, la
 * entrega digital que sube la artista (D20/N11).
 *
 * `user_id` es quien lo subio, y es lo unico que hace falta para que la
 * Policy decida. En v1 las imagenes colgaban de `products` sin ninguna
 * comprobacion de propiedad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');

            // Que disco lo guarda: `public` las referencias, `private` las
            // entregas digitales, que solo se sirven tras pasar por Policy.
            $table->string('visibility', 20);

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
