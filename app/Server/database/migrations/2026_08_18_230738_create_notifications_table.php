<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D10 — el centro de avisos de la aplicacion.
 *
 * Generada por `php artisan notifications:table` y dejada tal cual. Es la
 * excepcion consciente a D25: las migraciones de v1 se reescriben en sitio
 * para que el arbol se lea como el esquema objetivo, pero esta la define el
 * framework —es la tabla que espera `DatabaseNotification`— y retocarla
 * significaria mantener a mano una copia de algo que no es nuestro.
 *
 * `morphs('notifiable')` crea ya el indice `(notifiable_type,
 * notifiable_id)`, que es por donde se consulta siempre: los avisos de una
 * persona. No hace falta ninguno mas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            // UUID y no autoincremental: el id viaja en la URL de
            // `PATCH /api/notifications/{id}/read`, y una numeracion
            // correlativa invita a tantear la de al lado.
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
