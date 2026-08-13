<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de Live Art. Reescribe la tabla de v1 (D25).
 *
 * D27 — solo la parte base. El presupuesto, la señal y el `confirmed_slot`
 * de N16 llegan en la Fase 5, que es donde nace ese flujo y donde la
 * colision de fechas se puede probar de verdad; ademas `confirmed_slot` es
 * una columna generada con sintaxis de MariaDB y los tests corren en SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('phone', 30)->nullable();

            // En v1 se llamaba `date`, que choca con la funcion homonima y no
            // dice de que fecha habla.
            $table->date('event_date');
            $table->string('schedule', 20);
            $table->string('location');

            // N14 — lo que la artista necesita para presupuestar y que v1 no
            // pedia. Los dos primeros son los que determinan la tarifa.
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->unsignedSmallInteger('duration_hours')->nullable();
            $table->string('event_type', 50)->nullable();

            // Sin DEFAULT: lo fija el Service que crea la solicitud, y las
            // transiciones validas las declara EventStatus. En v1 era un enum
            // de base de datos con `default 'pending'` y el propietario podia
            // moverlo a `confirmed` (SEC-010).
            $table->string('status', 30);

            $table->softDeletes();
            $table->timestamps();

            // DB-003 — el backoffice lista por estado y fecha.
            $table->index(['status', 'event_date']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
