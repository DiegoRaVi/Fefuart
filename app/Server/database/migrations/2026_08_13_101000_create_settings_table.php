<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los ajustes del negocio que la artista puede cambiar sin tocar codigo.
 *
 * N15 nombra `settings.deposit_percentage` explicitamente: la señal es un
 * porcentaje fijo del presupuesto y tiene que ser configurable. Dejarlo en
 * una constante seria la version de este proyecto de lo que ya pasaba en v1,
 * donde las reglas de precio vivian repartidas por el codigo y cambiarlas
 * significaba tocar dos formularios distintos.
 *
 * Clave y valor en texto, sin tipos. Son cuatro ajustes; una tabla por tipo
 * o una columna JSON serian mas maquinaria de la que hace falta, y quien lee
 * el valor —SettingsService— es el que sabe que forma tiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
