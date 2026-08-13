<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donde vive el precio (N2: IVA incluido, es el precio final que ve el
 * cliente; no hay columnas de base imponible ni cuota).
 *
 * «Diseno de moda» 30 EUR, «Acuarela» 40 EUR, «Digital» 20 EUR. El ramo y
 * las letras tienen una variante unica. Todo producto tiene al menos una.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);

            // N4 — la primera copia paga el trabajo artistico; las siguientes,
            // solo la impresion. Ambos importes se editan desde el backoffice
            // (D5), asi que ninguna cifra queda congelada en codigo.
            $table->decimal('price', 8, 2);
            $table->decimal('additional_copy_price', 8, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
