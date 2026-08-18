<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de Live Art. Reescribe la tabla de v1 (D25).
 *
 * El presupuesto y la señal siguen siendo de la Fase 5. El `confirmed_slot`
 * de N16 ya esta aqui: la portabilidad que D27 aplazaba resulto ser una sola
 * diferencia entre motores —como se concatenan dos cadenas— y no justificaba
 * dejar la agenda sin proteger.
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

            /*
             * N16 — no puede haber dos eventos confirmados en la misma fecha
             * y franja, y lo garantiza la base de datos.
             *
             * La columna vale NULL salvo que el evento este confirmado, y los
             * NULL no colisionan entre si en un indice unico: por eso las
             * solicitudes pueden solaparse cuanto quieran (N17) y solo lo
             * confirmado queda restringido.
             *
             * Es generada y no mantenida por la aplicacion a proposito. Si
             * manana alguien anade un camino que confirme un evento sin pasar
             * por el servicio, el motor lo para igual — que es lo que quiere
             * decir «la aplicacion no es la unica linea de defensa».
             */
            $table->string('confirmed_slot', 40)->storedAs($this->expresionDeFranja())->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique('confirmed_slot');

            // DB-003 — el backoffice lista por estado y fecha.
            $table->index(['status', 'event_date']);
            $table->index('user_id');
        });
    }

    /**
     * Lo unico que cambia entre motores es como se pegan dos cadenas: MariaDB
     * usa `CONCAT` y SQLite `||`. El resto de la expresion —un `CASE` sin
     * `ELSE`, que devuelve NULL— la entienden los dos igual.
     */
    private function expresionDeFranja(): string
    {
        $pegado = DB::getDriverName() === 'sqlite'
            ? "event_date || '-' || schedule"
            : 'CONCAT(event_date, \'-\', schedule)';

        return "CASE WHEN status = 'confirmed' THEN {$pegado} END";
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
